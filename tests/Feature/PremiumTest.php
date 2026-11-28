<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantUsageEntry;
use App\Models\User;
use App\Support\Packages;
use App\Support\PayPerUse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Pacchetti Base / Premium / Business: dal credito, 1-3-6-12 mesi con sconto, tetto al mese (poi 0,50 €),
 * opzioni del sito incluse in Premium/Business, rinnovo da solo, cambio con rimborso, uscita a 0,80 € per un mese.
 */
class PremiumTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.hub_billing.pay_per_use.enabled' => true,
            'services.hub_billing.pay_per_use.card_enabled' => false,
            'services.hub_billing.pay_per_use.welcome_credit_cents' => 0,
            'services.hub_billing.pay_per_use.overdraft_cents' => 0,
        ]);
        Notification::fake();
    }

    /** @return array{0: Tenant, 1: User} */
    private function business(int $credit): array
    {
        $tenant = Tenant::create(['name' => 'SOS Phone', 'slug' => 'sos-phone', 'type' => 'azienda', 'plan' => 'demo', 'guest_verified_at' => now(),
            'settings' => ['ppu_credit' => ['cents' => $credit, 'welcome_at' => now()->toIso8601String()]]]);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'admin']);

        return [$tenant->fresh(), $user];
    }

    private function publish(Tenant $tenant, User $user, string $title)
    {
        $promo = $tenant->promos()->create(['title' => $title, 'slug' => \Illuminate\Support\Str::slug($title).'-'.uniqid(), 'status' => 'draft']);
        $this->actingAs($user)->post(route('admin.promos.publish', [$tenant, $promo]));

        return $promo->fresh();
    }

    public function test_prices_for_more_months_have_a_discount(): void
    {
        $this->assertSame(900, Packages::priceCents('base', 1));
        $this->assertSame(5130, Packages::priceCents('premium', 3));   // 57 € −10%
        $this->assertSame(9690, Packages::priceCents('premium', 6));   // 114 € −15%
        $this->assertSame(18240, Packages::priceCents('premium', 12)); // 228 € −20%
        $this->assertSame(3900, Packages::priceCents('business', 1));
    }

    public function test_base_includes_fifteen_a_month_then_fifty_cents_and_site_options_are_not_included(): void
    {
        [$tenant, $user] = $this->business(1000);

        $this->actingAs($user)->post(route('admin.credit.premium', $tenant), ['plan' => 'premium', 'months' => 1])->assertSessionHasErrors('credit');
        $this->post(route('admin.credit.premium', $tenant), ['plan' => 'base', 'months' => 1])->assertSessionHas('status');

        $tenant = $tenant->fresh();
        $this->assertSame(100, PayPerUse::creditCents($tenant));
        $this->assertSame('base', Packages::current($tenant)['key']);
        $this->assertSame(0, PayPerUse::priceCents($tenant, PayPerUse::SITE));
        $this->assertSame(100, PayPerUse::priceCents($tenant, 'addon_seo'));

        foreach (range(1, 15) as $i) {
            $this->assertSame('published', $this->publish($tenant, $user, 'Promo '.$i)->status);
        }
        $this->assertSame(0, Packages::itemsLeft($tenant));
        $this->assertSame(50, PayPerUse::priceCents($tenant, 'promo'));
        $this->assertSame('published', $this->publish($tenant, $user, 'Sedicesima')->status);
        $this->assertSame(50, PayPerUse::creditCents($tenant->fresh()));

        // il mese dopo (ancora nel pacchetto comprato per più mesi) il tetto riparte
        $this->travel(31)->days();
        $this->assertFalse(Packages::active($tenant->fresh())); // comprato per 1 mese: finito
    }

    public function test_premium_includes_site_options_and_two_languages(): void
    {
        [$tenant] = $this->business(2000);
        $this->assertTrue(Packages::buy($tenant, 'premium', 1));
        $tenant = $tenant->fresh();

        $this->assertSame(0, PayPerUse::priceCents($tenant, 'addon_seo'));
        $this->assertSame(0, PayPerUse::priceCents($tenant, 'lang_en'));
        $this->assertSame(50, Packages::itemsLeft($tenant));

    }

    public function test_multi_month_purchase_resets_the_monthly_cap(): void
    {
        [$tenant, $user] = $this->business(3000);
        $this->assertTrue(Packages::buy($tenant, 'base', 3));
        $tenant = $tenant->fresh();
        $this->assertSame(3000 - 2430, PayPerUse::creditCents($tenant));

        foreach (range(1, 15) as $i) {
            $this->publish($tenant, $user, 'P'.$i);
        }
        $this->assertSame(0, Packages::itemsLeft($tenant));

        $this->travel(31)->days();
        $this->assertTrue(Packages::active($tenant->fresh()));
        $this->assertSame(15, Packages::itemsLeft($tenant->fresh()));
    }

    public function test_switching_plan_refunds_the_unused_part(): void
    {
        [$tenant] = $this->business(6000);
        $this->assertTrue(Packages::buy($tenant, 'premium', 1)); // 19 €
        $this->travel(15)->days();

        $this->assertTrue(Packages::buy($tenant->fresh(), 'business', 1)); // 39 €, rimborso ~9,50 €
        $tenant = $tenant->fresh();
        $this->assertSame('business', Packages::current($tenant)['key']);
        $refund = (int) TenantUsageEntry::where('reason', 'premium_refund')->sole()->amount_cents;
        $this->assertGreaterThan(900, $refund);
        $this->assertLessThan(1000, $refund);
        $this->assertSame(6000 - 1900 + $refund - 3900, PayPerUse::creditCents($tenant));
    }

    public function test_it_renews_from_credit_then_exit_price_then_normal(): void
    {
        [$tenant] = $this->business(4000);
        $this->assertTrue(Packages::buy($tenant, 'premium', 1));

        $this->travel(30)->days();
        $this->artisan('hub:renew-premium')->assertSuccessful();
        $this->assertTrue(Packages::active($tenant->fresh()));
        $this->assertSame(200, PayPerUse::creditCents($tenant->fresh()));

        $this->travel(30)->days();
        $this->artisan('hub:renew-premium')->assertSuccessful(); // credito non basta
        $this->travel(1)->days();
        $tenant = $tenant->fresh();
        $this->assertFalse(Packages::active($tenant));
        $this->assertTrue(Packages::exitActive($tenant));
        $this->assertSame(80, PayPerUse::priceCents($tenant, 'promo'));

        $this->travel(31)->days();
        $this->assertSame(100, PayPerUse::priceCents($tenant->fresh(), 'promo'));
    }

    public function test_client_can_stop_and_resume_the_renewal_and_sees_the_plans(): void
    {
        [$tenant, $user] = $this->business(2000);
        $this->actingAs($user)->get(route('admin.credit.show', $tenant))->assertOk()->assertSee('Base')->assertSee('Premium')->assertSee('Business')->assertSee('−20%');

        $this->post(route('admin.credit.premium', $tenant), ['plan' => 'premium', 'months' => 1]);
        $this->get(route('admin.credit.show', $tenant))->assertSee('Premium attivo fino al')->assertSee('Non rinnovare');

        $this->post(route('admin.credit.premium.renewal', $tenant))->assertSessionHas('status');
        $this->assertFalse(Packages::state($tenant->fresh())['renew']);

        $this->post(route('admin.credit.premium.renewal', $tenant), ['renew' => 1]);
        $this->assertTrue(Packages::state($tenant->fresh())['renew']);
    }
}
