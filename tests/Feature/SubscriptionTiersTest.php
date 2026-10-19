<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Support\HubModules;
use App\Support\TenantPromoQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use M35\HubPayments\Support\TenantServiceQuota;
use Tests\TestCase;

/** Un solo abbonamento, due livelli: Silver e Premium. */
class SubscriptionTiersTest extends TestCase
{
    use RefreshDatabase;

    private string $whsec;

    protected function setUp(): void
    {
        parent::setUp();

        $this->whsec = 'whsec'.'_'.str_repeat('e', 32);
        config([
            'services.hub_billing.secret_key' => 'sk_test_'.str_repeat('e', 24),
            'services.hub_billing.webhook_secret' => $this->whsec,
            // Cifre fisse: il .env locale può avere valori di prova diversi dal listino.
            'services.hub_billing.free_days' => 30, 'services.hub_billing.trial_days' => 90, 'services.hub_billing.launch_offer_price_eur' => 1,
            'services.hub_billing.monthly_price_eur' => 9, 'services.hub_billing.annual_price_eur' => 79,
            'services.hub_billing.tiers.silver.monthly' => 9, 'services.hub_billing.tiers.silver.annual' => 79,
            'services.hub_billing.tiers.premium.monthly' => 19, 'services.hub_billing.tiers.premium.annual' => 169,
        ]);
    }

    /** @return array{0: Tenant, 1: User} */
    private function tenant(array $attrs = []): array
    {
        $tenant = Tenant::create(array_merge(['name' => 'Salone Anna', 'slug' => 'salone-anna', 'type' => 'azienda', 'plan' => 'demo', 'guest_verified_at' => now(), 'subscription_status' => 'active'], $attrs));
        $user = User::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'admin']);

        return [$tenant, $user];
    }

    private function website(Tenant $tenant): array
    {
        return collect(app(HubModules::class)->forTenant($tenant))->firstWhere('key', 'website');
    }

    public function test_the_price_list_is_one_subscription_with_two_levels_and_no_per_module_costs(): void
    {
        $tiers = config('services.hub_billing.tiers');

        $this->assertGreaterThan($tiers['silver']['monthly'], $tiers['premium']['monthly']);
        $this->assertLessThan($tiers['silver']['monthly'] * 12, $tiers['silver']['annual']);
        $this->assertLessThan($tiers['premium']['monthly'] * 12, $tiers['premium']['annual']);
        $this->assertGreaterThan($tiers['silver']['promo_quota'], $tiers['premium']['promo_quota']);
        $this->assertSame(0, config('module_pricing.promo.activation_cents') + config('module_pricing.promo.monthly_cents') + config('module_pricing.servizi.activation_cents') + config('module_pricing.servizi.monthly_cents'));
    }
    public function test_the_website_module_is_premium_but_everybody_has_it_during_the_trial(): void
    {
        [$silver] = $this->tenant(['slug' => 'a', 'subscription_status' => 'active']);
        [$premium] = $this->tenant(['slug' => 'b', 'subscription_status' => 'active', 'settings' => ['tier' => 'premium']]);
        [$trial] = $this->tenant(['slug' => 'c', 'subscription_status' => 'trialing', 'trial_ends_at' => now()->addDays(10)]);

        $this->assertFalse($this->website($silver)['active']);
        $this->assertSame('Premium', $this->website($silver)['badge']);
        $this->assertTrue($this->website($premium)['active']);
        $this->assertTrue($this->website($trial)['active']);
    }

    public function test_a_silver_customer_cannot_use_the_site_builder_and_is_sent_to_the_upgrade(): void
    {
        [$tenant, $user] = $this->tenant();

        $this->actingAs($user)->get(route('admin.sitebuilder.show', $tenant))->assertRedirect(route('admin.billing.show', $tenant));
        $this->actingAs($user)->post(route('admin.sitebuilder.generate', $tenant), ['tagline' => 'x', 'services' => 'y'])->assertRedirect(route('admin.billing.show', $tenant));

        $tenant->update(['settings' => ['tier' => 'premium']]);
        $this->actingAs($user)->get(route('admin.sitebuilder.show', $tenant))->assertOk();
    }

    public function test_quotas_follow_the_tier(): void
    {
        [$silver] = $this->tenant(['slug' => 'a']);
        [$premium] = $this->tenant(['slug' => 'b', 'settings' => ['tier' => 'premium']]);

        $this->assertSame(20, TenantPromoQuota::includedLimit($silver));
        $this->assertSame(100, TenantPromoQuota::includedLimit($premium));
        $this->assertSame(20, TenantServiceQuota::includedLimit($silver));
        $this->assertSame(100, TenantServiceQuota::includedLimit($premium));
    }

    public function test_checkout_uses_the_price_of_the_chosen_tier_and_remembers_it(): void
    {
        [$tenant, $user] = $this->tenant(['subscription_status' => 'trialing', 'trial_ends_at' => now()->subDay()]);

        Http::fake([
            'api.stripe.com/v1/customers' => Http::response(['id' => 'cus_1']),
            'api.stripe.com/v1/products*' => Http::response(['id' => 'prod_1', 'data' => []]),
            'api.stripe.com/v1/prices*' => Http::response(['id' => 'price_1', 'data' => []]),
            'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_1']),
        ]);

        $this->actingAs($user)->post(route('admin.billing.checkout', $tenant), ['interval' => 'month', 'tier' => 'premium'])
            ->assertRedirect('https://checkout.stripe.com/c/pay/cs_1');

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/v1/prices') && $r->method() === 'POST' && (int) $r->data()['unit_amount'] === 1900 && $r->data()['recurring[interval]'] === 'month');
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/v1/checkout/sessions') && ($r->data()['metadata[tier]'] ?? null) === 'premium');

        $this->actingAs($user)->post(route('admin.billing.checkout', $tenant), ['interval' => 'year', 'tier' => 'silver'])->assertRedirect();
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/v1/prices') && $r->method() === 'POST' && (int) $r->data()['unit_amount'] === 7900 && $r->data()['recurring[interval]'] === 'year');

        $this->actingAs($user)->post(route('admin.billing.checkout', $tenant), ['interval' => 'month', 'tier' => 'gold'])->assertSessionHasErrors('tier');
    }

    public function test_payment_completed_saves_the_tier_the_customer_bought(): void
    {
        [$tenant] = $this->tenant(['subscription_status' => 'trialing', 'trial_ends_at' => now()->subDay()]);

        $payload = json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => [
            'id' => 'cs_1', 'customer' => 'cus_1', 'subscription' => 'sub_1',
            'metadata' => ['tenant_id' => (string) $tenant->id, 'interval' => 'month', 'tier' => 'premium'],
        ]]]);
        $t = time();

        $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$t},v1=".hash_hmac('sha256', $t.'.'.$payload, $this->whsec),
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk();

        $this->assertSame('active', $tenant->fresh()->subscription_status);
        $this->assertSame('premium', $tenant->fresh()->tier());
        $this->assertTrue($tenant->fresh()->hasPremiumFeatures());
    }

    public function test_pricing_page_shows_three_plans_and_no_per_module_sums(): void
    {
        $html = $this->get(route('pricing.show'))->assertOk()
            ->assertSee('Un solo abbonamento, due livelli')
            ->assertSee('Silver')->assertSee('Premium')
            ->assertSee('€9')->assertSee('€19')
            ->assertDontSee('Attivazione (coperta dall\'offerta)')
            ->getContent();

        $this->assertStringContainsString('30 giorni gratis', $html);
        $this->assertStringContainsString('dopo 120 giorni', $html);
    }
}
