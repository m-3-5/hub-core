<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantUsageEntry;
use App\Models\User;
use App\Services\ContentModerator;
use App\Support\PayPerUse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use M35\HubSitebuilder\Models\GeneratedSite;
use M35\HubSitebuilder\Services\SiteBuilderHeroGenerator;
use M35\HubSitebuilder\Services\SiteTranslations;
use Tests\TestCase;

/** Il sito in altre lingue: 1 € a lingua, traduce l'IA, si ritraduce da solo (gratis) quando cambia. */
class SiteTranslationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.hub_billing.pay_per_use.enabled' => true, 'services.hub_billing.secret_key' => 'sk_test_hub', 'services.gemini.api_key' => 'test-key']);
        Cache::put('gemini.model_catalog', ['text_models' => ['gemini-test'], 'text_best' => 'gemini-test']);
        Notification::fake();
    }

    private function english(string $tagline = 'Everything for your home, close to you'): array
    {
        return [
            'tagline' => $tagline, 'cta_label' => 'Call us', 'about' => 'We are a hardware shop in Rossano.',
            'services' => [['name' => 'Tools', 'line' => 'For every job'], ['name' => 'Paints', 'line' => '']],
            'strengths' => [], 'faq' => [['q' => 'Do you deliver?', 'a' => 'Yes, in town.']], 'closing' => '', 'hours' => 'Monday closed',
        ];
    }

    private function fakeGemini(array|int $reply): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => is_int($reply)
            ? Http::response(['error' => ['message' => 'overloaded']], $reply)
            : Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode($reply)]]]]]])]);
    }

    /** @return array{0: Tenant, 1: User, 2: GeneratedSite} */
    private function siteOnline(bool $card = true): array
    {
        $tenant = Tenant::create(['name' => 'Ferramenta Rossi', 'slug' => 'ferramenta-rossi', 'type' => 'azienda', 'plan' => 'demo', 'guest_verified_at' => now()]);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'admin']);
        if ($card) {
            PayPerUse::saveCard($tenant, 'cus_1', ['payment_method' => 'pm_1', 'brand' => 'visa', 'last4' => '4242']);
        }
        $site = GeneratedSite::create(['tenant_id' => $tenant->id, 'status' => 'published', 'published_at' => now(), 'answers' => [
            'tagline' => 'Tutto per la casa, a due passi da te', 'services' => ['Utensili', 'Vernici'], 'cta_label' => 'Chiamaci', 'extra' => 'Siamo una ferramenta di Rossano.',
            'extras' => ['service_details' => [['name' => 'Utensili', 'line' => 'Per ogni lavoro', 'icon' => '🔧']], 'faq' => [['q' => 'Consegnate?', 'a' => 'Sì, in città.']]],
            'info' => ['hours' => 'Lunedì chiuso', 'schedule' => ['days' => [2, 3], 'ranges' => [['09:00', '13:00']]]],
        ]]);

        return [$tenant->fresh(), $user, $site];
    }

    public function test_with_a_card_the_chosen_languages_go_online_at_one_euro_each(): void
    {
        [$tenant, $user, $site] = $this->siteOnline();
        $this->fakeGemini($this->english());

        $this->actingAs($user)->from(route('admin.sitebuilder.show', $tenant))
            ->post(route('admin.sitebuilder.languages', $tenant), ['langs' => ['en', 'de']])
            ->assertRedirect(route('admin.sitebuilder.show', $tenant))->assertSessionHas('success');

        $this->assertSame(['en', 'de'], SiteTranslations::active($site->fresh()));
        $this->assertSame(200, PayPerUse::pendingCents($tenant));
        $this->assertSame(['lang_en', 'lang_de'], TenantUsageEntry::orderBy('id')->pluck('item_type')->all());

        // Chiederla di nuovo non costa.
        $this->post(route('admin.sitebuilder.languages', $tenant), ['langs' => ['en']])->assertSessionHas('success', 'Queste lingue sono già online.');
        $this->assertSame(2, TenantUsageEntry::count());

        auth()->logout();
        $this->get(route('site.public.lang', [$tenant, 'en']))->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('Everything for your home, close to you')
            ->assertSee('What we offer')->assertSee('Tools')->assertSee('Do you deliver?')
            ->assertSee('Tue, Wed 9:00-13:00')
            ->assertDontSee('Cosa offriamo');
        $this->get(route('site.public.show', $tenant))->assertOk()
            ->assertSee('Tutto per la casa')->assertSee('hreflang="en"', false)->assertSee(route('site.public.lang', [$tenant, 'de']), false);
        $this->get(route('site.public.lang', [$tenant, 'fr']))->assertNotFound();
    }

    public function test_without_a_card_the_languages_wait_for_the_card(): void
    {
        [$tenant, $user, $site] = $this->siteOnline(card: false);

        $this->actingAs($user)->post(route('admin.sitebuilder.languages', $tenant), ['langs' => ['fr', 'es']])
            ->assertRedirect(route('admin.credit.show', $tenant));
        $this->assertSame(['fr', 'es'], SiteTranslations::wanted($site->fresh()));
        $this->assertSame([], SiteTranslations::active($site->fresh()));
        $this->assertSame(0, TenantUsageEntry::count());
        $this->get(route('admin.credit.show', $tenant))->assertOk()->assertSee('Il tuo sito in francese, spagnolo')->assertSee('2 €');

        $this->fakeGemini($this->english());
        Http::fake([
            'api.stripe.com/v1/customers/cus_9' => Http::response(['id' => 'cus_9']),
            'api.stripe.com/v1/checkout/sessions/cs_setup*' => Http::response([
                'id' => 'cs_setup', 'mode' => 'setup', 'status' => 'complete', 'customer' => 'cus_9',
                'metadata' => ['tenant_id' => (string) $tenant->id, 'ppu_setup' => '1'],
                'setup_intent' => ['status' => 'succeeded', 'payment_method' => ['id' => 'pm_9', 'card' => ['brand' => 'visa', 'last4' => '4242']]],
            ]),
        ]);
        $this->get(route('admin.credit.return', [$tenant, 'session_id' => 'cs_setup']))->assertRedirect(route('admin.sitebuilder.show', $tenant));

        $this->assertSame(['fr', 'es'], SiteTranslations::active($site->fresh()));
        $this->assertSame([], SiteTranslations::wanted($site->fresh()));
        $this->assertSame(200, PayPerUse::pendingCents($tenant));
    }

    public function test_if_the_ai_fails_nothing_is_charged(): void
    {
        [$tenant, $user, $site] = $this->siteOnline();
        $this->fakeGemini(503);

        $this->actingAs($user)->post(route('admin.sitebuilder.languages', $tenant), ['langs' => ['en']])->assertSessionHasErrors('tagline');
        $this->assertSame([], SiteTranslations::active($site->fresh()));
        $this->assertSame(0, TenantUsageEntry::count());
    }

    public function test_editing_the_site_translates_again_for_free(): void
    {
        [$tenant, $user, $site] = $this->siteOnline();
        $reply = fn (array $j) => ['candidates' => [['content' => ['parts' => [['text' => json_encode($j)]]]]]];
        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()->push($reply($this->english()))->push($reply($this->english('New tools every week')))]);
        $this->actingAs($user)->post(route('admin.sitebuilder.languages', $tenant), ['langs' => ['en']]);

        $this->mock(ContentModerator::class, fn ($m) => $m->shouldReceive('check')->andReturn(['allowed' => true, 'unavailable' => false, 'category' => 'ok', 'reason' => '']));
        $this->mock(SiteBuilderHeroGenerator::class, fn ($m) => $m->shouldReceive('generate', 'illustration')->andReturn(null));

        $this->post(route('admin.sitebuilder.generate', $tenant), ['tagline' => 'Novità ogni settimana', 'services' => 'Utensili, Vernici', 'cta_label' => 'Chiamaci'])
            ->assertRedirect(route('admin.sitebuilder.show', $tenant));

        $this->assertSame('New tools every week', $site->fresh()->answers['i18n']['texts']['en']['tagline']);
        $this->assertSame(1, TenantUsageEntry::count());
    }

    public function test_published_classified_ads_appear_on_the_site_drafts_do_not(): void
    {
        [$tenant] = $this->siteOnline();
        \App\Models\ClassifiedAd::create(['tenant_id' => $tenant->id, 'slug' => 'bilocale', 'zone' => 'Rossano', 'title' => 'Bilocale al lago', 'category' => 'affitto', 'price' => 450, 'price_unit' => 'mese', 'status' => 'published', 'published_at' => now(), 'description' => 'Luminoso']);
        \App\Models\ClassifiedAd::create(['tenant_id' => $tenant->id, 'slug' => 'bozza', 'zone' => 'Rossano', 'title' => 'Garage in bozza', 'category' => 'affitto', 'status' => 'draft', 'description' => 'x']);

        $this->get(route('site.public.show', $tenant))->assertOk()
            ->assertSee('I nostri annunci')->assertSee('Bilocale al lago')->assertSee('href="#annunci"', false)
            ->assertDontSee('Garage in bozza');
    }
}
