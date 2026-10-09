<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantModuleCharge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use M35\HubPayments\Models\PayableOrder;
use M35\HubPayments\Models\PayableService;
use M35\HubPayments\Notifications\PaymentReceivedNotification;
use M35\HubPayments\Support\PaymentRecorder;
use M35\HubPayments\Support\TenantCommission;
use Tests\TestCase;

class HubChannelCommissionTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $slug = 'beauty'): Tenant
    {
        return Tenant::create([
            'name' => ucfirst($slug), 'slug' => $slug, 'type' => 'azienda', 'plan' => 'demo',
            'settings' => ['stripe' => ['secret_key' => Crypt::encryptString('sk_test_'.str_repeat('a', 24))]],
        ]);
    }

    private function item(Tenant $tenant, string $type = 'service', string $title = 'Piega', int $cents = 10000, array $extra = []): PayableService
    {
        return PayableService::create(array_merge([
            'tenant_id' => $tenant->id, 'type' => $type, 'title' => $title,
            'slug' => PayableService::uniqueSlugForTenant($tenant->id, $title),
            'amount_cents' => $cents, 'currency' => 'eur', 'status' => 'active', 'published_to_site' => true,
            'stripe_payment_link_id' => 'plink_'.md5($title), 'payment_url' => 'https://buy.stripe.com/test_'.md5($title),
        ], $extra));
    }

    private function stripeSession(PayableService $item, array $extra = []): array
    {
        return array_merge([
            'id' => 'cs_live_'.uniqid(), 'payment_status' => 'paid', 'payment_link' => $item->stripe_payment_link_id,
            'amount_total' => $item->amount_cents,
            'customer_details' => ['email' => 'c@example.com', 'name' => 'Cliente', 'phone' => '+39333'],
        ], $extra);
    }

    // ---- calcolo ----

    public function test_commission_defaults_to_zero_applies_only_to_the_hub_channel_and_never_exceeds_the_order(): void
    {
        $tenant = $this->tenant();
        $this->assertFalse(TenantCommission::isActive($tenant));
        $this->assertSame(0, TenantCommission::compute($tenant, 'hub', 10000));

        TenantCommission::store($tenant, 10, 50);
        $tenant->refresh();

        $this->assertTrue(TenantCommission::isActive($tenant));
        $this->assertSame(1050, TenantCommission::compute($tenant, 'hub', 10000)); // 10% + 0,50 €
        $this->assertSame(0, TenantCommission::compute($tenant, 'site', 10000));
        $this->assertSame(40, TenantCommission::compute($tenant, 'hub', 40));       // mai oltre l'ordine
        $this->assertSame(0, TenantCommission::compute($tenant, 'hub', 0));
    }

    // ---- registrazione dei pagamenti ----

    public function test_payment_link_with_hub_reference_is_recorded_on_the_hub_channel_with_zero_commission_by_default(): void
    {
        Notification::fake();
        $tenant = $this->tenant();
        $owner = User::factory()->create();
        $tenant->users()->attach($owner->id, ['role' => 'admin']);
        $service = $this->item($tenant);
        $session = $this->stripeSession($service, ['client_reference_id' => 'hub']);

        PaymentRecorder::record($tenant, $session);
        PaymentRecorder::record($tenant, $session); // riconsegna

        $order = PayableOrder::sole();
        $this->assertSame('hub', $order->channel);
        $this->assertSame('paid', $order->status);
        $this->assertSame(0, $order->commission_cents);
        $this->assertSame('Piega', $order->items[0]['title']);
        $this->assertSame('c@example.com', $order->customer_email);
        $this->assertSame('active', $service->fresh()->status); // il servizio resta in vendita
        Notification::assertSentToTimes($owner, PaymentReceivedNotification::class, 1);
    }

    public function test_commission_is_frozen_on_hub_orders_only_when_the_tenant_has_one(): void
    {
        Notification::fake();
        $tenant = $this->tenant();
        TenantCommission::store($tenant, 10, 0);
        $tenant->refresh();
        $service = $this->item($tenant);

        PaymentRecorder::record($tenant, $this->stripeSession($service, ['client_reference_id' => 'hub']));
        PaymentRecorder::record($tenant, $this->stripeSession($service));                                   // link condiviso dal tenant → canale sito

        $orders = PayableOrder::orderBy('id')->get();
        $this->assertSame(['hub', 'site'], $orders->pluck('channel')->all());
        $this->assertSame([1000, 0], $orders->pluck('commission_cents')->all());

        // un cambio successivo della commissione non tocca gli ordini già registrati
        TenantCommission::store($tenant->fresh(), 50, 0);
        $this->assertSame(1000, $orders->first()->fresh()->commission_cents);
    }

    public function test_cart_orders_and_quotes_never_carry_a_commission(): void
    {
        Notification::fake();
        $tenant = $this->tenant();
        TenantCommission::store($tenant, 10, 100);
        $tenant->refresh();

        $cart = PayableOrder::create([
            'tenant_id' => $tenant->id, 'channel' => 'site', 'status' => 'pending',
            'items' => [], 'currency' => 'eur', 'amount_cents' => 5000,
        ]);
        PaymentRecorder::record($tenant, ['id' => 'cs_1', 'payment_status' => 'paid', 'metadata' => ['hub_order_id' => (string) $cart->id], 'amount_total' => 5000]);

        $quote = $this->item($tenant, 'quote', 'Preventivo', 20000);
        PaymentRecorder::record($tenant, $this->stripeSession($quote, ['client_reference_id' => 'hub']));

        $this->assertSame(0, $cart->fresh()->commission_cents);
        $this->assertSame('site', PayableOrder::where('id', '!=', $cart->id)->sole()->channel);
        $this->assertSame(0, PayableOrder::sum('commission_cents'));
    }

    public function test_payment_link_of_another_tenant_is_ignored(): void
    {
        Notification::fake();
        $beauty = $this->tenant();
        $other = $this->tenant('altro');
        $service = $this->item($other);

        PaymentRecorder::record($beauty, $this->stripeSession($service, ['client_reference_id' => 'hub']));

        $this->assertSame(0, PayableOrder::count());
    }

    // ---- pagine pubbliche ----

    public function test_public_pages_use_the_hub_reference_and_correct_texts_for_products_and_services(): void
    {
        $tenant = $this->tenant();
        $service = $this->item($tenant, 'service', 'Piega');
        $product = $this->item($tenant, 'product', 'Shampoo', 1850);

        $this->get("/s/beauty/{$product->slug}")->assertOk()
            ->assertSee('Acquista ora')->assertDontSee('Prenota e paga ora')
            ->assertSee('Tutti i prodotti')->assertDontSee('Tutti i servizi')
            ->assertSee($product->payment_url.'?client_reference_id=hub', false);

        $this->get("/s/beauty/{$service->slug}")->assertOk()
            ->assertSee('Prenota e paga ora')->assertSee('Tutti i servizi')
            ->assertSee($service->payment_url.'?client_reference_id=hub', false);

        // la pagina incorporata nel sito del cliente non è canale hub
        $embed = $this->get("/client/beauty/services/{$service->slug}/embed")->assertOk();
        $embed->assertDontSee('client_reference_id', false)->assertSee($service->payment_url, false);

        $this->get('/s/beauty')->assertOk()
            ->assertSee('Servizi')->assertSee('Prodotti')->assertSee('Piega')->assertSee('Shampoo')->assertSee('Vedi e acquista');
    }

    // ---- addebito mensile ----

    public function test_monthly_command_moves_uncharged_hub_commissions_into_the_ledger_once(): void
    {
        $tenant = $this->tenant();
        $other = $this->tenant('altro');
        $make = fn (Tenant $t, string $channel, int $commission, string $paidAt, array $extra = []) => PayableOrder::create(array_merge([
            'tenant_id' => $t->id, 'channel' => $channel, 'status' => 'paid', 'items' => [], 'currency' => 'eur',
            'amount_cents' => 10000, 'commission_cents' => $commission, 'paid_at' => $paidAt,
        ], $extra));

        $make($tenant, 'hub', 500, '2026-09-10 10:00:00');
        $make($tenant, 'hub', 700, '2026-09-30 23:00:00');
        $make($tenant, 'hub', 900, '2026-10-02 10:00:00');        // altro mese
        $make($tenant, 'site', 0, '2026-09-11 10:00:00');
        $make($tenant, 'hub', 0, '2026-09-12 10:00:00');           // commissione zero
        $make($other, 'hub', 0, '2026-09-12 10:00:00');

        $this->artisan('hub:charge-commissions', ['--period' => '2026-09', '--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, TenantModuleCharge::count());

        $this->artisan('hub:charge-commissions', ['--period' => '2026-09'])->assertSuccessful();

        $charge = TenantModuleCharge::sole();
        $this->assertSame($tenant->id, $charge->tenant_id);
        $this->assertSame(1200, $charge->amount_cents);
        $this->assertSame('commission', $charge->charge_type);
        $this->assertSame('2026-09', $charge->period);
        $this->assertFalse($charge->paid);
        $this->assertSame(2, PayableOrder::where('commission_charge_id', $charge->id)->count());

        $this->artisan('hub:charge-commissions', ['--period' => '2026-09'])->assertSuccessful();   // seconda volta: niente doppioni
        $this->assertSame(1, TenantModuleCharge::count());

        $this->artisan('hub:charge-commissions', ['--period' => 'settembre'])->assertFailed();
    }

    // ---- pannelli ----

    public function test_tenant_orders_page_shows_channels_and_hides_commission_until_it_exists(): void
    {
        Notification::fake();
        $tenant = $this->tenant();
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['role' => 'admin']);
        $service = $this->item($tenant);

        PaymentRecorder::record($tenant, $this->stripeSession($service, ['client_reference_id' => 'hub']));

        $this->actingAs($user)->get(route('admin.orders.index', $tenant))
            ->assertOk()->assertSee('Da inm35.it')->assertSee('Piega')->assertSee('c@example.com')
            ->assertDontSee('Commissione maturata');

        TenantCommission::store($tenant, 10, 0);
        $this->actingAs($user)->get(route('admin.orders.index', $tenant->fresh()))->assertSee('Commissione maturata');
    }

    public function test_only_super_admins_see_and_set_commissions(): void
    {
        $tenant = $this->tenant();
        $member = User::factory()->create();
        $tenant->users()->attach($member->id, ['role' => 'admin']);
        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($member)->get(route('admin.commissions.index'))->assertForbidden();
        $this->actingAs($member)->put(route('admin.commissions.update', $tenant), ['percent' => 5, 'fixed' => 1])->assertForbidden();
        $this->assertFalse(TenantCommission::isActive($tenant->fresh()));

        $this->actingAs($admin)->get(route('admin.commissions.index'))->assertOk()->assertSee('Commissioni sulle vendite inm35.it');
        $this->actingAs($admin)->put(route('admin.commissions.update', $tenant), ['percent' => 7.5, 'fixed' => 0.5])
            ->assertRedirect(route('admin.commissions.index'));
        $this->actingAs($admin)->put(route('admin.commissions.update', $tenant), ['percent' => 120, 'fixed' => 0])->assertSessionHasErrors('percent');

        $tenant->refresh();
        $this->assertSame(7.5, TenantCommission::percent($tenant));
        $this->assertSame(50, TenantCommission::fixedCents($tenant));
    }
}
