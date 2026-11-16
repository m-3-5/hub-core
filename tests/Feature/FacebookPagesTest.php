<?php

namespace Tests\Feature;

use App\Models\Promo;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ContentModerator;
use App\Services\FacebookPages;
use App\Support\PayPerUse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** «Pubblica da Facebook»: pagina collegata con l'accesso ufficiale, post con #promo → promo (online se si può pagare). */
class FacebookPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.facebook.app_id' => '123', 'services.facebook.app_secret' => 'segreto', 'services.facebook.verify_token' => 'verifica',
            'services.hub_billing.pay_per_use.enabled' => true, 'services.hub_billing.secret_key' => 'sk_test_hub',
        ]);
        Storage::fake('public');
        Notification::fake();
    }

    private function owner(): array
    {
        $tenant = Tenant::create(['name' => 'Pizzeria Rocco', 'slug' => 'pizzeria-rocco', 'type' => 'azienda', 'plan' => 'demo', 'guest_verified_at' => now()]);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'admin']);

        return [$tenant, $user];
    }

    private function connected(Tenant $tenant): Tenant
    {
        $settings = $tenant->settings ?? [];
        $settings['facebook'] = ['page_id' => '555', 'page_name' => 'Pizzeria Rocco', 'token' => Crypt::encryptString('page-token'), 'hashtags' => ['#promo'], 'connected_at' => now()->toIso8601String()];
        $tenant->forceFill(['settings' => $settings])->save();

        return $tenant->fresh();
    }

    private function fakeGraph(string $message): void
    {
        $img = imagecreatetruecolor(800, 600);
        ob_start();
        imagejpeg($img);
        $jpg = ob_get_clean();

        Http::fake([
            'graph.facebook.com/*/555_1*' => Http::response(['message' => $message, 'full_picture' => 'https://scontent.fbcdn.net/p.jpg', 'permalink_url' => 'https://www.facebook.com/555/posts/1', 'created_time' => '2026-11-16T10:00:00+0000']),
            'scontent.fbcdn.net/*' => Http::response($jpg, 200, ['Content-Type' => 'image/jpeg']),
        ]);
    }

    private function signed(array $payload): array
    {
        $body = json_encode($payload);

        return [$body, ['X-Hub-Signature-256' => 'sha256='.hash_hmac('sha256', $body, 'segreto'), 'Content-Type' => 'application/json']];
    }

    public function test_the_page_explains_and_connect_goes_to_facebook(): void
    {
        [$tenant, $user] = $this->owner();

        $this->actingAs($user)->get(route('admin.facebook.show', $tenant))->assertOk()->assertSee('Collega Facebook');
        $this->post(route('admin.facebook.connect', $tenant))
            ->assertRedirect()->assertRedirectContains('facebook.com/v27.0/dialog/oauth')->assertRedirectContains('pages_read_engagement');
    }

    public function test_the_callback_connects_the_only_page_and_subscribes_it(): void
    {
        [$tenant, $user] = $this->owner();
        Http::fake([
            'graph.facebook.com/v27.0/oauth/access_token*' => Http::response(['access_token' => 'user-token']),
            'graph.facebook.com/v27.0/me/accounts*' => Http::response(['data' => [['id' => '555', 'name' => 'Pizzeria Rocco', 'access_token' => 'page-token']]]),
            'graph.facebook.com/v27.0/555/subscribed_apps' => Http::response(['success' => true]),
        ]);

        $this->actingAs($user)->post(route('admin.facebook.connect', $tenant));
        $state = Crypt::encryptString(json_encode(['t' => $tenant->id, 'n' => session('fb_nonce')]));

        $this->get(route('facebook.callback', ['code' => 'abc', 'state' => $state]))->assertRedirect(route('admin.facebook.show', $tenant));

        $fb = FacebookPages::connection($tenant->fresh());
        $this->assertSame('555', $fb['page_id']);
        $this->assertSame('page-token', Crypt::decryptString($fb['token']));
        Http::assertSent(fn ($r) => str_contains($r->url(), '555/subscribed_apps') && str_contains($r->body(), 'subscribed_fields=feed'));

        // Lo stesso «state» non vale due volte.
        $this->get(route('facebook.callback', ['code' => 'abc', 'state' => $state]))->assertForbidden();
    }

    public function test_webhook_verification_and_signature(): void
    {
        $this->get('/api/facebook/webhook?hub_mode=subscribe&hub_verify_token=verifica&hub_challenge=42')->assertOk()->assertSee('42');
        $this->get('/api/facebook/webhook?hub_mode=subscribe&hub_verify_token=sbagliato&hub_challenge=42')->assertForbidden();
        $this->call('POST', '/api/facebook/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256=falso'], '{"entry":[]}')->assertForbidden();
    }

    public function test_a_post_with_promo_hashtag_and_a_card_goes_online_and_costs_one_euro(): void
    {
        [$tenant] = $this->owner();
        $tenant = $this->connected($tenant);
        PayPerUse::saveCard($tenant, 'cus_1', ['payment_method' => 'pm_1', 'brand' => 'visa', 'last4' => '4242']);
        $this->mock(ContentModerator::class, fn ($m) => $m->shouldReceive('check')->andReturn(['allowed' => true, 'unavailable' => false, 'category' => 'ok', 'reason' => '']));
        $this->fakeGraph("Pizza margherita a 5 € tutto il weekend\nVieni a trovarci! #promo");

        [$body, $headers] = $this->signed(['object' => 'page', 'entry' => [['id' => '555', 'changes' => [['field' => 'feed', 'value' => ['item' => 'photo', 'verb' => 'add', 'post_id' => '555_1']]]]]]);
        $this->call('POST', '/api/facebook/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => $headers['X-Hub-Signature-256']], $body)->assertOk();

        $promo = Promo::sole();
        $this->assertSame('Pizza margherita a 5 € tutto il weekend', $promo->title);
        $this->assertTrue($promo->isPublished());
        $this->assertSame(100, PayPerUse::pendingCents($tenant));

        // Lo stesso post due volte non crea doppioni.
        app(FacebookPages::class)->handlePost($tenant, '555_1');
        $this->assertSame(1, Promo::count());
    }

    public function test_without_card_it_stays_a_draft_and_without_hashtag_nothing_happens(): void
    {
        [$tenant] = $this->owner();
        $tenant = $this->connected($tenant);
        $this->mock(ContentModerator::class, fn ($m) => $m->shouldReceive('check')->andReturn(['allowed' => true, 'unavailable' => false, 'category' => 'ok', 'reason' => '']));

        $this->fakeGraph('Buona domenica a tutti!');
        $this->assertNull(app(FacebookPages::class)->handlePost($tenant, '555_1'));
        $this->assertSame(0, Promo::count());

        Http::swap(new \Illuminate\Http\Client\Factory);
        $this->fakeGraph("Calzone + bibita 7 € #Promo");
        $promo = app(FacebookPages::class)->handlePost($tenant, '555_1');
        $this->assertSame('draft', $promo->fresh()->status);
        $this->assertSame(0, PayPerUse::pendingCents($tenant));
    }

    public function test_disconnect_forgets_the_page(): void
    {
        [$tenant, $user] = $this->owner();
        $tenant = $this->connected($tenant);
        Http::fake(['graph.facebook.com/*' => Http::response(['success' => true])]);

        $this->actingAs($user)->post(route('admin.facebook.disconnect', $tenant))->assertRedirect(route('admin.facebook.show', $tenant));
        $this->assertNull(FacebookPages::connection($tenant->fresh()));
    }
}
