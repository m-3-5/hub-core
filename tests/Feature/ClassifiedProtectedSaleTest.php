<?php

namespace Tests\Feature;

use App\Models\ClassifiedAd;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use M35\HubPayments\Models\PayableOrder;
use M35\HubPayments\Support\SellerTerms;
use M35\HubPayments\Support\TenantConnect;
use Tests\TestCase;

/** Pezzo 4: anche i privati vendono oggetti con un annuncio e il pagamento protetto. */
class ClassifiedProtectedSaleTest extends TestCase
{
    use RefreshDatabase;

    private string $whsec = 'whsec_platform_test';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.hub_billing.secret_key' => 'sk_test_'.str_repeat('p', 24), 'services.hub_billing.webhook_secret' => $this->whsec]);
    }

    /** @return array{0: Tenant, 1: User} */
    private function privateSeller(bool $ready = true): array
    {
        $tenant = Tenant::create(['name' => 'Mario Rossi', 'slug' => 'mario-rossi', 'type' => 'privato', 'plan' => 'demo']);
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['role' => 'admin']);

        if ($ready) {
            TenantConnect::setAccount($tenant, 'acct_private');
            TenantConnect::sync($tenant->fresh(), ['id' => 'acct_private', 'charges_enabled' => true, 'payouts_enabled' => true, 'details_submitted' => true]);
            SellerTerms::accept($tenant->fresh(), $user, '127.0.0.1');
        }

        return [$tenant->fresh(), $user];
    }

    private function ad(Tenant $tenant, array $extra = []): ClassifiedAd
    {
        return ClassifiedAd::create($extra + [
            'tenant_id' => $tenant->id, 'slug' => 'bici-'.uniqid(), 'category' => 'oggetti', 'title' => 'Bicicletta da città', 'zone' => 'Corigliano-Rossano',
            'price' => 120, 'protected_sale' => true, 'description' => 'Bici in ottimo stato.', 'status' => 'published', 'published_at' => now(),
        ]);
    }

    private function fakeCheckout(): void
    {
        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_ad', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_ad'])]);
    }

    private function paid(PayableOrder $order): void
    {
        $payload = json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => [
            'id' => 'cs_test_ad', 'payment_status' => 'paid', 'payment_intent' => 'pi_ad', 'amount_total' => $order->amount_cents,
            'metadata' => ['hub_order_id' => (string) $order->id, 'hub_tenant' => 'mario-rossi', 'hub_flow' => 'protected'],
            'customer_details' => ['email' => 'compratore@example.com', 'name' => 'Anna'],
        ]]]);
        $t = time();

        $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$t},v1=".hash_hmac('sha256', $t.'.'.$payload, $this->whsec),
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk();
    }

    public function test_a_private_seller_can_connect_payments_from_the_ads_page_without_the_services_module(): void
    {
        [$tenant, $user] = $this->privateSeller(ready: false);
        Http::fake([
            'api.stripe.com/v1/accounts' => Http::response(['id' => 'acct_new']),
            'api.stripe.com/v1/account_links' => Http::response(['url' => 'https://connect.stripe.com/setup/e/acct_new/x']),
            'api.stripe.com/v1/accounts/acct_new' => Http::response(['id' => 'acct_new', 'charges_enabled' => true, 'payouts_enabled' => true, 'details_submitted' => true]),
        ]);

        // Il modulo Servizi è precluso ai privati, gli Annunci no: lì compare la scheda dei pagamenti protetti.
        $this->actingAs($user)->get(route('admin.services.index', $tenant))->assertForbidden();
        $this->actingAs($user)->get(route('admin.classifieds.index', $tenant))->assertOk()
            ->assertSee('Pagamenti protetti Hub Core')->assertSee('Collega i pagamenti protetti')->assertSee('accept_terms', false);

        $this->actingAs($user)->post(route('admin.connect.start', $tenant), ['accept_terms' => 1])->assertRedirect('https://connect.stripe.com/setup/e/acct_new/x');
        $this->actingAs($user)->get(route('admin.connect.return', $tenant))->assertRedirect(route('admin.classifieds.index', $tenant));

        $this->assertTrue(TenantConnect::isReady($tenant->fresh()));
        $this->actingAs($user)->get(route('admin.orders.index', $tenant))->assertOk()->assertSee('← Annunci');
    }

    public function test_the_protected_sale_option_only_sticks_to_fixed_price_objects(): void
    {
        [$tenant, $user] = $this->privateSeller();
        $base = ['title' => 'Divano', 'zone' => 'Rossano', 'description' => 'Divano 3 posti', 'protected_sale' => 1];

        $this->actingAs($user)->post(route('admin.classifieds.store', $tenant), $base + ['category' => 'oggetti', 'price' => 80])->assertRedirect();
        $this->assertTrue(ClassifiedAd::where('title', 'Divano')->sole()->protected_sale);

        ClassifiedAd::query()->delete();
        $this->actingAs($user)->post(route('admin.classifieds.store', $tenant), $base + ['category' => 'vendita', 'price' => 90000])->assertRedirect();
        $this->assertFalse(ClassifiedAd::sole()->protected_sale);

        ClassifiedAd::query()->delete();
        $this->actingAs($user)->post(route('admin.classifieds.store', $tenant), $base + ['category' => 'oggetti'])->assertRedirect();
        $this->assertFalse(ClassifiedAd::sole()->protected_sale); // senza prezzo non si può comprare
    }

    public function test_the_ad_page_offers_the_protected_purchase_and_tells_the_buyer_the_seller_is_private(): void
    {
        [$tenant] = $this->privateSeller();
        $ad = $this->ad($tenant);

        $this->get(route('classifieds.show', [$tenant, $ad]))->assertOk()
            ->assertSee('Compra con pagamento protetto')->assertSee(route('classifieds.buy', [$tenant, $ad]), false)
            ->assertSee('name="accept_buyer_terms"', false)->assertSee('privato')->assertSee('non valgono garanzia legale e recesso');
    }

    public function test_no_purchase_offer_if_the_seller_is_not_connected_or_the_ad_is_not_for_sale(): void
    {
        [$notReady] = $this->privateSeller(ready: false);
        $ad1 = $this->ad($notReady);
        $this->get(route('classifieds.show', [$notReady, $ad1]))->assertOk()->assertDontSee('Compra con pagamento protetto');

        $other = Tenant::create(['name' => 'Agenzia', 'slug' => 'agenzia', 'type' => 'azienda', 'plan' => 'demo']);
        TenantConnect::setAccount($other, 'acct_a');
        TenantConnect::sync($other->fresh(), ['id' => 'acct_a', 'charges_enabled' => true, 'payouts_enabled' => true]);
        SellerTerms::accept($other->fresh(), null, null);
        $house = $this->ad($other->fresh(), ['category' => 'vendita', 'price' => 150000, 'protected_sale' => true]);
        $this->get(route('classifieds.show', [$other, $house]))->assertOk()->assertDontSee('Compra con pagamento protetto');

        Http::fake();
        $this->post(route('classifieds.buy', [$other, $house]), ['accept_buyer_terms' => 1])->assertSessionHasErrors('buy');
        Http::assertNothingSent();
        $this->assertSame(0, PayableOrder::count());
    }

    public function test_buying_from_an_ad_requires_the_terms_and_creates_a_protected_order_with_the_ad_price(): void
    {
        [$tenant] = $this->privateSeller();
        $ad = $this->ad($tenant);
        $this->fakeCheckout();

        $this->post(route('classifieds.buy', [$tenant, $ad]))->assertSessionHasErrors('buy');
        $this->assertSame(0, PayableOrder::count());

        $this->post(route('classifieds.buy', [$tenant, $ad]), ['accept_buyer_terms' => 1, 'customer_email' => 'compratore@example.com'])
            ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_ad');

        $order = PayableOrder::sole();
        $this->assertSame('protected', $order->flow);
        $this->assertSame(12000, $order->amount_cents);
        $this->assertSame('classified', $order->items[0]['type']);
        $this->assertSame($ad->id, $order->items[0]['id']);
        $this->assertNotNull($order->buyer_terms_accepted_at);

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/v1/checkout/sessions')
            && (int) $r['line_items[0][price_data][unit_amount]'] === 12000 && (int) $r['line_items[0][quantity]'] === 1
            && $r['line_items[0][price_data][product_data][name]'] === 'Bicicletta da città'
            && $r['cancel_url'] === route('classifieds.show', [$tenant, $ad]) && $r['metadata[hub_flow]'] === 'protected');
    }

    public function test_once_paid_the_item_is_sold_and_nobody_else_can_buy_it(): void
    {
        Notification::fake();
        [$tenant] = $this->privateSeller();
        $ad = $this->ad($tenant);
        $this->fakeCheckout();
        $this->post(route('classifieds.buy', [$tenant, $ad]), ['accept_buyer_terms' => 1]);

        $this->paid(PayableOrder::sole());

        $this->assertSame('sold', $ad->fresh()->status);
        $this->assertTrue($ad->fresh()->isSold());

        Http::fake();
        $this->post(route('classifieds.buy', [$tenant, $ad]), ['accept_buyer_terms' => 1])->assertSessionHasErrors('buy');
        Http::assertNothingSent();
        $this->get(route('classifieds.show', [$tenant, $ad]))->assertNotFound(); // non più in bacheca
    }

    public function test_a_buyer_cannot_buy_another_tenants_ad_through_a_wrong_seller_url(): void
    {
        [$tenant] = $this->privateSeller();
        $ad = $this->ad($tenant);
        $other = Tenant::create(['name' => 'Altro', 'slug' => 'altro', 'type' => 'privato', 'plan' => 'demo']);
        Http::fake();

        $this->post(route('classifieds.buy', [$other, $ad]), ['accept_buyer_terms' => 1])->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_economic_and_buyer_terms_cover_private_sellers(): void
    {
        $this->get(route('terms.economic'))->assertOk()->assertSee('Se vendi come privato')->assertSee('Vendita occasionale')->assertSee('armi')->assertSee('partita IVA');
        $this->get(route('terms.buyer'))->assertOk()->assertSee('Se il venditore è un privato')->assertSee('non valgono');
    }
}
