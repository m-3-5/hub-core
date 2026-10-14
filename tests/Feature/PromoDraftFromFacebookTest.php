<?php

namespace Tests\Feature;

use App\Models\Promo;
use App\Models\PromoSource;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PromoDraftToApproveNotification;
use App\Services\WordPressWebhookDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PromoDraftFromFacebookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'bridge-test-secret';

    private const IMAGE_URL = 'https://scontent.xx.fbcdn.net/v/t39/abc.jpg';

    protected function setUp(): void
    {
        parent::setUp();

        config(['hub.bridge_secret' => self::SECRET]);
        Storage::fake('public');
        Notification::fake();

        // Immagine vera da 1x1 pixel.
        Http::fake([
            'scontent.xx.fbcdn.net/*' => Http::response(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='), 200, ['Content-Type' => 'image/png']),
            'expired.fbcdn.net/*' => Http::response('', 403),
        ]);
    }

    private function tenant(): Tenant
    {
        return Tenant::create(['name' => 'Beauty', 'slug' => 'beauty', 'type' => 'azienda', 'plan' => 'demo', 'website' => 'https://beauty.example', 'guest_verified_at' => now()]);
    }

    /** @param array<string, mixed> $payload */
    private function signedPost(array $payload, ?int $timestamp = null, ?string $secret = null)
    {
        $body = json_encode($payload);
        $ts = (string) ($timestamp ?? time());
        $sig = 'sha256='.hash_hmac('sha256', $ts.'.'.$body, $secret ?? self::SECRET);

        return $this->call('POST', '/api/v1/beauty/promos/drafts', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_TIMESTAMP' => $ts,
            'HTTP_X_HUB_SIGNATURE' => $sig,
        ], $body);
    }

    /** @return array<string, mixed> */
    private function payload(array $extra = []): array
    {
        return array_merge([
            'source' => 'facebook',
            'source_id' => '123_456',
            'source_url' => 'https://www.facebook.com/beauty/posts/456',
            'title' => 'Pulizia viso a 39€ ✨',
            'description' => "Solo per ottobre, prenota su WhatsApp.\nValida fino al 31/10",
            'image_url' => self::IMAGE_URL,
            'posted_at' => '2026-10-16T11:00:00+02:00',
            'publish' => false,
        ], $extra);
    }

    public function test_invalid_or_expired_signature_is_rejected(): void
    {
        $this->tenant();

        $this->postJson('/api/v1/beauty/promos/drafts', $this->payload())->assertUnauthorized();
        $this->signedPost($this->payload(), null, 'altro-segreto')->assertUnauthorized();
        $this->signedPost($this->payload(), time() - 400)->assertUnauthorized();

        $this->assertSame(0, Promo::count());
    }

    public function test_creates_a_draft_downloads_the_photo_and_warns_the_owners(): void
    {
        $tenant = $this->tenant();
        $owner = User::factory()->create();
        $owner->tenants()->attach($tenant);

        $response = $this->signedPost($this->payload())->assertCreated()
            ->assertJsonPath('promo.status', 'draft')
            ->assertJsonPath('duplicate', false);

        $promo = Promo::sole();
        $this->assertSame('draft', $promo->status);
        $this->assertSame('Pulizia viso a 39€ ✨', $promo->title);
        $this->assertNull($promo->ends_at);
        $this->assertTrue($promo->isImported());
        $this->assertSame('https://www.facebook.com/beauty/posts/456', $promo->sourceUrl());
        $this->assertTrue(Storage::disk('public')->exists($promo->image_path), 'La foto è salvata sul nostro server');
        $this->assertStringNotContainsString('fbcdn', (string) $promo->imageUrl());
        $this->assertSame($promo->id, $response->json('promo.id'));
        $this->assertSame($promo->slug, $response->json('promo.slug'));
        $this->assertStringContainsString('/admin/', $response->json('promo.admin_url'));

        Notification::assertSentTo($owner, PromoDraftToApproveNotification::class, function ($n, $channels) use ($owner) {
            $mail = $n->toMail($owner);

            return str_starts_with($mail->subject, 'Nuova promo da approvare: Pulizia viso');
        });
    }

    public function test_same_post_twice_creates_a_single_promo(): void
    {
        $this->tenant();

        $this->signedPost($this->payload())->assertCreated();
        $this->signedPost($this->payload())->assertOk()->assertJsonPath('duplicate', true)->assertJsonPath('promo.slug', Promo::sole()->slug);

        $this->assertSame(1, Promo::count());
        $this->assertSame(1, PromoSource::count());
    }

    public function test_a_deleted_promo_never_comes_back(): void
    {
        $tenant = $this->tenant();
        $user = User::factory()->create();
        $user->tenants()->attach($tenant);

        $this->signedPost($this->payload())->assertCreated();
        $promo = Promo::sole();

        $this->actingAs($user)->delete(route('admin.promos.destroy', [$tenant, $promo]))->assertRedirect();
        $this->assertSame(0, Promo::count());

        $this->signedPost($this->payload())->assertOk()->assertJsonPath('duplicate', true)->assertJsonPath('deleted', true);
        $this->assertSame(0, Promo::count());
    }

    public function test_photo_from_a_disallowed_host_or_expired_link_is_refused_without_creating_anything(): void
    {
        $this->tenant();

        foreach ([
            'https://evil.example/pic.jpg',
            'http://scontent.xx.fbcdn.net/pic.jpg',
            'https://fbcdn.net.evil.example/pic.jpg',
            'https://169.254.169.254/latest/meta-data',
        ] as $url) {
            $this->signedPost($this->payload(['image_url' => $url]))->assertStatus(422);
        }

        // Link scaduto (403): 422, il sito riprova al giro dopo e il post non risulta importato.
        $this->signedPost($this->payload(['image_url' => 'https://expired.fbcdn.net/old.jpg']))->assertStatus(422);

        $this->assertSame(0, Promo::count());
        $this->assertSame(0, PromoSource::count());
    }

    public function test_draft_is_not_public_and_sends_no_webhook_until_approved(): void
    {
        $tenant = $this->tenant();
        $this->mock(WordPressWebhookDispatcher::class, fn ($m) => $m->shouldReceive('promoPublished')->never());

        $this->signedPost($this->payload())->assertCreated();
        $promo = Promo::sole();

        $this->getJson('/api/v1/beauty/promos')->assertOk()->assertJsonCount(0, 'promos');
        $this->getJson('/api/v1/beauty/promos/'.$promo->slug)->assertNotFound();
        $this->get(route('promo.archive', $tenant))->assertOk()->assertDontSee('Pulizia viso');
    }

    public function test_approving_publishes_applies_one_month_expiry_and_sends_the_webhook(): void
    {
        $tenant = $this->tenant();
        $user = User::factory()->create();
        $user->tenants()->attach($tenant);

        $this->signedPost($this->payload())->assertCreated();
        $promo = Promo::sole();

        $this->mock(WordPressWebhookDispatcher::class, fn ($m) => $m->shouldReceive('promoPublished')->once());

        $this->actingAs($user)->post(route('admin.promos.publish', [$tenant, $promo]))->assertRedirect();

        $promo->refresh();
        $this->assertSame('published', $promo->status);
        $this->assertNotNull($promo->published_at);
        $this->assertTrue($promo->ends_at->isSameDay(now()->addMonth()), 'La scadenza è un mese dall\'approvazione');

        $this->getJson('/api/v1/beauty/promos')->assertOk()->assertJsonCount(1, 'promos');
    }

    public function test_an_explicit_end_date_is_kept_and_publish_true_publishes_immediately(): void
    {
        $this->tenant();
        $this->mock(WordPressWebhookDispatcher::class, fn ($m) => $m->shouldReceive('promoPublished')->once());

        $this->signedPost($this->payload(['source_id' => '9_9', 'ends_at' => '2026-10-31T23:59:00+01:00', 'publish' => true]))
            ->assertCreated()->assertJsonPath('promo.status', 'published');

        $promo = Promo::sole();
        $this->assertSame('2026-10-31', $promo->ends_at->timezone('Europe/Rome')->toDateString());
    }

    public function test_panel_shows_the_to_approve_section_with_the_three_actions(): void
    {
        $tenant = $this->tenant();
        $user = User::factory()->create();
        $user->tenants()->attach($tenant);

        $this->signedPost($this->payload())->assertCreated();
        $promo = Promo::sole();

        $this->actingAs($user)->get(route('admin.promos.index', $tenant))->assertOk()
            ->assertSee('Da approvare (1)')
            ->assertSee('da Facebook')
            ->assertSee('Approva e pubblica')
            ->assertSee('Modifica')
            ->assertSee('Elimina')
            ->assertSee('Vedi il post originale')
            ->assertSee(route('admin.promos.publish', [$tenant, $promo]), false);
    }
}
