<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use M35\HubPayments\Models\PayableOrder;
use M35\HubPayments\Support\TenantConnect;
use Tests\TestCase;

class ResetConnectTest extends TestCase
{
    use RefreshDatabase;

    private function linked(string $slug, array $extra = []): Tenant
    {
        return Tenant::create(['name' => ucfirst($slug), 'slug' => $slug, 'type' => 'azienda', 'plan' => 'demo', 'settings' => array_merge([
            'connect' => ['account_id' => 'acct_'.$slug, 'charges_enabled' => true, 'payouts_enabled' => true],
            'seller_terms' => ['version' => '2026-10-15', 'accepted_at' => now()->toIso8601String()],
        ], $extra)]);
    }

    public function test_it_clears_the_connect_link_but_keeps_the_accepted_terms(): void
    {
        $tenant = $this->linked('beauty');

        $this->artisan('hub:reset-connect', ['slug' => 'beauty'])->assertExitCode(0);

        $tenant->refresh();
        $this->assertSame('none', TenantConnect::state($tenant));
        $this->assertSame('2026-10-15', $tenant->settings['seller_terms']['version']);
    }

    public function test_it_refuses_while_protected_money_is_still_held_unless_forced(): void
    {
        $tenant = $this->linked('beauty');
        PayableOrder::create(['tenant_id' => $tenant->id, 'channel' => 'hub', 'flow' => 'protected', 'status' => 'paid', 'payout_status' => 'held', 'items' => [], 'amount_cents' => 1000, 'paid_at' => now()]);

        $this->artisan('hub:reset-connect', ['slug' => 'beauty'])->assertExitCode(1);
        $this->assertSame('ready', TenantConnect::state($tenant->fresh()));

        $this->artisan('hub:reset-connect', ['slug' => 'beauty', '--force' => true])->assertExitCode(0);
        $this->assertSame('none', TenantConnect::state($tenant->fresh()));
    }

    public function test_all_and_dry_run(): void
    {
        $a = $this->linked('uno');
        $b = $this->linked('due');

        $this->artisan('hub:reset-connect', ['--all' => true, '--dry-run' => true])->assertExitCode(0);
        $this->assertSame('ready', TenantConnect::state($a->fresh()));

        $this->artisan('hub:reset-connect', ['--all' => true])->assertExitCode(0);
        $this->assertSame('none', TenantConnect::state($a->fresh()));
        $this->assertSame('none', TenantConnect::state($b->fresh()));
    }

    public function test_it_needs_a_target(): void
    {
        $this->artisan('hub:reset-connect')->assertExitCode(1);
    }
}
