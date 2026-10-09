<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use M35\HubPayments\Models\PayableOrder;
use M35\HubPayments\Models\PayableService;
use Tests\TestCase;

class CartCheckoutApiTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'bridge-test-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['hub.bridge_secret' => self::SECRET]);
    }

    private function tenant(string $slug = 'beauty', array $extra = []): Tenant
    {
        return Tenant::create(array_merge([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'type' => 'azienda',
            'plan' => 'demo',
            'website' => 'https://'.$slug.'.example',
            'workspace_url' => 'https://app.'.$slug.'.example',
            'settings' => ['stripe' => ['secret_key' => Crypt::encryptString('sk_test_'.str_repeat('a', 24))]],
        ], $extra));
    }

    private function item(Tenant $tenant, string $title, int $cents, array $extra = []): PayableService
    {
        return PayableService::create(array_merge([
            'tenant_id' => $tenant->id,
            'type' => 'product',
            'title' => $title,
            'slug' => PayableService::uniqueSlugForTenant($tenant->id, $title),
            'amount_cents' => $cents,
            'currency' => 'eur',
            'stripe_price_id' => 'price_'.md5($title),
            'payment_url' => 'https://buy.stripe.com/test_'.md5($title),
            'status' => 'active',
            'published_to_site' => true,
        ], $extra));
    }

    /** @return array<string, mixed> */
    private function signedPost(string $slug, array $payload, ?int $timestamp = null, ?string $secret = null)
    {
        $body = json_encode($payload);
        $ts = (string) ($timestamp ?? time());
        $sig = 'sha256='.hash_hmac('sha256', $ts.'.'.$body, $secret ?? self::SECRET);

        return $this->call('POST', "/api/v1/$slug/checkout", [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_TIMESTAMP' => $ts,
            'HTTP_X_HUB_SIGNATURE' => $sig,
        ], $body);
    }

    private function fakeStripe(): void
    {
        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => function () {
                static $n = 0;
                $n++;

                return Http::response([
                    'id' => 'cs_test_'.$n,
                    'url' => 'https://checkout.stripe.com/c/pay/cs_test_'.$n,
                ]);
            },
        ]);
    }

    private function payload(array $items, array $extra = []): array
    {
        return array_merge([
            'items' => $items,
            'success_url' => 'https://app.beauty.example/ordine/grazie',
            'cancel_url' => 'https://app.beauty.example/carrello',
        ], $extra);
    }

    public function test_creates_a_checkout_session_with_hub_prices_phone_and_a_pending_order(): void
    {
        $this->fakeStripe();
        $tenant = $this->tenant();
        $a = $this->item($tenant, 'Shampoo', 1850);
        $b = $this->item($tenant, 'Piega', 4000, ['type' => 'service']);

        // Il sito prova anche a mandare un prezzo: deve essere ignorato.
        $response = $this->signedPost('beauty', $this->payload([
            ['id' => $a->id, 'quantity' => 2, 'amount_cents' => 1],
            ['id' => $b->id, 'quantity' => 1],
        ], ['customer_email' => 'maria@example.com']));

        $response->assertCreated();
        $sessionId = $response->json('id');
        $this->assertStringStartsWith('cs_test_', $sessionId);
        $this->assertStringStartsWith('https://checkout.stripe.com/', $response->json('url'));

        Http::assertSent(function (HttpRequest $request) use ($a, $b) {
            $body = $request->body();

            return str_contains($request->url(), '/v1/checkout/sessions')
                && str_contains($body, urlencode('line_items[0][price]').'='.$a->stripe_price_id)
                && str_contains($body, urlencode('line_items[0][quantity]').'=2')
                && str_contains($body, urlencode('line_items[1][price]').'='.$b->stripe_price_id)
                && str_contains($body, urlencode('phone_number_collection[enabled]').'=true')
                && str_contains($body, 'mode=payment')
                && ! str_contains($body, 'amount');
        });

        $order = PayableOrder::firstOrFail();
        $this->assertSame('pending', $order->status);
        $this->assertSame('site', $order->channel);
        $this->assertSame(1850 * 2 + 4000, $order->amount_cents);
        $this->assertSame($sessionId, $order->stripe_session_id);
    }

    public function test_rejects_missing_wrong_old_or_tampered_signatures(): void
    {
        $this->fakeStripe();
        $tenant = $this->tenant();
        $item = $this->item($tenant, 'Shampoo', 1000);
        $payload = $this->payload([['id' => $item->id, 'quantity' => 1]]);

        $this->postJson('/api/v1/beauty/checkout', $payload)->assertUnauthorized();
        $this->signedPost('beauty', $payload, null, 'altro-segreto')->assertUnauthorized();
        $this->signedPost('beauty', $payload, time() - 301)->assertUnauthorized();
        $this->signedPost('beauty', $payload, time() + 301)->assertUnauthorized();

        $body = json_encode($payload);
        $ts = (string) time();
        $this->call('POST', '/api/v1/beauty/checkout', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_TIMESTAMP' => $ts,
            'HTTP_X_HUB_SIGNATURE' => 'sha256='.hash_hmac('sha256', $ts.'.'.$body, self::SECRET),
        ], $body.' ')->assertUnauthorized();

        $this->assertSame(0, PayableOrder::count());
        Http::assertNothingSent();
    }

    public function test_rejects_items_of_another_tenant_unpublished_inactive_and_quotes(): void
    {
        $this->fakeStripe();
        $tenant = $this->tenant();
        $other = $this->tenant('altro');

        $foreign = $this->item($other, 'Altrui', 1000);
        $hidden = $this->item($tenant, 'Nascosto', 1000, ['published_to_site' => false]);
        $archived = $this->item($tenant, 'Archiviato', 1000, ['status' => 'archived']);
        $quote = $this->item($tenant, 'Preventivo', 1000, ['type' => 'quote']);
        $ok = $this->item($tenant, 'Valido', 1000);

        foreach ([$foreign, $hidden, $archived, $quote] as $bad) {
            $this->signedPost('beauty', $this->payload([
                ['id' => $ok->id, 'quantity' => 1],
                ['id' => $bad->id, 'quantity' => 1],
            ]))->assertStatus(422)->assertJsonPath('errors.items.0', fn ($m) => str_contains($m, (string) $bad->id));
        }

        $this->assertSame(0, PayableOrder::count());
        Http::assertNothingSent();
    }

    public function test_return_urls_must_belong_to_the_tenant_domains(): void
    {
        $this->fakeStripe();
        $tenant = $this->tenant();
        $item = $this->item($tenant, 'Shampoo', 1000);
        $items = [['id' => $item->id, 'quantity' => 1]];

        $this->signedPost('beauty', $this->payload($items, ['success_url' => 'https://evil.example/ok']))
            ->assertStatus(422)->assertJsonPath('errors.success_url.0', 'Dominio non consentito.');
        $this->signedPost('beauty', $this->payload($items, ['cancel_url' => 'https://app.beauty.example@evil.example/x']))
            ->assertStatus(422);
        $this->signedPost('beauty', $this->payload($items, ['success_url' => 'http://beauty.example/ok']))
            ->assertCreated(); // http consentito solo in locale/test

        foreach (['https://beauty.example/a', 'https://www.beauty.example/a', 'https://app.beauty.example/a'] as $url) {
            $this->signedPost('beauty', $this->payload($items, ['success_url' => $url]))->assertCreated();
        }
    }

    public function test_validates_quantities_and_cart_size(): void
    {
        $tenant = $this->tenant();
        $item = $this->item($tenant, 'Shampoo', 1000);

        $this->signedPost('beauty', $this->payload([['id' => $item->id, 'quantity' => 0]]))->assertStatus(422);
        $this->signedPost('beauty', $this->payload([['id' => $item->id, 'quantity' => 100]]))->assertStatus(422);
        $this->signedPost('beauty', $this->payload([]))->assertStatus(422);
        $this->signedPost('nonexistent', $this->payload([['id' => $item->id, 'quantity' => 1]]))->assertNotFound();
    }

    public function test_tenant_without_stripe_gets_a_clear_error_and_stripe_failure_leaves_no_order(): void
    {
        $tenant = $this->tenant('beauty', ['settings' => []]);
        $item = $this->item($tenant, 'Shampoo', 1000);
        $this->signedPost('beauty', $this->payload([['id' => $item->id, 'quantity' => 1]]))->assertStatus(409);

        $tenant2 = $this->tenant('salone');
        $item2 = $this->item($tenant2, 'Olio', 1000);
        Http::fake(['api.stripe.com/*' => Http::response(['error' => ['message' => 'sk_test_secret rifiutata']], 400)]);

        $response = $this->signedPost('salone', $this->payload([['id' => $item2->id, 'quantity' => 1]], [
            'success_url' => 'https://app.salone.example/ok',
            'cancel_url' => 'https://app.salone.example/no',
        ]));

        $response->assertStatus(502);
        $this->assertStringNotContainsString('sk_test', $response->getContent());
        $this->assertSame(0, PayableOrder::count());
    }

    public function test_wp_bridge_dest_services_and_products_go_to_the_right_sections(): void
    {
        $tenant = $this->tenant();
        $user = User::factory()->create(['wp_username' => 'emilia']);
        $tenant->users()->attach($user->id, ['role' => 'admin']);

        foreach (['services' => 'admin.services.index', 'products' => 'admin.products.index', 'promos' => 'admin.promos.index'] as $dest => $route) {
            $ts = time();
            $sig = hash_hmac('sha256', 'beauty|emilia|'.$ts, self::SECRET);

            $this->get('/auth/wp-bridge?'.http_build_query([
                'tenant' => 'beauty', 'wp_user' => 'emilia', 'ts' => $ts, 'sig' => $sig, 'dest' => $dest,
            ]))->assertRedirect(route($route, $tenant));

            auth()->logout();
        }
    }
}
