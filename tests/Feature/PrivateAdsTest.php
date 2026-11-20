<?php

namespace Tests\Feature;

use App\Models\ClassifiedAd;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ClassifiedReviewedNotification;
use App\Notifications\ClassifiedToReviewNotification;
use App\Notifications\EmailLoginLinkNotification;
use App\Support\EmailLogin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Annunci dei privati: modulo da telefono (anche precompilato da piramide35.com), link via email senza password,
 * controllo di Max, 30 giorni online, elenco JSON per paese.
 */
class PrivateAdsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.leads_monitor_email' => 'max@example.com']);
        Notification::fake();
        Storage::fake('public');
    }

    private function max(): User
    {
        return User::factory()->create(['email' => 'staff@example.com', 'is_super_admin' => true]);
    }

    private function sentLink(string $email): string
    {
        $url = null;
        Notification::assertSentTo(new AnonymousNotifiable, EmailLoginLinkNotification::class, function ($n, $channels, $notifiable) use ($email, &$url) {
            if (($notifiable->routes['mail'] ?? null) !== $email) {
                return false;
            }
            $url = $n->toMail($notifiable)->actionUrl;

            return true;
        });

        return $url;
    }

    public function test_prefilled_link_from_piramide35_opens_the_form_with_fields_written(): void
    {
        $this->get('/annunci/nuovo?zona=Senise&tipo=cerco&testo='.rawurlencode("Cerco bici per bambino\nDi 6 anni").'&ref=piramide35')
            ->assertOk()
            ->assertSee('value="Senise"', false)
            ->assertSee('value="Cerco bici per bambino"', false)
            ->assertSee('Di 6 anni')
            ->assertSee('name="ref" value="piramide35"', false)
            ->assertSee('value="cerco" checked', false);
    }

    public function test_guest_publishes_confirms_by_email_and_max_approves_for_30_days(): void
    {
        $this->post('/annunci/nuovo', [
            'tipo' => 'vendo', 'title' => 'Bici da bambino', 'description' => 'Usata poco, ritiro a Senise.',
            'zone' => 'Senise', 'price' => '40', 'name' => 'Lucia', 'email' => 'Lucia@Example.com', 'ref' => 'piramide35',
            'images' => [UploadedFile::fake()->image('bici.jpg', 2400, 1800)],
        ])->assertRedirect(route('classifieds.private.create'))->assertSessionHas('sent', 'lucia@example.com');

        $ad = ClassifiedAd::sole();
        $this->assertSame('email', $ad->status);
        $this->assertSame('oggetti', $ad->category);
        $this->assertSame('piramide35', $ad->source);
        $this->assertSame('privato', $ad->tenant->type);
        $this->assertCount(1, $ad->images);
        $this->assertNotNull($ad->thumb);
        Storage::disk('public')->assertExists([$ad->images[0], $ad->thumb]);
        [$w] = getimagesizefromstring(Storage::disk('public')->get($ad->images[0]));
        $this->assertSame(1600, $w); // ridotta anche dal server
        [$tw] = getimagesizefromstring(Storage::disk('public')->get($ad->thumb));
        $this->assertSame(480, $tw);

        // Non ancora online, né in bacheca né nel JSON
        $this->get($ad->publicUrl())->assertNotFound();
        Notification::assertNotSentTo(new AnonymousNotifiable, ClassifiedToReviewNotification::class);

        // Clic sul link dell'email: entra senza password, l'annuncio va a Max
        $this->get($this->sentLink('lucia@example.com'))->assertRedirect('/annunci/conferma/'.$ad->slug);
        $this->assertAuthenticatedAs(User::where('email', 'lucia@example.com')->sole());
        $this->get('/annunci/conferma/'.$ad->slug)->assertRedirect(route('classifieds.private.mine'));
        $this->assertSame('review', $ad->fresh()->status);
        Notification::assertSentTo(new AnonymousNotifiable, ClassifiedToReviewNotification::class, fn ($n, $c, $to) => $to->routes['mail'] === 'max@example.com');
        $this->get(route('classifieds.private.mine'))->assertOk()->assertSee('Bici da bambino')->assertSee('In controllo');

        // Max approva
        $this->actingAs($this->max())->get(route('admin.classified-review.index'))->assertOk()->assertSee('Bici da bambino');
        $this->post(route('admin.classified-review.approve', $ad))->assertRedirect();
        $ad->refresh();
        $this->assertTrue($ad->isPublished());
        $this->assertEqualsWithDelta(now()->addDays(30)->timestamp, $ad->expires_at->timestamp, 5);
        Notification::assertSentTo(new AnonymousNotifiable, ClassifiedReviewedNotification::class, fn ($n, $c, $to) => $to->routes['mail'] === 'lucia@example.com');
        $this->get($ad->publicUrl())->assertOk()->assertSee('Bici da bambino');

        // Dopo 30 giorni sparisce
        $this->travel(31)->days();
        $this->get($ad->publicUrl())->assertNotFound();
        $this->assertSame(0, ClassifiedAd::published()->count());
    }

    public function test_logged_in_user_goes_straight_to_review(): void
    {
        $tenant = Tenant::create(['name' => 'Gino', 'slug' => 'gino', 'type' => 'privato', 'plan' => 'demo', 'guest_verified_at' => now()]);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'admin']);

        $this->actingAs($user)->post('/annunci/nuovo', ['tipo' => 'lavoro', 'title' => 'Cerco lavoro come cameriere', 'description' => 'Esperienza 2 anni.', 'zone' => 'Senise'])
            ->assertRedirect(route('classifieds.private.mine'));

        $ad = ClassifiedAd::sole();
        $this->assertSame(['review', 'lavoro', $tenant->id], [$ad->status, $ad->category, $ad->tenant_id]);
        Notification::assertSentTo(new AnonymousNotifiable, ClassifiedToReviewNotification::class);
        Notification::assertNotSentTo(new AnonymousNotifiable, EmailLoginLinkNotification::class);
    }

    public function test_rejected_ad_stays_offline_and_owner_is_told(): void
    {
        $tenant = Tenant::create(['name' => 'Gino', 'slug' => 'gino', 'type' => 'privato', 'plan' => 'demo']);
        $ad = $tenant->classifiedAds()->create(['slug' => 'x-1', 'category' => 'cerco', 'title' => 'X', 'zone' => 'Senise', 'description' => 'x', 'status' => 'review', 'contact_email' => 'gino@example.com']);

        $this->actingAs($this->max())->post(route('admin.classified-review.reject', $ad), ['reason' => 'Foto non adatta'])->assertRedirect();

        $this->assertSame('rejected', $ad->fresh()->status);
        Notification::assertSentTo(new AnonymousNotifiable, ClassifiedReviewedNotification::class, fn ($n, $c, $to) => $to->routes['mail'] === 'gino@example.com');
    }

    public function test_only_max_moderates(): void
    {
        $tenant = Tenant::create(['name' => 'Gino', 'slug' => 'gino', 'type' => 'privato', 'plan' => 'demo']);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'admin']);
        $ad = $tenant->classifiedAds()->create(['slug' => 'x-1', 'category' => 'cerco', 'title' => 'X', 'zone' => 'Senise', 'description' => 'x', 'status' => 'review']);

        $this->actingAs($user)->get(route('admin.classified-review.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.classified-review.approve', $ad))->assertForbidden();
        $this->assertSame('review', $ad->fresh()->status);
    }

    public function test_guest_needs_name_and_email_and_staff_email_cannot_be_used(): void
    {
        $this->from('/annunci/nuovo')->post('/annunci/nuovo', ['tipo' => 'vendo', 'title' => 'A', 'description' => 'b', 'zone' => 'Senise'])
            ->assertSessionHasErrors(['name', 'email']);

        $this->max();
        $this->from('/annunci/nuovo')->post('/annunci/nuovo', ['tipo' => 'vendo', 'title' => 'A', 'description' => 'b', 'zone' => 'Senise', 'name' => 'X', 'email' => 'staff@example.com'])
            ->assertSessionHasErrors('email');
        $this->assertDatabaseCount('classified_ads', 0);
    }

    public function test_email_link_is_signed_and_cannot_redirect_elsewhere(): void
    {
        $this->assertSame('/app', EmailLogin::safeNext('https://evil.example'));
        $this->assertSame('/app', EmailLogin::safeNext('//evil.example'));
        $this->assertSame('/buono?codice=X', EmailLogin::safeNext('/buono?codice=X'));

        $url = EmailLogin::url('nuovo@example.com', '/annunci/miei');
        $this->get($url.'x')->assertForbidden();
        $this->assertGuest();

        $this->get($url)->assertRedirect('/annunci/miei');
        $user = User::where('email', 'nuovo@example.com')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('privato', $user->tenants()->sole()->type);

        $this->travel(61)->minutes();
        auth()->logout();
        $this->get($url)->assertForbidden();
    }

    public function test_json_feed_by_town_and_type_readable_from_piramide35(): void
    {
        $tenant = Tenant::create(['name' => 'Gino', 'slug' => 'gino', 'type' => 'privato', 'plan' => 'demo']);
        $make = fn (string $title, string $category, string $zone, array $extra = []) => $tenant->classifiedAds()->create($extra + [
            'slug' => \Illuminate\Support\Str::slug($title), 'category' => $category, 'title' => $title, 'zone' => $zone, 'description' => 'x',
            'status' => 'published', 'published_at' => now(), 'expires_at' => now()->addDays(30), 'price' => 25,
        ]);
        $make('Divano', 'oggetti', 'Senise (PZ)', ['thumb' => 'ads/gino/thumb-a.jpg']);
        $make('Cerco bici', 'cerco', 'senise');
        $make('Lavoro bar', 'lavoro', 'Senise');
        $make('Tavolo', 'oggetti', 'Francavilla');
        $make('Vecchio', 'oggetti', 'Senise', ['expires_at' => now()->subDay()]);
        $make('In attesa', 'oggetti', 'Senise', ['status' => 'review']);

        $res = $this->getJson('/api/annunci?zona=Senise&tipo=vendo&limit=12', ['Origin' => 'https://piramide35.com'])
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin')
            ->assertJsonPath('zona', 'Senise')
            ->assertJsonCount(1, 'annunci')
            ->assertJsonPath('annunci.0.titolo', 'Divano')
            ->assertJsonPath('annunci.0.tipo', 'vendo')
            ->assertJsonPath('annunci.0.prezzo', '€ 25')
            ->assertJsonPath('annunci.0.prezzo_centesimi', 2500);
        $this->assertStringContainsString('ads/gino/thumb-a.jpg', $res->json('annunci.0.foto'));
        $this->assertStringContainsString('/a/gino/divano', $res->json('annunci.0.url'));
        $this->assertStringContainsString('max-age=300', $res->headers->get('Cache-Control'));
        $this->assertStringContainsString('/annunci/nuovo?zona=Senise&tipo=vendo&ref=piramide35', $res->json('nuovo_url'));

        $this->getJson('/api/annunci?zona=senise')->assertJsonCount(3, 'annunci');
        $this->getJson('/api/annunci?zona=Senise&tipo=lavoro')->assertJsonCount(1, 'annunci')->assertJsonPath('annunci.0.titolo', 'Lavoro bar');
        $this->getJson('/api/annunci?limit=2')->assertJsonCount(2, 'annunci');
    }

    public function test_owner_renews_and_removes(): void
    {
        $tenant = Tenant::create(['name' => 'Gino', 'slug' => 'gino', 'type' => 'privato', 'plan' => 'demo']);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'admin']);
        $ad = $tenant->classifiedAds()->create(['slug' => 'x-1', 'category' => 'cerco', 'title' => 'X', 'zone' => 'Senise', 'description' => 'x',
            'status' => 'published', 'published_at' => now()->subDays(40), 'expires_at' => now()->subDays(10)]);

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->post(route('classifieds.private.renew', $ad))->assertForbidden();

        $this->actingAs($user)->post(route('classifieds.private.renew', $ad))->assertRedirect();
        $this->assertTrue($ad->fresh()->isPublished());

        $this->actingAs($user)->post(route('classifieds.private.remove', $ad))->assertRedirect();
        $this->assertDatabaseCount('classified_ads', 0);
    }
}
