<?php

namespace Tests\Feature;

use App\Models\Promo;
use App\Models\Tenant;
use App\Models\TenantUsageEntry;
use App\Models\User;
use App\Support\PayPerUse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use M35\HubPayments\Models\PayableService;
use M35\HubSitebuilder\Models\GeneratedSite;
use Tests\TestCase;

/**
 * Paghi quello che pubblichi: annunci gratis; promo, prodotti e servizi 1 € (poi a scalare);
 * col sito i primi 3 di ogni tipo inclusi. Si salva la carta, si pubblica subito, si addebita dopo un mese.
 */
class PayPerUseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.hub_billing.pay_per_use.enabled' => true, 'services.hub_billing.secret_key' => 'sk_test_hub']);
    }

    /** @return array{0: Tenant, 1: User} */
    private function business(bool $card = false): array
    {
        $tenant = Tenant::create(['name' => 'Ferramenta Rossi', 'slug' => 'ferramenta-rossi', 'type' => 'azienda', 'plan' => 'demo', 'guest_verified_at' => now(), 'subscription_status' => 'trialing', 'trial_ends_at' => now()->addDays(20)]);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'admin']);

        if ($card) {
            PayPerUse::saveCard($tenant, 'cus_1', ['payment_method' => 'pm_1', 'brand' => 'visa', 'last4' => '4242']);
        }

        return [$tenant->fresh(), $user];
    }

    private function draft(Tenant $tenant, string $title = 'Sconto trapani'): Promo
    {
        return $tenant->promos()->create(['title' => $title, 'slug' => \Illuminate\Support\Str::slug($title).'-'.uniqid(), 'status' => 'draft']);
    }

    public function test_switched_off_nothing_changes(): void
    {
        config(['services.hub_billing.pay_per_use.enabled' => false]);
        [$tenant, $user] = $this->business();
        $promo = $this->draft($tenant);

        $this->actingAs($user)->post(route('admin.promos.publish', [$tenant, $promo]))->assertRedirect(route('admin.promos.show', [$tenant, $promo]));
        $this->assertSame('published', $promo->fresh()->status);
        $this->assertSame(0, TenantUsageEntry::count());
    }

    public function test_without_a_card_publishing_asks_for_the_card_and_nothing_is_charged_now(): void
    {
        [$tenant, $user] = $this->business();
        $promo = $this->draft($tenant);

        $this->actingAs($user)->post(route('admin.promos.publish', [$tenant, $promo]))->assertRedirect(route('admin.credit.show', $tenant));
        $this->assertSame('draft', $promo->fresh()->status);

        $this->get(route('admin.credit.show', $tenant))->assertOk()
            ->assertSee('Ricarica il credito')->assertSee('Salva la carta')
            ->assertSee('«Sconto trapani»', false)->assertSee('1 €');
    }

    public function test_with_a_card_it_goes_online_now_and_is_billed_in_a_month_only_once(): void
    {
        [$tenant, $user] = $this->business(card: true);
        $promo = $this->draft($tenant);

        $this->actingAs($user)->post(route('admin.promos.publish', [$tenant, $promo]))->assertRedirect(route('admin.promos.show', [$tenant, $promo]));
        $this->assertSame('published', $promo->fresh()->status);

        $entry = TenantUsageEntry::sole();
        $this->assertSame(100, $entry->amount_cents);
        $this->assertSame('pending', $entry->status);
        $this->assertTrue($entry->bill_after->between(now()->addDays(29), now()->addDays(31)));

        // ripubblicare o modificare non costa
        $this->post(route('admin.promos.publish', [$tenant, $promo]));
        $this->assertSame(1, TenantUsageEntry::count());
    }

    public function test_the_more_you_publish_in_a_month_the_less_you_pay(): void
    {
        [$tenant] = $this->business(card: true);

        $prices = [];
        foreach (range(1, 22) as $i) {
            $prices[$i] = PayPerUse::priceCents($tenant, 'promo');
            PayPerUse::charge($tenant, 'promo', $i, 'Promo '.$i);
        }

        $this->assertSame(100, $prices[1]);
        $this->assertSame(100, $prices[5]);
        $this->assertSame(80, $prices[6]);
        $this->assertSame(80, $prices[20]);
        $this->assertSame(50, $prices[21]);
    }

    public function test_with_the_website_the_first_three_of_each_kind_are_included(): void
    {
        [$tenant] = $this->business();
        GeneratedSite::create(['tenant_id' => $tenant->id, 'status' => 'published', 'answers' => ['business_name' => 'Ferramenta Rossi']]);

        foreach (range(1, 3) as $i) {
            $this->assertSame(0, PayPerUse::priceCents($tenant, 'promo'));
            $this->assertTrue(PayPerUse::charge($tenant, 'promo', $i, 'Promo '.$i)); // incluso anche senza carta
        }
        $this->assertSame(100, PayPerUse::priceCents($tenant, 'promo'));
        $this->assertFalse(PayPerUse::charge($tenant, 'promo', 4, 'Promo 4')); // ora serve la carta
        $this->assertSame(0, PayPerUse::priceCents($tenant, 'product')); // i prodotti hanno i loro 3
        $this->assertSame(0, PayPerUse::pendingCents($tenant));
    }

    public function test_saving_the_card_on_stripe_publishes_what_was_waiting(): void
    {
        [$tenant, $user] = $this->business();
        $promo = $this->draft($tenant);
        $this->actingAs($user)->post(route('admin.promos.publish', [$tenant, $promo]));

        Http::fake([
            'api.stripe.com/v1/customers/cus_9' => Http::response(['id' => 'cus_9']),
            'api.stripe.com/v1/checkout/sessions/cs_setup*' => Http::response([
                'id' => 'cs_setup', 'mode' => 'setup', 'status' => 'complete', 'customer' => 'cus_9',
                'metadata' => ['tenant_id' => (string) $tenant->id, 'ppu_setup' => '1'],
                'setup_intent' => ['status' => 'succeeded', 'payment_method' => ['id' => 'pm_9', 'card' => ['brand' => 'visa', 'last4' => '4242']]],
            ]),
        ]);

        $this->get(route('admin.credit.return', [$tenant, 'session_id' => 'cs_setup']))
            ->assertRedirect(route('admin.promos.show', [$tenant, $promo]));

        $this->assertTrue(PayPerUse::hasCard($tenant->fresh()));
        $this->assertSame('published', $promo->fresh()->status);
        $this->assertSame(100, PayPerUse::pendingCents($tenant));
        Http::assertSent(fn ($r) => str_contains($r->url(), '/v1/customers/cus_9') && str_contains(urldecode($r->body()), 'invoice_settings[default_payment_method]=pm_9'));
    }

    public function test_a_setup_session_of_another_business_is_ignored(): void
    {
        [$tenant, $user] = $this->business();
        $other = Tenant::create(['name' => 'Altro', 'slug' => 'altro', 'type' => 'azienda', 'plan' => 'demo']);

        Http::fake(['api.stripe.com/*' => Http::response([
            'id' => 'cs_x', 'mode' => 'setup', 'status' => 'complete', 'customer' => 'cus_x',
            'metadata' => ['tenant_id' => (string) $other->id],
            'setup_intent' => ['status' => 'succeeded', 'payment_method' => ['id' => 'pm_x', 'card' => []]],
        ])]);

        $this->actingAs($user)->get(route('admin.credit.return', [$tenant, 'session_id' => 'cs_x']))->assertSessionHasErrors('credit');
        $this->assertFalse(PayPerUse::hasCard($tenant->fresh()));
    }

    public function test_after_a_month_everything_is_billed_at_once_and_a_failure_blocks_new_paid_publications(): void
    {
        [$tenant] = $this->business(card: true);
        foreach (range(1, 3) as $i) {
            PayPerUse::charge($tenant, 'promo', $i, 'Promo '.$i);
        }

        Http::preventStrayRequests();
        Http::fake([
            'api.stripe.com/v1/customers/cus_1' => Http::response(['invoice_settings' => ['default_payment_method' => 'pm_1']]),
            'api.stripe.com/v1/payment_intents' => Http::sequence()
                ->push(['id' => 'pi_1', 'status' => 'succeeded'])
                ->push(['error' => ['message' => 'Your card was declined.']], 402),
        ]);

        // non ancora un mese: niente
        $this->artisan('hub:bill-usage')->assertSuccessful();
        Http::assertNothingSent();

        $this->travel(31)->days();
        $this->artisan('hub:bill-usage')->assertSuccessful();

        Http::assertSent(fn ($r) => str_contains($r->url(), 'payment_intents') && (int) $r['amount'] === 300);
        $this->assertSame(3, TenantUsageEntry::where('status', 'billed')->where('payment_intent_id', 'pi_1')->count());

        // carta rifiutata
        PayPerUse::charge($tenant, 'promo', 10, 'Promo 10');
        $this->travel(31)->days();
        $this->artisan('hub:bill-usage')->assertSuccessful();

        $this->assertSame('failed', TenantUsageEntry::where('item_id', 10)->sole()->status);
        $this->assertFalse(PayPerUse::hasCard($tenant->fresh()));
        $this->assertFalse(PayPerUse::charge($tenant->fresh(), 'promo', 11, 'Promo 11'));

        // nuova carta: si riprova
        PayPerUse::saveCard($tenant->fresh(), 'cus_1', ['payment_method' => 'pm_2']);
        $this->assertSame('pending', TenantUsageEntry::where('item_id', 10)->sole()->status);
    }

    public function test_products_without_card_stay_hidden_until_the_card_is_added(): void
    {
        Http::fake();
        [$tenant, $user] = $this->business();

        $this->actingAs($user)->post(route('admin.products.store', $tenant), ['title' => 'Trapano', 'amount' => '89.90', 'published_to_site' => 1])
            ->assertRedirect(route('admin.credit.show', $tenant));

        $this->assertFalse(PayableService::sole()->published_to_site);
        $this->get(route('admin.credit.show', $tenant))->assertSee('«Trapano»', false);
    }

    public function test_switched_on_there_is_no_trial_and_no_subscription_page(): void
    {
        [$tenant, $user] = $this->business();
        $tenant->update(['trial_ends_at' => now()->subDay(), 'settings' => ['free_week' => true]]);

        $this->actingAs($user)->get(route('admin.billing.show', $tenant))->assertRedirect(route('admin.credit.show', $tenant));

        $this->get(route('admin.promos.index', $tenant))->assertOk()
            ->assertDontSee('Prova finita')->assertDontSee('Abbonamento')->assertSee('Pagamenti');

        // niente email «la prova è finita»
        \Illuminate\Support\Facades\Notification::fake();
        $this->artisan('hub:launch-offer-reminders')->assertSuccessful();
        \Illuminate\Support\Facades\Notification::assertNothingSent();

        // il sito web c'è per tutti, senza livelli
        $this->assertTrue($tenant->fresh()->hasPremiumFeatures());
    }

    public function test_a_refused_card_shows_a_short_warning(): void
    {
        [$tenant, $user] = $this->business(card: true);
        PayPerUse::markCardFailed($tenant);

        $this->actingAs($user)->get(route('admin.promos.index', $tenant->fresh()))->assertOk()->assertSee('Carta non passata');
    }

    public function test_old_subscribers_keep_their_subscription_page(): void
    {
        [$tenant, $user] = $this->business();
        $tenant->update(['subscription_status' => 'active']);

        $this->actingAs($user)->get(route('admin.billing.show', $tenant))->assertOk();
    }

    public function test_classified_ads_stay_free_and_old_subscribers_and_staff_do_not_pay(): void
    {
        [$tenant, $user] = $this->business();
        $this->actingAs($user)->post(route('admin.classifieds.store', $tenant), [
            'category' => 'servizi', 'title' => 'Ripetizioni', 'zone' => 'Corigliano', 'description' => 'Lezioni di matematica.', 'publish_now' => 1,
        ]);
        $this->assertSame(0, TenantUsageEntry::count());

        $tenant->update(['subscription_status' => 'active']);
        $this->assertTrue(PayPerUse::charge($tenant->fresh(), 'promo', 1, 'x'));

        $tenant->update(['subscription_status' => 'trialing']);
        $this->actingAs(User::factory()->create(['is_super_admin' => true]));
        $this->assertTrue(PayPerUse::charge($tenant->fresh(), 'promo', 2, 'y'));
        $this->assertSame(0, TenantUsageEntry::count());
    }

    private function makeSite(Tenant $tenant, User $user)
    {
        $this->mock(\App\Services\ContentModerator::class, fn ($m) => $m->shouldReceive('check')->andReturn(['allowed' => true, 'unavailable' => false, 'category' => 'ok', 'reason' => '']));
        $this->mock(\M35\HubSitebuilder\Services\SiteBuilderHeroGenerator::class, fn ($m) => $m->shouldReceive('generate', 'illustration')->andReturn(null));
        \Illuminate\Support\Facades\Notification::fake();

        return $this->actingAs($user)->post(route('admin.sitebuilder.generate', $tenant), [
            'tagline' => 'Tutto per la casa, a due passi da te', 'services' => 'Utensili, Vernici', 'cta_label' => 'Chiamaci', 'phone' => '333 123 4567',
        ]);
    }

    public function test_the_ai_website_costs_one_euro_once_and_waits_for_the_card(): void
    {
        [$tenant, $user] = $this->business();

        // Senza carta: il sito è salvato ma non online, e si chiede la carta.
        $this->makeSite($tenant, $user)->assertRedirect(route('admin.credit.show', $tenant));
        $site = GeneratedSite::sole();
        $this->assertSame('awaiting_card', $site->status);
        $this->assertNull($site->published_at);
        $this->assertSame(0, TenantUsageEntry::count());
        $this->assertSame(0, PayPerUse::includedLeft($tenant, 'promo')); // niente inclusi finché il sito non è online
        $this->get(route('admin.credit.show', $tenant))->assertOk()->assertSee('«Sito web di Ferramenta Rossi»', false)->assertSee('1 €');
        $this->get(route('admin.sitebuilder.show', $tenant))->assertOk()->assertSee('il credito non basta');
        auth()->logout();
        $this->get(route('site.public.show', $tenant))->assertNotFound();

        // Salvata la carta: il sito va online da solo, 1 € da addebitare tra un mese.
        Http::fake([
            'api.stripe.com/v1/customers/cus_9' => Http::response(['id' => 'cus_9']),
            'api.stripe.com/v1/checkout/sessions/cs_setup*' => Http::response([
                'id' => 'cs_setup', 'mode' => 'setup', 'status' => 'complete', 'customer' => 'cus_9',
                'metadata' => ['tenant_id' => (string) $tenant->id, 'ppu_setup' => '1'],
                'setup_intent' => ['status' => 'succeeded', 'payment_method' => ['id' => 'pm_9', 'card' => ['brand' => 'visa', 'last4' => '4242']]],
            ]),
        ]);
        $this->actingAs($user)->get(route('admin.credit.return', [$tenant, 'session_id' => 'cs_setup']))
            ->assertRedirect(route('admin.sitebuilder.show', $tenant));

        $site->refresh();
        $this->assertSame('published', $site->status);
        $this->assertNotNull($site->published_at);
        $entry = TenantUsageEntry::sole();
        $this->assertSame([100, 'site', 'pending'], [$entry->amount_cents, $entry->item_type, $entry->status]);
        $this->assertSame('Sito web «Sito web di Ferramenta Rossi»', $entry->description);
        $this->assertSame(3, PayPerUse::includedLeft($tenant, 'promo'));
        auth()->logout();
        $this->get(route('site.public.show', $tenant))->assertOk();

        // Modificarlo non costa più niente; e il sito non conta negli scaglioni del mese.
        $this->makeSite($tenant, $user)->assertRedirect(route('admin.sitebuilder.show', $tenant));
        $this->assertSame(1, TenantUsageEntry::count());
        $this->assertSame(0, PayPerUse::priceCents($tenant, 'promo')); // col sito le prime promo sono incluse
    }

    public function test_with_a_card_the_ai_website_goes_online_at_once(): void
    {
        [$tenant, $user] = $this->business(card: true);

        $this->makeSite($tenant, $user)->assertRedirect(route('admin.sitebuilder.show', $tenant));
        $this->assertSame('published', GeneratedSite::sole()->status);
        $this->assertSame(100, PayPerUse::pendingCents($tenant));
    }

    public function test_sites_already_online_and_staff_do_not_pay_for_the_website(): void
    {
        [$tenant, $user] = $this->business();
        GeneratedSite::create(['tenant_id' => $tenant->id, 'answers' => ['tagline' => 'Vecchio', 'services' => ['Utensili']], 'status' => 'published', 'published_at' => now()->subMonth()]);
        $this->makeSite($tenant, $user)->assertRedirect(route('admin.sitebuilder.show', $tenant));
        $this->assertSame(0, TenantUsageEntry::count());

        [$other] = [Tenant::create(['name' => 'Bar Sport', 'slug' => 'bar-sport', 'type' => 'azienda', 'plan' => 'demo', 'guest_verified_at' => now()])];
        $staff = User::factory()->create(['is_super_admin' => true]);
        $this->makeSite($other, $staff)->assertRedirect(route('admin.sitebuilder.show', $other));
        $this->assertSame('published', $other->fresh()->generatedSite->status);
        $this->assertSame(0, TenantUsageEntry::count());
    }
}
