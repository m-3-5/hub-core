<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantUsageEntry;
use App\Models\User;
use App\Support\PayPerUse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use M35\HubSitebuilder\Models\GeneratedSite;
use M35\HubSitebuilder\Services\SiteAddons;
use Tests\TestCase;

/** Opzioni del sito a 1 € l'una: SEO e parole chiave, Fatti trovare dall'IA, Indicizza il sito. */
class SiteAddonsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.hub_billing.pay_per_use.enabled' => true, 'services.hub_billing.secret_key' => 'sk_test_hub', 'services.gemini.api_key' => 'test-key']);
        Cache::put('gemini.model_catalog', ['text_models' => ['gemini-test'], 'text_best' => 'gemini-test']);
    }

    /** @return array{0: Tenant, 1: User, 2: GeneratedSite} */
    private function siteOnline(bool $card = true): array
    {
        $tenant = Tenant::create(['name' => 'Studio Bianchi', 'slug' => 'studio-bianchi', 'type' => 'azienda', 'plan' => 'demo', 'guest_verified_at' => now(), 'phone' => '338 111 2222', 'address' => 'Via Roma 1, Senise']);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'admin']);
        if ($card) {
            PayPerUse::saveCard($tenant, 'cus_1', ['payment_method' => 'pm_1', 'brand' => 'visa', 'last4' => '4242']);
        }
        $site = GeneratedSite::create(['tenant_id' => $tenant->id, 'status' => 'published', 'published_at' => now(), 'answers' => [
            'tagline' => 'Il tuo sorriso, la mia priorità', 'services' => ['Impianti', 'Sbiancamento'], 'cta_label' => 'Prenota', 'extra' => 'Studio dentistico a Senise.',
            'extras' => ['faq' => [['q' => 'Si paga a rate?', 'a' => 'Sì, chiedi in studio.']]],
            'info' => ['schedule' => ['days' => [1, 2, 3], 'ranges' => [['09:00', '13:00']]]],
        ]]);

        return [$tenant->fresh(), $user, $site];
    }

    public function test_seo_is_written_by_the_ai_and_costs_one_euro_once(): void
    {
        [$tenant, $user, $site] = $this->siteOnline();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode([
            'title' => 'Dentista a Senise — Studio Bianchi', 'description' => 'Impianti e sbiancamento a Senise, su appuntamento.',
            'keywords' => ['Dentista Senise', 'impianti dentali senise'], 'alts' => [],
        ])]]]]]])]);

        $this->actingAs($user)->from(route('admin.sitebuilder.show', $tenant))
            ->post(route('admin.sitebuilder.addon', [$tenant, 'seo']))->assertSessionHas('success');
        $this->post(route('admin.sitebuilder.addon', [$tenant, 'seo']))->assertSessionHas('success', 'Questa opzione è già attiva.');

        $this->assertTrue(SiteAddons::active($site->fresh(), 'seo'));
        $this->assertSame(['addon_seo'], TenantUsageEntry::pluck('item_type')->all());
        $this->assertSame(100, PayPerUse::pendingCents($tenant));

        auth()->logout();
        $this->get(route('site.public.show', $tenant))->assertOk()
            ->assertSee('<title>Dentista a Senise — Studio Bianchi</title>', false)
            ->assertSee('content="Impianti e sbiancamento a Senise, su appuntamento."', false)
            ->assertSee('dentista senise, impianti dentali senise');
    }

    public function test_ai_option_publishes_llms_txt_and_structured_data_without_calling_the_ai(): void
    {
        [$tenant, $user] = $this->siteOnline();
        Http::fake();

        $this->get(route('site.public.llms', $tenant))->assertNotFound();
        $this->actingAs($user)->post(route('admin.sitebuilder.addon', [$tenant, 'ai']))->assertSessionHas('success');
        Http::assertNothingSent();

        auth()->logout();
        $this->get(route('site.public.llms', $tenant))->assertOk()
            ->assertSee('# Studio Bianchi')->assertSee('- Telefono: 338 111 2222')->assertSee('- Impianti')->assertSee('Si paga a rate?');
        $this->get(route('site.public.show', $tenant))->assertOk()
            ->assertSee('"@type":"FAQPage"', false)->assertSee('"opens":"09:00"', false)->assertSee(route('site.public.llms', $tenant), false);
    }

    public function test_index_option_puts_the_site_in_the_sitemap(): void
    {
        [$tenant, $user] = $this->siteOnline();
        $this->get('/sitemap.xml')->assertOk()->assertDontSee(route('site.public.show', $tenant), false);

        $this->actingAs($user)->post(route('admin.sitebuilder.addon', [$tenant, 'index']))->assertSessionHas('success');

        $this->get('/sitemap.xml')->assertOk()->assertSee(route('site.public.show', $tenant), false);
        $this->assertSame(1, TenantUsageEntry::count());
    }

    public function test_without_a_card_the_option_waits_and_the_page_shows_the_buttons(): void
    {
        [$tenant, $user, $site] = $this->siteOnline(card: false);

        $this->actingAs($user)->get(route('admin.sitebuilder.show', $tenant))->assertOk()
            ->assertSee('Fatti trovare')->assertSee('SEO e parole chiave')->assertSee('Il tuo sito in altre lingue');

        $this->post(route('admin.sitebuilder.addon', [$tenant, 'index']))->assertRedirect(route('admin.credit.show', $tenant));
        $this->assertFalse(SiteAddons::active($site->fresh(), 'index'));
        $this->get(route('admin.credit.show', $tenant))->assertOk()->assertSee('Indicizza il sito per il tuo sito')->assertSee('1 €');
        $this->assertSame(0, TenantUsageEntry::count());
    }

    public function test_if_the_seo_ai_fails_nothing_is_charged(): void
    {
        [$tenant, $user, $site] = $this->siteOnline();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'overloaded']], 503)]);

        $this->actingAs($user)->post(route('admin.sitebuilder.addon', [$tenant, 'seo']))->assertSessionHasErrors('tagline');
        $this->assertFalse(SiteAddons::active($site->fresh(), 'seo'));
        $this->assertSame(0, TenantUsageEntry::count());
    }
}
