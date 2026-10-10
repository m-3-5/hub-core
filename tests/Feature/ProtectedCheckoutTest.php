<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantModuleCharge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use M35\HubPayments\Models\PayableOrder;
use M35\HubPayments\Models\PayableService;
use M35\HubPayments\Notifications\BuyerOrderNotification;
use M35\HubPayments\Notifications\PaymentReceivedNotification;
use M35\HubPayments\Support\PaymentRecorder;
use M35\HubPayments\Support\SellerTerms;
use M35\HubPayments\Support\TenantCommission;
use M35\HubPayments\Support\TenantConnect;
use Tests\TestCase;

/** Pagamenti protetti, pezzo 2: la cassa sulle pagine di inm35.it incassa su Hub Core e trattiene i soldi fino alla consegna. */
class ProtectedCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private string $whsec = 'whsec_platform_test';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.hub_billing.secret_key' => 'sk_test_'.str_repeat('p', 24),
            'services.hub_billing.webhook_secret' => $this->whsec,
        ]);
    }

    private function seller(bool $connected = true, bool $accepted = true): Tenant
    {
        $tenant = Tenant::create(['name' => 'Beauty', 'slug' => 'beauty', 'type' => 'azienda', 'plan' => 'demo', 'primary_color' => '#aa3377']);

        if ($connected) {
            TenantConnect::setAccount($tenant, 'acct_seller');
            TenantConnect::sync($tenant->fresh(), ['id' => 'acct_seller', 'charges_enabled' => true, 'payouts_enabled' => true, 'details_submitted' => true]);
        }

        if ($accepted) {
            SellerTerms::accept($tenant->fresh(), null, '127.0.0.1');
        }

        return $tenant->fresh();
    }

    private function item(Tenant $tenant, string $type = 'product', string $title = 'Siero viso', int $cents = 4500): PayableService
    {
        return PayableService::create([
            'tenant_id' => $tenant->id, 'type' => $type, 'title' => $title, 'slug' => PayableService::uniqueSlugForTenant($tenant->id, $title),
            'amount_cents' => $cents, 'currency' => 'eur', 'status' => 'active', 'published_to_site' => true,
            'stripe_payment_link_id' => 'plink_x', 'payment_url' => 'https://buy.stripe.com/test_abc',
        ]);
    }

    private function fakeCheckout(): void
    {
        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1'])]);
    }

    private function platformWebhook(array $session): \Illuminate\Testing\TestResponse
    {
        $payload = json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => $session]]);
        $t = time();

        return $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$t},v1=".hash_hmac('sha256', $t.'.'.$payload, $this->whsec),
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }

    private function paidSession(PayableOrder $order, array $extra = []): array
    {
        return $extra + [
            'id' => 'cs_test_1', 'payment_status' => 'paid', 'payment_intent' => 'pi_123', 'amount_total' => $order->amount_cents,
            'client_reference_id' => (string) $order->id,
            'metadata' => ['hub_order_id' => (string) $order->id, 'hub_tenant' => 'beauty', 'hub_flow' => 'protected'],
            'customer_details' => ['email' => 'compratore@example.com', 'name' => 'Anna Verdi', 'phone' => '+39333'],
        ];
    }

    // ---- pagina pubblica e avvio acquisto ----

    public function test_the_public_page_offers_the_protected_purchase_when_the_seller_is_connected(): void
    {
        $tenant = $this->seller();
        $item = $this->item($tenant);

        $this->get(route('services.public.show', [$tenant, $item]))->assertOk()
            ->assertSee(route('services.public.buy', [$tenant, $item]), false)
            ->assertSee('Pagamento protetto da Hub Core')->assertSee('name="quantity"', false);
    }

    public function test_without_connection_or_acceptance_the_page_keeps_the_usual_payment_link(): void
    {
        foreach ([$this->seller(connected: false), ] as $tenant) {
            $item = $this->item($tenant);
            $this->get(route('services.public.show', [$tenant, $item]))->assertOk()
                ->assertSee('https://buy.stripe.com/test_abc?client_reference_id=hub', false)->assertDontSee('Pagamento protetto da Hub Core');
        }
    }

    public function test_a_connected_seller_who_has_not_accepted_the_terms_cannot_sell_protected(): void
    {
        $tenant = $this->seller(connected: true, accepted: false);
        $item = $this->item($tenant);

        $this->get(route('services.public.show', [$tenant, $item]))->assertDontSee(route('services.public.buy', [$tenant, $item]), false);
        Http::fake();

        $this->post(route('services.public.buy', [$tenant, $item]))->assertRedirect('https://buy.stripe.com/test_abc?client_reference_id=hub');
        Http::assertNothingSent();
        $this->assertSame(0, PayableOrder::count());
    }

    public function test_buying_creates_a_held_order_and_a_checkout_on_the_platform_account(): void
    {
        $tenant = $this->seller();
        $item = $this->item($tenant, cents: 4500);
        $this->fakeCheckout();

        $this->post(route('services.public.buy', [$tenant, $item]), ['quantity' => 2, 'customer_email' => 'compratore@example.com'])
            ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_1');

        $order = PayableOrder::sole();
        $this->assertSame('protected', $order->flow);
        $this->assertSame('hub', $order->channel);
        $this->assertSame('pending', $order->status);
        $this->assertSame(9000, $order->amount_cents);
        $this->assertSame('acct_seller', $order->connect_account_id);
        $this->assertSame('cs_test_1', $order->stripe_session_id);
        $this->assertSame(40, strlen($order->buyer_token));

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/v1/checkout/sessions')
            && $r->hasHeader('Authorization', 'Bearer sk_test_'.str_repeat('p', 24))
            && $r['mode'] === 'payment' && (int) $r['line_items[0][price_data][unit_amount]'] === 4500 && (int) $r['line_items[0][quantity]'] === 2
            && $r['line_items[0][price_data][currency]'] === 'eur' && $r['line_items[0][price_data][product_data][name]'] === 'Siero viso'
            && $r['metadata[hub_flow]'] === 'protected' && $r['metadata[hub_tenant]'] === 'beauty' && (string) $r['metadata[hub_order_id]'] === (string) $order->id
            && $r['payment_intent_data[transfer_group]'] === 'order_'.$order->id && $r['customer_email'] === 'compratore@example.com'
            && $r['success_url'] === route('protected.order.show', $order->buyer_token).'?pagato=1'
            && $r['cancel_url'] === route('services.public.show', [$tenant, $item]));
    }

    public function test_the_price_comes_from_the_hub_and_services_are_bought_one_at_a_time(): void
    {
        $tenant = $this->seller();
        $service = $this->item($tenant, 'service', 'Piega', 3000);
        $this->fakeCheckout();

        $this->post(route('services.public.buy', [$tenant, $service]), ['quantity' => 5, 'amount' => 1])->assertRedirect();

        $this->assertSame(3000, PayableOrder::sole()->amount_cents);
    }

    public function test_a_hidden_or_foreign_item_cannot_be_bought(): void
    {
        $tenant = $this->seller();
        $hidden = $this->item($tenant);
        $hidden->update(['published_to_site' => false]);
        $other = Tenant::create(['name' => 'Altro', 'slug' => 'altro', 'type' => 'azienda', 'plan' => 'demo']);
        $foreign = $this->item($other, title: 'Altro prodotto');
        Http::fake();

        $this->post(route('services.public.buy', [$tenant, $hidden]))->assertNotFound();
        $this->post(route('services.public.buy', [$tenant, $foreign]))->assertNotFound();
        $this->post(route('services.public.buy', [$tenant, $this->item($tenant, title: 'Ok')]), ['quantity' => 99])->assertSessionHasErrors('quantity');

        Http::assertNothingSent();
        $this->assertSame(0, PayableOrder::count());
    }

    public function test_when_stripe_fails_nothing_is_left_behind_and_the_buyer_sees_a_message(): void
    {
        $tenant = $this->seller();
        $item = $this->item($tenant);
        Http::fake(['api.stripe.com/*' => Http::response(['error' => ['message' => 'no']], 500)]);

        $this->from(route('services.public.show', [$tenant, $item]))->post(route('services.public.buy', [$tenant, $item]))
            ->assertRedirect(route('services.public.show', [$tenant, $item]))->assertSessionHasErrors('buy');

        $this->assertSame(0, PayableOrder::count());
    }

    // ---- pagamento ricevuto ----

    public function test_the_payment_webhook_holds_the_money_for_seven_days_and_notifies_seller_and_buyer(): void
    {
        Notification::fake();
        $tenant = $this->seller();
        $owner = User::factory()->create();
        $tenant->users()->attach($owner->id, ['role' => 'admin']);
        $item = $this->item($tenant);
        $this->fakeCheckout();
        $this->post(route('services.public.buy', [$tenant, $item]));
        $order = PayableOrder::sole();

        $this->platformWebhook($this->paidSession($order))->assertOk();

        $order->refresh();
        $this->assertSame('paid', $order->status);
        $this->assertSame('held', $order->payout_status);
        $this->assertTrue($order->isHeld());
        $this->assertSame('pi_123', $order->stripe_payment_intent_id);
        $this->assertSame('Anna Verdi', $order->customer_name);
        $this->assertEqualsWithDelta(now()->addDays(7)->timestamp, $order->release_at->timestamp, 5);

        Notification::assertSentTo($owner, PaymentReceivedNotification::class);
        Notification::assertSentOnDemand(BuyerOrderNotification::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'compratore@example.com');
    }

    public function test_a_repeated_webhook_does_not_notify_twice_or_move_the_release_date(): void
    {
        Notification::fake();
        $tenant = $this->seller();
        $tenant->users()->attach(User::factory()->create()->id, ['role' => 'admin']);
        $this->fakeCheckout();
        $this->post(route('services.public.buy', [$tenant, $this->item($tenant)]));
        $order = PayableOrder::sole();

        $this->platformWebhook($this->paidSession($order))->assertOk();
        $firstRelease = $order->fresh()->release_at;
        $this->travel(2)->days();
        $this->platformWebhook($this->paidSession($order))->assertOk();

        $this->assertEquals($firstRelease, $order->fresh()->release_at);
        Notification::assertSentTimes(PaymentReceivedNotification::class, 1);
        Notification::assertSentOnDemandTimes(BuyerOrderNotification::class, 1);
    }

    public function test_an_unpaid_session_does_not_hold_anything(): void
    {
        $tenant = $this->seller();
        $this->fakeCheckout();
        $this->post(route('services.public.buy', [$tenant, $this->item($tenant)]));
        $order = PayableOrder::sole();

        $this->platformWebhook($this->paidSession($order, ['payment_status' => 'unpaid']))->assertOk();

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertNull($order->fresh()->payout_status);
    }

    public function test_a_protected_order_can_never_be_marked_paid_by_the_sellers_own_webhook(): void
    {
        $tenant = $this->seller();
        $this->fakeCheckout();
        $this->post(route('services.public.buy', [$tenant, $this->item($tenant)]));
        $order = PayableOrder::sole();

        // Stessa sessione, ma arrivata dal conto Stripe del venditore (non da quello di Hub Core).
        $this->assertNull(PaymentRecorder::record($tenant, $this->paidSession($order)));

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_a_direct_order_cannot_be_claimed_through_the_platform_flow(): void
    {
        $tenant = $this->seller();
        $direct = PayableOrder::create(['tenant_id' => $tenant->id, 'channel' => 'site', 'status' => 'pending', 'items' => [], 'amount_cents' => 1000]);

        $this->platformWebhook($this->paidSession($direct))->assertOk();

        $this->assertSame('pending', $direct->fresh()->status);
        $this->assertNull($direct->fresh()->payout_status);
    }

    public function test_an_unknown_tenant_in_the_event_is_ignored_safely(): void
    {
        $this->platformWebhook(['id' => 'cs_x', 'payment_status' => 'paid', 'metadata' => ['hub_flow' => 'protected', 'hub_tenant' => 'nessuno', 'hub_order_id' => '1']])->assertOk();

        $this->assertSame(0, PayableOrder::count());
    }

    // ---- commissioni e pagina del compratore ----

    public function test_the_hub_commission_of_protected_orders_is_netted_at_release_not_billed_monthly(): void
    {
        $tenant = $this->seller();
        TenantCommission::store($tenant, 10, 0);
        $protected = PayableOrder::create(['tenant_id' => $tenant->id, 'channel' => 'hub', 'flow' => 'protected', 'status' => 'paid', 'payout_status' => 'held', 'items' => [], 'amount_cents' => 10000, 'commission_cents' => 1000, 'paid_at' => now()]);
        $direct = PayableOrder::create(['tenant_id' => $tenant->id, 'channel' => 'hub', 'flow' => 'direct', 'status' => 'paid', 'items' => [], 'amount_cents' => 5000, 'commission_cents' => 500, 'paid_at' => now()]);

        Artisan::call('hub:charge-commissions', ['--period' => now()->format('Y-m')]);

        $charge = TenantModuleCharge::sole();
        $this->assertSame(500, $charge->amount_cents);
        $this->assertNull($protected->fresh()->commission_charge_id);
        $this->assertSame($charge->id, $direct->fresh()->commission_charge_id);
    }

    public function test_the_buyer_page_shows_the_protection_and_the_release_date_only_with_the_secret_link(): void
    {
        $tenant = $this->seller();
        $this->fakeCheckout();
        $this->post(route('services.public.buy', [$tenant, $this->item($tenant)]));
        $order = PayableOrder::sole();
        $url = route('protected.order.show', $order->buyer_token);

        $this->get($url)->assertOk()->assertSee('Stiamo aspettando la conferma del pagamento')->assertSee('noindex', false);

        $this->platformWebhook($this->paidSession($order))->assertOk();

        $this->get($url.'?pagato=1')->assertOk()->assertSee('Grazie! Pagamento ricevuto')->assertSee('Come sei protetto')
            ->assertSee('Siero viso')->assertSee('45,00 €')->assertSee($order->fresh()->release_at->locale('it')->translatedFormat('d F Y'));

        $this->get(route('protected.order.show', str_repeat('a', 40)))->assertNotFound();
        $this->get('/ordine/corto')->assertNotFound();
    }

    public function test_the_seller_sees_held_money_and_the_estimated_net_in_the_orders_list(): void
    {
        $tenant = $this->seller();
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['role' => 'admin']);
        $this->fakeCheckout();
        $this->post(route('services.public.buy', [$tenant, $this->item($tenant, cents: 10000)]));
        $order = PayableOrder::sole();
        $this->platformWebhook($this->paidSession($order))->assertOk();

        $this->actingAs($user)->get(route('admin.orders.index', $tenant))->assertOk()
            ->assertSee('Trattenuto')->assertSee('custoditi da Hub Core')->assertSee('ricevi circa 98,25 €');
    }
}
