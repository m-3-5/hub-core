<?php

namespace Tests\Feature;

use App\Models\Promo;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantOverview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use M35\HubPayments\Models\PayableOrder;
use M35\HubPayments\Models\PayableService;
use Tests\TestCase;

class ClientCardsDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_super_admin' => true]);
    }

    private function tenant(string $name, string $slug, array $extra = []): Tenant
    {
        return Tenant::create(array_merge(['name' => $name, 'slug' => $slug, 'type' => 'azienda', 'plan' => 'demo'], $extra));
    }

    public function test_dashboard_shows_one_card_per_client_with_what_is_active(): void
    {
        $beauty = $this->tenant('Beauty of Image', 'beauty', ['subscription_status' => 'active']);
        $this->tenant('Mario Rossi', 'mario', ['type' => 'privato', 'subscription_status' => 'free']);

        PayableService::create(['tenant_id' => $beauty->id, 'type' => 'service', 'title' => 'Trattamento', 'slug' => 'trattamento', 'amount_cents' => 5000, 'status' => 'active']);
        PayableOrder::create(['tenant_id' => $beauty->id, 'channel' => 'hub', 'flow' => 'protected', 'status' => 'paid', 'payout_status' => 'held', 'items' => [], 'amount_cents' => 2500, 'paid_at' => now()]);

        $this->actingAs($this->admin())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Beauty of Image')
            ->assertSee('Mario Rossi')
            ->assertSee('Abbonamento attivo')
            ->assertSee('Gratis')
            ->assertSee(route('admin.tenants.show', $beauty), false);
    }

    public function test_client_page_gathers_all_the_work_of_one_client(): void
    {
        $beauty = $this->tenant('Beauty of Image', 'beauty', ['subscription_status' => 'active']);
        Promo::create(['tenant_id' => $beauty->id, 'title' => 'Sconto autunno', 'slug' => 'sconto-autunno', 'status' => 'published', 'always_active' => true, 'body' => 'x']);
        PayableService::create(['tenant_id' => $beauty->id, 'type' => 'product', 'title' => 'Siero viso', 'slug' => 'siero', 'amount_cents' => 3200, 'status' => 'active']);
        PayableOrder::create(['tenant_id' => $beauty->id, 'channel' => 'hub', 'flow' => 'protected', 'status' => 'paid', 'payout_status' => 'held', 'items' => [['title' => 'Siero viso']], 'amount_cents' => 3200, 'customer_name' => 'Anna', 'paid_at' => now()]);

        $this->actingAs($this->admin())->get(route('admin.tenants.show', $beauty))
            ->assertOk()
            ->assertSee('Beauty of Image')
            ->assertSee('Sconto autunno')
            ->assertSee('Siero viso')
            ->assertSee('32,00 €')
            ->assertSee('Trattenuti fino alla consegna')
            ->assertSee('Anna')
            ->assertSee(route('app.home', $beauty), false);
    }

    public function test_client_page_is_only_for_super_admins(): void
    {
        $tenant = $this->tenant('Beauty', 'beauty');
        $user = User::factory()->create();
        $user->tenants()->attach($tenant);

        $this->actingAs($user)->get(route('admin.tenants.show', $tenant))->assertForbidden();
    }

    public function test_summary_counts_are_per_client(): void
    {
        $a = $this->tenant('A', 'a');
        $b = $this->tenant('B', 'b');
        PayableOrder::create(['tenant_id' => $a->id, 'channel' => 'hub', 'flow' => 'protected', 'status' => 'paid', 'payout_status' => 'frozen', 'items' => [], 'amount_cents' => 1000, 'paid_at' => now()]);
        PayableOrder::create(['tenant_id' => $a->id, 'channel' => 'site', 'flow' => 'direct', 'status' => 'pending', 'items' => [], 'amount_cents' => 900]);

        $s = TenantOverview::summaries(collect([$a, $b]));

        $this->assertSame(1, $s[$a->id]['orders_count']);
        $this->assertSame(1000, $s[$a->id]['orders_total_cents']);
        $this->assertSame(1, $s[$a->id]['frozen_count']);
        $this->assertSame(0, $s[$b->id]['orders_count']);
    }

    public function test_the_clients_own_home_shows_their_work_summary(): void
    {
        $tenant = $this->tenant('Beauty', 'beauty', ['subscription_status' => 'active']);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant);

        $this->actingAs($user)->get(route('app.home', $tenant))->assertOk()->assertSee('Il tuo lavoro in breve')->assertSee('Promo attive');
    }
}
