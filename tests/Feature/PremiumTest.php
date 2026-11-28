<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantUsageEntry;
use App\Models\User;
use App\Support\PayPerUse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Premium: un tot al mese scalato dal credito, tutto illimitato, rinnovo da solo finché il cliente vuole.
 * Chi esce paga 0,80 € a pubblicazione per un mese, poi 1 €.
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
            'services.hub_billing.pay_per_use.premium' => ['enabled' => true, 'monthly_cents' => 1900, 'days' => 30, 'exit_cents' => 80, 'exit_days' => 30],
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

    public function test_premium_needs_credit_then_everything_is_free_for_a_month(): void
    {
        [$tenant, $user] = $this->business(1000);

        $this->actingAs($user)->post(route('admin.credit.premium', $tenant))->assertSessionHasErrors('credit');
        $this->assertFalse(PayPerUse::premiumActive($tenant->fresh()));

        PayPerUse::topUp($tenant, 1500, 'bonifico', 'manual-1');
        $this->post(route('admin.credit.premium', $tenant))->assertSessionHas('status');

        $tenant = $tenant->fresh();
        $this->assertTrue(PayPerUse::premiumActive($tenant));
        $this->assertSame(600, PayPerUse::creditCents($tenant));

        foreach (range(1, 8) as $i) {
            $this->assertSame('published', $this->publish($tenant, $user, 'Promo '.$i)->status);
        }
        $this->assertSame(600, PayPerUse::creditCents($tenant->fresh()));
        $this->assertSame(8, TenantUsageEntry::where('reason', 'premium')->count());
        $this->assertSame(0, PayPerUse::priceCents($tenant, PayPerUse::SITE));

        $this->get(route('admin.credit.show', $tenant))->assertOk()->assertSee('Premium attivo fino al')->assertSee('Non rinnovare');
    }

    public function test_it_renews_from_credit_and_stops_when_credit_ends_then_exit_price_then_normal(): void
    {
        [$tenant, $user] = $this->business(4000);
        $this->assertTrue(PayPerUse::startPremium($tenant));

        $this->travel(30)->days();
        $this->artisan('hub:renew-premium')->assertSuccessful();
        $this->assertTrue(PayPerUse::premiumActive($tenant->fresh()));
        $this->assertSame(200, PayPerUse::creditCents($tenant->fresh()));

        // al giro dopo il credito non basta: non si rinnova e finisce alla scadenza
        $this->travel(30)->days();
        $this->artisan('hub:renew-premium')->assertSuccessful();
        $this->travel(1)->days();
        $tenant = $tenant->fresh();
        $this->assertFalse(PayPerUse::premiumActive($tenant));
        $this->assertTrue(PayPerUse::premiumExitActive($tenant));
        $this->assertSame(80, PayPerUse::priceCents($tenant, 'promo'));

        $this->travel(31)->days();
        $this->assertSame(100, PayPerUse::priceCents($tenant->fresh(), 'promo'));
    }

    public function test_client_can_stop_and_resume_the_renewal(): void
    {
        [$tenant, $user] = $this->business(2000);
        $this->actingAs($user)->post(route('admin.credit.premium', $tenant));

        $this->post(route('admin.credit.premium.renewal', $tenant))->assertSessionHas('status');
        $this->assertFalse(PayPerUse::premium($tenant->fresh())['renew']);

        $this->travel(30)->days();
        $this->artisan('hub:renew-premium');
        $this->assertSame(100, PayPerUse::creditCents($tenant->fresh())); // non rinnovato

        $this->travel(-30)->days();
        $this->post(route('admin.credit.premium.renewal', $tenant), ['renew' => 1]);
        $this->assertTrue(PayPerUse::premium($tenant->fresh())['renew']);
    }
}
