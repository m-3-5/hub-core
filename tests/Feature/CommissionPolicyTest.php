<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantModuleCharge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use M35\HubPayments\Models\PayableOrder;
use M35\HubPayments\Models\PayableService;
use M35\HubPayments\Support\OrderSummary;
use M35\HubPayments\Support\PaymentRecorder;
use M35\HubPayments\Support\TenantCommission;
use Tests\TestCase;

/** Commissione a carico dell'azienda: calcolo sul solo canale hub, mai addebitata in automatico, gestita dal super admin. */
class CommissionPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $slug = 'beauty'): Tenant
    {
        return Tenant::create([
            'name' => ucfirst($slug), 'slug' => $slug, 'type' => 'azienda', 'plan' => 'demo',
            'settings' => ['stripe' => ['secret_key' => Crypt::encryptString('sk_test_'.str_repeat('a', 24))]],
        ]);
    }

    private function item(Tenant $tenant, string $type, string $title, int $cents): PayableService
    {
        return PayableService::create([
            'tenant_id' => $tenant->id, 'type' => $type, 'title' => $title,
            'slug' => PayableService::uniqueSlugForTenant($tenant->id, $title),
            'amount_cents' => $cents, 'currency' => 'eur', 'status' => 'active', 'published_to_site' => true,
            'stripe_payment_link_id' => 'plink_'.md5($title), 'payment_url' => 'https://buy.stripe.com/test_'.md5($title),
        ]);
    }

    private function paidSession(PayableService $item, array $extra = []): array
    {
        return array_merge([
            'id' => 'cs_live_'.uniqid(), 'payment_status' => 'paid', 'payment_link' => $item->stripe_payment_link_id,
            'amount_total' => $item->amount_cents,
            'customer_details' => ['email' => 'c@example.com', 'name' => 'Cliente'],
        ], $extra);
    }

    public function test_five_percent_is_recorded_on_hub_orders_and_stays_zero_on_every_site_order(): void
    {
        Notification::fake();
        $tenant = $this->tenant();
        TenantCommission::store($tenant, 5, 0);
        $tenant->refresh();
        $service = $this->item($tenant, 'service', 'Piega', 10000);
        $product = $this->item($tenant, 'product', 'Shampoo', 2000);
        $quote = $this->item($tenant, 'quote', 'Preventivo', 30000);

        // canale hub: pagina pubblica di inm35.it
        PaymentRecorder::record($tenant, $this->paidSession($service, ['client_reference_id' => 'hub']));

        // canale sito: carrello, preventivo, link condiviso dal titolare
        $cart = PayableOrder::create(['tenant_id' => $tenant->id, 'channel' => 'site', 'status' => 'pending', 'items' => [], 'currency' => 'eur', 'amount_cents' => 4000]);
        PaymentRecorder::record($tenant, ['id' => 'cs_cart', 'payment_status' => 'paid', 'metadata' => ['hub_order_id' => (string) $cart->id], 'amount_total' => 4000]);
        PaymentRecorder::record($tenant, $this->paidSession($quote));
        PaymentRecorder::record($tenant, $this->paidSession($product));

        $byChannel = PayableOrder::all()->groupBy('channel');

        $this->assertSame([500], $byChannel['hub']->pluck('commission_cents')->all()); // 5% di 100,00 €
        $this->assertCount(3, $byChannel['site']);
        $this->assertSame([0, 0, 0], $byChannel['site']->pluck('commission_cents')->all());
    }

    public function test_commissions_are_never_charged_automatically_but_other_charges_still_are(): void
    {
        Http::fake([
            'api.stripe.com/v1/customers/*' => Http::response(['invoice_settings' => ['default_payment_method' => 'pm_1']]),
            'api.stripe.com/v1/payment_intents' => Http::response(['id' => 'pi_1']),
        ]);
        config(['services.hub_billing.secret_key' => 'sk_test_'.str_repeat('h', 24)]);
        $tenant = $this->tenant();
        $tenant->forceFill(['stripe_customer_id' => 'cus_1'])->save();

        $extra = TenantModuleCharge::create(['tenant_id' => $tenant->id, 'module' => 'servizi', 'charge_type' => 'extra_item', 'period' => '2026-10', 'description' => 'Extra', 'amount_cents' => 1200, 'paid' => false]);
        $commission = TenantModuleCharge::create(['tenant_id' => $tenant->id, 'module' => 'servizi', 'charge_type' => 'commission', 'period' => '2026-09', 'description' => 'Commissione', 'amount_cents' => 5000, 'paid' => false]);

        $this->artisan('hub:charge-pending-module-charges')->assertSuccessful();

        $this->assertTrue($extra->fresh()->paid);
        $this->assertFalse($commission->fresh()->paid);
        Http::assertSentCount(2); // lettura cliente + un solo addebito: quello dell'extra
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'payment_intents') && str_contains($r->body(), '5000'));
    }

    public function test_only_super_admins_can_mark_commissions_paid_or_delete_them_and_the_owner_sees_them(): void
    {
        $tenant = $this->tenant();
        $owner = User::factory()->create();
        $tenant->users()->attach($owner->id, ['role' => 'admin']);
        $admin = User::factory()->create(['is_super_admin' => true]);
        $commission = TenantModuleCharge::create(['tenant_id' => $tenant->id, 'module' => 'servizi', 'charge_type' => 'commission', 'period' => '2026-09', 'description' => 'Commissione vendite', 'amount_cents' => 5000, 'paid' => false]);
        $extra = TenantModuleCharge::create(['tenant_id' => $tenant->id, 'module' => 'servizi', 'charge_type' => 'extra_item', 'period' => '2026-09', 'description' => 'Extra', 'amount_cents' => 1200, 'paid' => false]);

        // il titolare la vede nel registro ma non può segnarla pagata né eliminarla
        $this->actingAs($owner)->get(route('admin.module-billing.show', $tenant))->assertOk()
            ->assertSee('Commissione')->assertSee('da incassare da M 3.5');
        $this->actingAs($owner)->post(route('admin.module-billing.toggle-paid', [$tenant, $commission]))->assertForbidden();
        $this->actingAs($owner)->delete(route('admin.module-billing.destroy', [$tenant, $commission]))->assertForbidden();
        $this->assertFalse($commission->fresh()->paid);
        $this->assertNotNull(TenantModuleCharge::find($commission->id));

        // le altre voci restano come prima
        $this->actingAs($owner)->post(route('admin.module-billing.toggle-paid', [$tenant, $extra]))->assertRedirect();
        $this->assertTrue($extra->fresh()->paid);

        // il super admin la segna pagata
        $this->actingAs($admin)->post(route('admin.module-billing.toggle-paid', [$tenant, $commission]))->assertRedirect();
        $this->assertTrue($commission->fresh()->paid);
        $this->assertNotNull($commission->fresh()->paid_at);
    }

    public function test_zero_commission_shows_nothing_and_the_amount_still_to_pay_shrinks_when_marked_paid(): void
    {
        Notification::fake();
        $tenant = $this->tenant();
        $owner = User::factory()->create();
        $tenant->users()->attach($owner->id, ['role' => 'admin']);
        $service = $this->item($tenant, 'service', 'Piega', 10000);
        $period = now()->format('Y-m');

        // commissione zero: nessuna colonna, nessuna riga di commissione, nessuna voce nel registro
        PaymentRecorder::record($tenant, $this->paidSession($service, ['client_reference_id' => 'hub']));
        $this->actingAs($owner)->get(route('admin.orders.index', $tenant))->assertOk()
            ->assertDontSee('Commissione')->assertDontSee('ancora da pagare');
        $this->artisan('hub:charge-commissions', ['--period' => $period])->assertSuccessful();
        $this->assertSame(0, TenantModuleCharge::count());

        // 5%: la colonna compare per il titolare e la quota ancora da pagare scende quando la voce risulta pagata
        TenantCommission::store($tenant->fresh(), 5, 0);
        PaymentRecorder::record($tenant->fresh(), $this->paidSession($service, ['client_reference_id' => 'hub']));
        $this->actingAs($owner)->get(route('admin.orders.index', $tenant->fresh()))->assertOk()
            ->assertSee('Commissione maturata')->assertSee('ancora da pagare')->assertSee('5,00 €');

        $this->artisan('hub:charge-commissions', ['--period' => $period])->assertSuccessful();
        $charge = TenantModuleCharge::sole();
        $this->assertSame(500, $charge->amount_cents);
        $this->assertSame(500, OrderSummary::byChannel($tenant->fresh())['hub']['unpaid_cents']);

        $charge->update(['paid' => true, 'paid_at' => now()]);
        $this->assertSame(0, OrderSummary::byChannel($tenant->fresh())['hub']['unpaid_cents']);
        $this->assertSame(500, OrderSummary::byChannel($tenant->fresh())['hub']['commission_cents']);
    }
}
