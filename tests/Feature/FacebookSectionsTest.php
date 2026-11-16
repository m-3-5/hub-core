<?php

namespace Tests\Feature;

use App\Models\Promo;
use App\Models\Tenant;
use App\Services\ContentModerator;
use App\Services\FacebookPages;
use App\Services\FacebookSections;
use App\Support\PayPerUse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use M35\HubPayments\Models\PayableService;
use M35\HubSitebuilder\Models\GeneratedSite;
use Tests\TestCase;

/** Gli hashtag di Facebook uguali per tutti: #prodotto, #servizio, #novita, #evento, #blog, #lavori. */
class FacebookSectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.facebook.app_id' => '123', 'services.facebook.app_secret' => 'segreto',
            'services.hub_billing.pay_per_use.enabled' => true, 'services.hub_billing.secret_key' => 'sk_test_hub',
        ]);
        Storage::fake('public');
        Notification::fake();
        $this->mock(ContentModerator::class, fn ($m) => $m->shouldReceive('check')->andReturn(['allowed' => true, 'unavailable' => false, 'category' => 'ok', 'reason' => '']));
    }

    private function tenant(bool $withSite = true): Tenant
    {
        $tenant = Tenant::create(['name' => 'Pizzeria Rocco', 'slug' => 'pizzeria-rocco', 'type' => 'azienda', 'plan' => 'demo', 'guest_verified_at' => now()]);
        $tenant->forceFill(['settings' => ['facebook' => ['page_id' => '555', 'page_name' => 'Pizzeria Rocco', 'token' => Crypt::encryptString('t'), 'hashtags' => ['#promo']]]])->save();

        if ($withSite) {
            GeneratedSite::create(['tenant_id' => $tenant->id, 'status' => 'published', 'published_at' => now(), 'answers' => [
                'tagline' => 'La pizza buona', 'services' => ['Pizza'], 'about' => 'Siamo noi',
                'media' => ['logo' => null, 'photos' => ['sites/pizzeria-rocco/media/a.jpg', 'sites/pizzeria-rocco/media/b.jpg']],
            ]]);
        }

        return $tenant->fresh();
    }

    private function fbPost(string $message, bool $photo = true): void
    {
        $img = imagecreatetruecolor(800, 600);
        ob_start();
        imagejpeg($img);
        $jpg = ob_get_clean();

        Http::fake([
            'graph.facebook.com/*' => Http::response(array_filter([
                'message' => $message, 'full_picture' => $photo ? 'https://scontent.fbcdn.net/p.jpg' : null,
                'permalink_url' => 'https://www.facebook.com/555/posts/1', 'created_time' => '2026-11-16T10:00:00+0000',
            ])),
            'scontent.fbcdn.net/*' => Http::response($jpg, 200, ['Content-Type' => 'image/jpeg']),
        ]);
    }

    public function test_hashtags_pick_the_section(): void
    {
        $this->assertSame('product', FacebookSections::typeOf('Nuova crema #Prodotto'));
        $this->assertSame('service', FacebookSections::typeOf('Piega #nuovoservizio'));
        $this->assertSame('event', FacebookSections::typeOf('Serata #evento'));
        $this->assertSame('news', FacebookSections::typeOf('Siamo aperti #novità'));
        $this->assertSame('blog', FacebookSections::typeOf('Consigli #blog'));
        $this->assertSame('work', FacebookSections::typeOf('Prima e dopo #lavori'));
        $this->assertNull(FacebookSections::typeOf('Buona domenica #prodottiitaliani'));
        $this->assertSame(2590, FacebookSections::price('Solo € 25,90'));
        $this->assertSame(1200, FacebookSections::price('a 12 euro'));
        $this->assertNull(FacebookSections::price('chiedi info'));
        $this->assertSame('2026-10-24 18:30', FacebookSections::eventDate('Ti aspettiamo il 24 ottobre alle 18:30', Carbon::parse('2026-10-01'))->format('Y-m-d H:i'));
    }

    public function test_a_product_with_price_and_card_goes_in_the_shop_and_costs_one_euro(): void
    {
        $tenant = $this->tenant(false); // senza sito: niente prodotti inclusi, si paga 1 €
        PayPerUse::saveCard($tenant, 'cus_1', ['payment_method' => 'pm_1', 'brand' => 'visa', 'last4' => '4242']);
        $this->fbPost("Crema viso idratante\nPerfetta per l'inverno, 25 € #prodotto #skincare");

        $item = app(FacebookPages::class)->handlePost($tenant, '555_1');

        $this->assertInstanceOf(PayableService::class, $item);
        $this->assertSame('product', $item->type);
        $this->assertSame('Crema viso idratante', $item->title);
        $this->assertSame(2500, $item->amount_cents);
        $this->assertTrue($item->fresh()->published_to_site);
        $this->assertStringContainsString('skincare', $item->description);
        $this->assertSame(100, PayPerUse::pendingCents($tenant));
        $this->assertSame(0, Promo::count());

        // Lo stesso post due volte non crea doppioni.
        app(FacebookPages::class)->handlePost($tenant, '555_1');
        $this->assertSame(1, PayableService::count());
    }

    public function test_a_service_without_price_stays_to_complete(): void
    {
        $tenant = $this->tenant();
        PayPerUse::saveCard($tenant, 'cus_1', ['payment_method' => 'pm_1', 'brand' => 'visa', 'last4' => '4242']);
        $this->fbPost("Trattamento viso alla vitamina C\nChiedi info #servizio");

        $item = app(FacebookPages::class)->handlePost($tenant, '555_1');

        $this->assertSame('service', $item->type);
        $this->assertFalse($item->published_to_site);
        $this->assertSame(0, PayPerUse::pendingCents($tenant));
    }

    public function test_news_and_events_go_in_the_site_for_free(): void
    {
        $tenant = $this->tenant();
        $this->fbPost("Serata degustazione\nIl 24 dicembre alle 21 #evento");

        app(FacebookPages::class)->handlePost($tenant, '555_1');

        $news = $tenant->generatedSite->fresh()->answers['news'];
        $this->assertCount(1, $news);
        $this->assertSame('event', $news[0]['type']);
        $this->assertSame('Serata degustazione', $news[0]['title']);
        $this->assertStringStartsWith('2026-12-24T21:00', $news[0]['event_at']);
        $this->assertSame(0, PayPerUse::pendingCents($tenant));

        $this->get(route('site.public.show', $tenant))->assertOk()->assertSee('Novità ed eventi')->assertSee('Serata degustazione')->assertSee('24/12');
    }

    public function test_lavori_adds_the_photo_to_the_site_gallery(): void
    {
        $tenant = $this->tenant();
        $this->fbPost('Prima e dopo #lavori');

        $path = app(FacebookPages::class)->handlePost($tenant, '555_1');

        $photos = $tenant->generatedSite->fresh()->answers['media']['photos'];
        $this->assertCount(3, $photos);
        $this->assertSame($path, $photos[2]);
        Storage::disk('public')->assertExists($path);
    }

    public function test_without_a_site_news_are_skipped_and_promo_still_wins(): void
    {
        $tenant = $this->tenant(false);
        $this->fbPost('Aperti anche a Natale #novita');
        $this->assertNull(app(FacebookPages::class)->handlePost($tenant, '555_1'));

        Http::swap(new \Illuminate\Http\Client\Factory);
        $this->fbPost('Pizza + bibita 7 € #promo #prodotto');
        $this->assertInstanceOf(Promo::class, app(FacebookPages::class)->handlePost($tenant, '555_2'));
        $this->assertSame(0, PayableService::count());
    }

    public function test_the_public_guide_explains_every_hashtag(): void
    {
        $page = $this->get(route('guides.facebook'))->assertOk();
        foreach (['#promo', '#prodotto', '#servizio', '#novita', '#evento', '#blog', '#lavori'] as $tag) {
            $page->assertSee($tag);
        }
    }
}
