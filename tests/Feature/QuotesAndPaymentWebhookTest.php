<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantModuleCharge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use M35\HubPayments\Models\PayableOrder;
use M35\HubPayments\Models\PayableService;
use M35\HubPayments\Notifications\PaymentReceivedNotification;
use M35\HubPayments\Support\TenantServiceQuota;
use M35\HubPayments\Support\TenantStripeConfig;
use M35\HubPayments\Support\TenantStripeWebhook;
use Tests\TestCase;

class QuotesAndPaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'bridge-test-secret';

    private const WHSEC = 'whsec_testsecret0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();

        config(['hub.bridge_secret' => self::SECRET]);
    }

    private function tenant(string $slug = 'beauty', bool $stripe = true): Tenant
    {
        return Tenant::create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'type' => 'azienda',
            'plan' => 'demo',
            'settings' => $stripe ? ['stripe' => ['secret_key' => Crypt::encryptString('sk_test_'.str_repeat('a', 24))]] : [],
        ]);
    }

    private function fakeStripeForQuotes(): void
    {
        Http::fake([
            'api.stripe.com/v1/products' => Http::response(['id' => 'prod_q1']),
            'api.stripe.com/v1/prices' => Http::response(['id' => 'price_q1', 'unit_amount' => 35050]),
            'api.stripe.com/v1/payment_links/*' => Http::response(['id' => 'plink_q1', 'active' => false]),
            'api.stripe.com/v1/payment_links' => Http::response(['id' => 'plink_q1', 'url' => 'https://buy.stripe.com/test_q1']),
        ]);
    }

    private function signed(string $method, string $path, array $payload = [], ?int $timestamp = null, ?string $secret = null)
    {
        $body = $method === 'GET' ? '' : json_encode($payload);
        $ts = (string) ($timestamp ?? time());

        return $this->call($method, $path, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_TIMESTAMP' => $ts,
            'HTTP_X_HUB_SIGNATURE' => 'sha256='.hash_hmac('sha256', $ts.'.'.$body, $secret ?? self::SECRET),
        ], $body);
    }

    private function quotePayload(array $extra = []): array
    {
        return array_merge([
            'title' => 'Pacchetto sposa',
            'amount_cents' => 35050,
            'customer_name' => 'Maria',
            'description' => 'nota interna',
            'created_by' => 'emilia',
        ], $extra);
    }

    private function stripeWebhook(Tenant $tenant, array $event, ?string $secret = null, ?int $timestamp = null)
    {
        $body = json_encode($event);
        $ts = (string) ($timestamp ?? time());
        $sig = hash_hmac('sha256', $ts.'.'.$body, $secret ?? self::WHSEC);

        return $this->call('POST', '/api/v1/'.$tenant->slug.'/stripe-webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t=$ts,v1=$sig",
        ], $body);
    }

    private function completed(array $session): array
    {
        return [
            'id' => 'evt_'.uniqid(),
            'type' => 'checkout.session.completed',
            'data' => ['object' => array_merge(['id' => 'cs_live_1', 'payment_status' => 'paid'], $session)],
        ];
    }

    // ---- Preventivi (API firmata) ----

    public function test_creates_a_single_use_quote_that_is_never_published_nor_counted(): void
    {
        $this->fakeStripeForQuotes();
        $tenant = $this->tenant();

        $response = $this->signed('POST', '/api/v1/beauty/quotes', $this->quotePayload());

        $response->assertCreated()
            ->assertJsonPath('quote.title', 'Pacchetto sposa')
            ->assertJsonPath('quote.customer_name', 'Maria')
            ->assertJsonPath('quote.description', 'nota interna')
            ->assertJsonPath('quote.amount_cents', 35050)
            ->assertJsonPath('quote.payment_url', 'https://buy.stripe.com/test_q1')
            ->assertJsonPath('quote.status', 'pending')
            ->assertJsonPath('quote.paid_at', null)
            ->assertJsonPath('quote.created_by', 'emilia');
        $this->assertNotNull($response->json('quote.created_at'));

        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/v1/payment_links')
            && ! str_contains($r->url(), 'plink')
            && str_contains($r->body(), urlencode('restrictions[completed_sessions][limit]').'=1'));

        $quote = PayableService::firstOrFail();
        $this->assertSame('quote', $quote->type);
        $this->assertFalse($quote->published_to_site);

        // Fuori da quota servizi, addebiti modulo e cataloghi pubblici.
        $this->assertSame(0, TenantServiceQuota::usedCount($tenant));
        $this->assertSame(0, TenantModuleCharge::count());
        $this->getJson('/api/v1/beauty/services')->assertJsonCount(0, 'services');
        $this->getJson('/api/v1/beauty/products')->assertJsonCount(0, 'products');
        $this->get("/s/beauty/{$quote->slug}")->assertNotFound();
        $this->get('/s/beauty')->assertOk()->assertDontSee('Pacchetto sposa');
    }

    public function test_quote_amount_limits_and_required_fields(): void
    {
        $this->fakeStripeForQuotes();
        $this->tenant();

        $this->signed('POST', '/api/v1/beauty/quotes', $this->quotePayload(['amount_cents' => 49]))->assertStatus(422);
        $this->signed('POST', '/api/v1/beauty/quotes', $this->quotePayload(['amount_cents' => 9_999_901]))->assertStatus(422);
        $this->signed('POST', '/api/v1/beauty/quotes', $this->quotePayload(['title' => '']))->assertStatus(422);
        $this->signed('POST', '/api/v1/beauty/quotes', $this->quotePayload(['amount_cents' => 'abc']))->assertStatus(422);
        $this->assertSame(0, PayableService::count());

        $this->signed('POST', '/api/v1/beauty/quotes', $this->quotePayload(['amount_cents' => 50]))->assertCreated();
        $this->signed('POST', '/api/v1/beauty/quotes', $this->quotePayload(['amount_cents' => 9_999_900]))->assertCreated();
    }

    public function test_quote_endpoints_reject_bad_signatures_and_unknown_tenants(): void
    {
        $this->fakeStripeForQuotes();
        $this->tenant();

        $this->postJson('/api/v1/beauty/quotes', $this->quotePayload())->assertUnauthorized();
        $this->getJson('/api/v1/beauty/quotes')->assertUnauthorized();
        $this->signed('POST', '/api/v1/beauty/quotes', $this->quotePayload(), null, 'altro')->assertUnauthorized();
        $this->signed('GET', '/api/v1/beauty/quotes', [], time() - 400)->assertUnauthorized();
        $this->signed('POST', '/api/v1/nessuno/quotes', $this->quotePayload())->assertNotFound();

        $this->assertSame(0, PayableService::count());
        Http::assertNothingSent();
    }

    public function test_quote_requires_stripe_and_stripe_failure_leaves_nothing(): void
    {
        $this->tenant('senzastripe', false);
        $this->signed('POST', '/api/v1/senzastripe/quotes', $this->quotePayload())->assertStatus(409);

        $this->tenant();
        Http::fake(['api.stripe.com/*' => Http::response(['error' => ['message' => 'sk_test_x non valida']], 401)]);
        $response = $this->signed('POST', '/api/v1/beauty/quotes', $this->quotePayload());

        $response->assertStatus(502);
        $this->assertStringNotContainsString('sk_test', $response->getContent());
        $this->assertSame(0, PayableService::count());
    }

    public function test_lists_only_this_tenants_open_and_paid_quotes_newest_first(): void
    {
        $this->fakeStripeForQuotes();
        $beauty = $this->tenant();
        $other = $this->tenant('altro');

        $make = fn (Tenant $t, string $title, array $extra = []) => PayableService::create(array_merge([
            'tenant_id' => $t->id, 'type' => 'quote', 'title' => $title,
            'slug' => PayableService::uniqueSlugForTenant($t->id, $title), 'amount_cents' => 1000,
            'payment_url' => 'https://buy.stripe.com/x', 'status' => 'active', 'metadata' => ['customer_name' => 'Anna'],
        ], $extra));

        $make($beauty, 'Vecchio');
        $make($beauty, 'Pagato', ['status' => 'paid', 'paid_at' => now()]);
        $make($beauty, 'Annullato', ['status' => 'archived']);
        $make($beauty, 'Recente');
        $make($other, 'Altrui');
        PayableService::create([
            'tenant_id' => $beauty->id, 'type' => 'service', 'title' => 'Piega', 'slug' => 'piega',
            'amount_cents' => 1000, 'status' => 'active',
        ]);

        $response = $this->signed('GET', '/api/v1/beauty/quotes')->assertOk();

        $this->assertSame(['Recente', 'Pagato', 'Vecchio'], collect($response->json('quotes'))->pluck('title')->all());
        $this->assertSame('paid', $response->json('quotes.1.status'));
        $this->assertNotNull($response->json('quotes.1.paid_at'));
        $response->assertJsonPath('quotes.0.customer_name', 'Anna');
    }

    // ---- Webhook per tenant ----

    public function test_webhook_rejects_missing_wrong_old_and_other_tenant_signatures(): void
    {
        $beauty = $this->tenant();
        TenantStripeWebhook::store($beauty, self::WHSEC);
        $event = $this->completed(['metadata' => ['hub_order_id' => '1']]);

        $this->postJson('/api/v1/beauty/stripe-webhook', $event)->assertUnauthorized();
        $this->stripeWebhook($beauty, $event, 'whsec_sbagliato0123456789abcdef')->assertUnauthorized();
        $this->stripeWebhook($beauty, $event, null, time() - 400)->assertUnauthorized();

        // Un tenant senza webhook configurato non accetta nulla, nemmeno con il segreto di un altro.
        $other = $this->tenant('altro');
        $this->stripeWebhook($other, $event)->assertNotFound();
        $this->postJson('/api/v1/nessuno/stripe-webhook', $event)->assertNotFound();
    }

    public function test_paid_quote_is_marked_paid_recorded_as_order_and_emailed_once(): void
    {
        Notification::fake();
        $this->fakeStripeForQuotes();
        $tenant = $this->tenant();
        $owner = User::factory()->create();
        $tenant->users()->attach($owner->id, ['role' => 'admin']);
        TenantStripeWebhook::store($tenant, self::WHSEC);

        $this->signed('POST', '/api/v1/beauty/quotes', $this->quotePayload())->assertCreated();

        $event = $this->completed([
            'id' => 'cs_live_quote',
            'payment_link' => 'plink_q1',
            'amount_total' => 35050,
            'customer_details' => ['email' => 'maria@example.com', 'name' => 'Maria Rossi', 'phone' => '+393331112222'],
        ]);

        $this->stripeWebhook($tenant, $event)->assertOk();
        $this->stripeWebhook($tenant, $event)->assertOk(); // Stripe riconsegna: nessun doppione

        $quote = PayableService::firstOrFail();
        $this->assertTrue($quote->isPaid());
        $this->assertNotNull($quote->paid_at);

        $order = PayableOrder::sole();
        $this->assertSame('paid', $order->status);
        $this->assertSame('site', $order->channel);
        $this->assertSame(35050, $order->amount_cents);
        $this->assertSame('maria@example.com', $order->customer_email);
        $this->assertSame('Maria Rossi', $order->customer_name);
        $this->assertSame('+393331112222', $order->customer_phone);
        $this->assertSame('quote', $order->items[0]['type']);
        $this->assertSame('Pacchetto sposa', $order->items[0]['title']);

        Notification::assertSentToTimes($owner, PaymentReceivedNotification::class, 1);

        $this->signed('GET', '/api/v1/beauty/quotes')->assertJsonPath('quotes.0.status', 'paid');
        $this->assertNotNull(TenantStripeWebhook::lastEventAt($tenant->fresh()));
    }

    public function test_paid_cart_order_gets_customer_data_and_one_email(): void
    {
        Notification::fake();
        $tenant = $this->tenant();
        $owner = User::factory()->create();
        $tenant->users()->attach($owner->id, ['role' => 'admin']);
        TenantStripeWebhook::store($tenant, self::WHSEC);

        $order = PayableOrder::create([
            'tenant_id' => $tenant->id, 'channel' => 'site', 'status' => 'pending', 'stripe_session_id' => 'cs_live_cart',
            'items' => [['id' => 1, 'type' => 'product', 'title' => 'Shampoo', 'quantity' => 2, 'unit_amount_cents' => 1850]],
            'currency' => 'eur', 'amount_cents' => 3700,
        ]);

        $event = $this->completed([
            'id' => 'cs_live_cart',
            'metadata' => ['hub_order_id' => (string) $order->id],
            'amount_total' => 3700,
            'customer_details' => ['email' => 'cliente@example.com', 'name' => 'Luca', 'phone' => '+39 333 000'],
        ]);

        $this->stripeWebhook($tenant, $event)->assertOk();
        $this->stripeWebhook($tenant, $event)->assertOk();

        $order->refresh();
        $this->assertSame('paid', $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame('cliente@example.com', $order->customer_email);
        $this->assertSame('Luca', $order->customer_name);
        $this->assertSame('+39 333 000', $order->customer_phone);
        $this->assertSame(1, PayableOrder::count());

        Notification::assertSentToTimes($owner, PaymentReceivedNotification::class, 1);
    }

    public function test_email_lists_items_amount_and_customer_data(): void
    {
        $tenant = $this->tenant();
        $order = PayableOrder::create([
            'tenant_id' => $tenant->id, 'channel' => 'site', 'status' => 'paid', 'paid_at' => now(),
            'items' => [['id' => 1, 'type' => 'product', 'title' => 'Shampoo', 'quantity' => 2, 'unit_amount_cents' => 1850]],
            'currency' => 'eur', 'amount_cents' => 3700,
            'customer_email' => 'cliente@example.com', 'customer_name' => 'Luca', 'customer_phone' => '+39 333 000',
        ]);

        $mail = (new PaymentReceivedNotification($tenant, $order))->toMail(new User);
        $text = collect([$mail->subject, ...$mail->introLines])->implode("\n");

        $this->assertStringContainsString('37,00', $text);
        $this->assertStringContainsString('2 × Shampoo', $text);
        $this->assertStringContainsString('Luca', $text);
        $this->assertStringContainsString('cliente@example.com', $text);
        $this->assertStringContainsString('+39 333 000', $text);
    }

    public function test_unpaid_other_tenant_unknown_and_unrelated_events_change_nothing(): void
    {
        Notification::fake();
        $tenant = $this->tenant();
        $other = $this->tenant('altro');
        $owner = User::factory()->create();
        $tenant->users()->attach($owner->id, ['role' => 'admin']);
        TenantStripeWebhook::store($tenant, self::WHSEC);

        $foreign = PayableOrder::create([
            'tenant_id' => $other->id, 'status' => 'pending', 'items' => [], 'currency' => 'eur', 'amount_cents' => 1000,
        ]);
        $mine = PayableOrder::create([
            'tenant_id' => $tenant->id, 'status' => 'pending', 'items' => [], 'currency' => 'eur', 'amount_cents' => 1000,
        ]);

        // pagamento asincrono non ancora incassato
        $this->stripeWebhook($tenant, $this->completed(['metadata' => ['hub_order_id' => (string) $mine->id], 'payment_status' => 'unpaid']))->assertOk();
        // ordine di un altro tenant
        $this->stripeWebhook($tenant, $this->completed(['metadata' => ['hub_order_id' => (string) $foreign->id]]))->assertOk();
        // evento che non ci interessa
        $this->stripeWebhook($tenant, ['id' => 'evt_x', 'type' => 'charge.refunded', 'data' => ['object' => ['id' => 'ch_1']]])->assertOk();
        // payment link che non è un preventivo
        $this->stripeWebhook($tenant, $this->completed(['payment_link' => 'plink_sconosciuto']))->assertOk();

        $this->assertSame('pending', $mine->fresh()->status);
        $this->assertSame('pending', $foreign->fresh()->status);
        $this->assertSame(2, PayableOrder::count());
        Notification::assertNothingSent();

        // quando il metodo asincrono incassa, l'ordine passa a pagato
        $paid = $this->completed(['metadata' => ['hub_order_id' => (string) $mine->id]]);
        $paid['type'] = 'checkout.session.async_payment_succeeded';
        $this->stripeWebhook($tenant, $paid)->assertOk();
        $this->assertSame('paid', $mine->fresh()->status);
        Notification::assertSentToTimes($owner, PaymentReceivedNotification::class, 1);
    }

    // ---- Pannello: pulsante webhook e sezione Preventivi ----

    public function test_button_creates_the_webhook_on_the_tenants_stripe_account_and_stores_the_secret(): void
    {
        Http::fake([
            'api.stripe.com/v1/webhook_endpoints' => Http::response(['id' => 'we_1', 'secret' => self::WHSEC]),
        ]);
        $tenant = $this->tenant();
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['role' => 'admin']);

        $this->actingAs($user)->post(route('admin.services.stripe-webhook.create', $tenant))
            ->assertRedirect()->assertSessionHas('status');

        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/v1/webhook_endpoints')
            && str_contains($r->body(), urlencode('/api/v1/beauty/stripe-webhook'))
            && str_contains($r->body(), 'checkout.session.completed')
            && str_contains($r->body(), 'checkout.session.async_payment_succeeded'));

        $tenant->refresh();
        $this->assertSame(self::WHSEC, TenantStripeWebhook::secret($tenant));
        $this->assertSame('we_1', TenantStripeWebhook::endpointId($tenant));
        $this->assertStringNotContainsString(self::WHSEC, json_encode($tenant->settings));
    }

    public function test_button_failure_shows_a_clear_message_with_manual_steps_and_stores_nothing(): void
    {
        Http::fake([
            'api.stripe.com/*' => Http::response(['error' => ['message' => 'This key does not have the required permissions']], 403),
        ]);
        $tenant = $this->tenant();
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['role' => 'admin']);

        $this->actingAs($user)->post(route('admin.services.stripe-webhook.create', $tenant))
            ->assertRedirect()->assertSessionHasErrors('webhook')->assertSessionHas('webhook_manual');

        $this->assertFalse(TenantStripeWebhook::isConfigured($tenant->fresh()));

        $page = $this->actingAs($user)->withSession(['webhook_manual' => true])->get(route('admin.services.index', $tenant));
        $page->assertOk()
            ->assertSee('/api/v1/beauty/stripe-webhook', false)
            ->assertSee('checkout.session.completed', false)
            ->assertSee('whsec_', false);
    }

    public function test_manual_secret_is_validated_and_survives_resaving_the_same_stripe_key(): void
    {
        $tenant = $this->tenant();
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['role' => 'admin']);

        $this->actingAs($user)->post(route('admin.services.stripe-webhook.secret', $tenant), ['webhook_secret' => 'non-valido'])
            ->assertSessionHasErrors('webhook_secret');
        $this->assertFalse(TenantStripeWebhook::isConfigured($tenant->fresh()));

        $this->actingAs($user)->post(route('admin.services.stripe-webhook.secret', $tenant), ['webhook_secret' => self::WHSEC])
            ->assertSessionHas('status');
        $this->assertSame(self::WHSEC, TenantStripeWebhook::secret($tenant->fresh()));

        // Stessa chiave Stripe salvata di nuovo: il webhook resta. Chiave di un altro conto: si azzera.
        $same = 'sk_test_'.str_repeat('a', 24);
        TenantStripeConfig::store($tenant->fresh(), $same);
        $this->assertSame(self::WHSEC, TenantStripeWebhook::secret($tenant->fresh()));

        TenantStripeConfig::store($tenant->fresh(), 'sk_test_'.str_repeat('b', 24));
        $this->assertNull(TenantStripeWebhook::secret($tenant->fresh()));
    }

    public function test_quotes_panel_creates_lists_and_cancels_quotes_without_charges(): void
    {
        $this->fakeStripeForQuotes();
        $tenant = $this->tenant();
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['role' => 'admin']);

        $this->actingAs($user)->post(route('admin.quotes.store', $tenant), [
            'title' => 'Trattamento su misura', 'amount' => '120,50', 'customer_name' => 'Giulia',
        ])->assertSessionHasErrors('amount');

        $this->actingAs($user)->post(route('admin.quotes.store', $tenant), [
            'title' => 'Trattamento su misura', 'amount' => '120.50', 'customer_name' => 'Giulia',
        ])->assertRedirect(route('admin.quotes.index', $tenant));

        $quote = PayableService::sole();
        $this->assertSame(12050, $quote->amount_cents);
        $this->assertSame($user->id, $quote->created_by);
        $this->assertSame(0, TenantModuleCharge::count());

        $this->actingAs($user)->get(route('admin.quotes.index', $tenant))
            ->assertOk()->assertSee('Trattamento su misura')->assertSee('120,50')->assertSee('In attesa');

        $this->actingAs($user)->delete(route('admin.quotes.destroy', [$tenant, $quote]))->assertRedirect();
        $this->assertSame('archived', $quote->fresh()->status);

        // un preventivo pagato non si annulla
        $paid = PayableService::create([
            'tenant_id' => $tenant->id, 'type' => 'quote', 'title' => 'Pagato', 'slug' => 'pagato',
            'amount_cents' => 1000, 'status' => 'paid', 'paid_at' => now(),
        ]);
        $this->actingAs($user)->delete(route('admin.quotes.destroy', [$tenant, $paid]))->assertSessionHasErrors('stripe');
        $this->assertSame('paid', $paid->fresh()->status);
    }

    public function test_wp_bridge_dest_quotes_goes_to_the_quotes_section(): void
    {
        $tenant = $this->tenant();
        $user = User::factory()->create(['wp_username' => 'emilia']);
        $tenant->users()->attach($user->id, ['role' => 'admin']);
        $ts = time();

        $this->get('/auth/wp-bridge?'.http_build_query([
            'tenant' => 'beauty', 'wp_user' => 'emilia', 'ts' => $ts,
            'sig' => hash_hmac('sha256', 'beauty|emilia|'.$ts, self::SECRET), 'dest' => 'quotes',
        ]))->assertRedirect(route('admin.quotes.index', $tenant));
    }
}
