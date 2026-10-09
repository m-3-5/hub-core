<?php

namespace Tests\Feature;

use App\Models\Promo;
use App\Models\Tenant;
use App\Services\WordPressWebhookDispatcher;
use App\Support\TenantSiteSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use M35\HubPayments\Models\PayableService;
use Tests\TestCase;

class TenantSiteSyncTest extends TestCase
{
    use RefreshDatabase;

    private const LEGACY = 'https://old.example/wp-json/sync';

    private const GLOBAL_SERVICES = 'https://global.example/api/hub/sync';

    private const OWN = 'https://app.beauty.example/api/hub/sync';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.hub.webhook_url' => self::LEGACY,
            'services.hub.webhook_secret' => 'global-secret',
            'services.hub.services_webhook_url' => self::GLOBAL_SERVICES,
        ]);
        Http::fake(['*' => Http::response(['ok' => true])]);
    }

    private function tenant(string $slug = 'beauty', bool $auto = true): Tenant
    {
        return Tenant::create([
            'name' => ucfirst($slug), 'slug' => $slug, 'type' => 'azienda', 'plan' => 'demo',
            'settings' => ['auto_site_sync' => $auto],
        ]);
    }

    private function sentUrls(): array
    {
        return Http::recorded()->map(fn ($pair) => $pair[0]->url())->values()->all();
    }

    private function service(Tenant $tenant): PayableService
    {
        return PayableService::create([
            'tenant_id' => $tenant->id, 'type' => 'product', 'title' => 'X', 'slug' => 'x',
            'amount_cents' => 1000, 'status' => 'active', 'published_to_site' => true,
        ]);
    }

    public function test_catalog_events_go_to_the_tenant_address_signed_with_the_global_secret(): void
    {
        $tenant = $this->tenant();
        TenantSiteSync::update($tenant, ['url' => self::OWN]);

        app(WordPressWebhookDispatcher::class)->servicesSync($tenant->fresh());

        $this->assertSame([self::OWN], $this->sentUrls());
        Http::assertSent(function (HttpRequest $r) {
            return $r->header('X-Hub-Event')[0] === 'services.sync'
                && $r->header('X-Hub-Signature')[0] === 'sha256='.hash_hmac('sha256', $r->body(), 'global-secret')
                && str_contains($r->body(), 'products_index_url');
        });
    }

    public function test_tenant_without_address_keeps_using_the_global_addresses(): void
    {
        $tenant = $this->tenant();
        app(WordPressWebhookDispatcher::class)->servicesSync($tenant);
        $this->assertSame([self::GLOBAL_SERVICES], $this->sentUrls());

        // senza indirizzo servizi globale, come prima, cade sul vecchio indirizzo
        Http::fake(['*' => Http::response([])]);
        config(['services.hub.services_webhook_url' => null]);
        app(WordPressWebhookDispatcher::class)->servicesSync($tenant);
        $this->assertContains(self::LEGACY, $this->sentUrls());
    }

    public function test_one_tenant_address_never_receives_another_tenants_events(): void
    {
        $beauty = $this->tenant();
        $other = $this->tenant('altro');
        TenantSiteSync::update($beauty, ['url' => self::OWN]);

        app(WordPressWebhookDispatcher::class)->servicesSync($other->fresh());

        $this->assertNotContains(self::OWN, $this->sentUrls());
    }

    public function test_promos_reach_both_the_legacy_site_and_the_tenant_site_until_legacy_is_dropped(): void
    {
        $tenant = $this->tenant();
        TenantSiteSync::update($tenant, ['url' => self::OWN]);
        $dispatcher = app(WordPressWebhookDispatcher::class);

        $dispatcher->promosSync($tenant->fresh());
        $this->assertEqualsCanonicalizing([self::LEGACY, self::OWN], $this->sentUrls());

        Http::fake(['*' => Http::response([])]);
        TenantSiteSync::update($tenant->fresh(), ['legacy_promos' => false]);
        $dispatcher->promosSync($tenant->fresh());
        $this->assertSame([self::OWN], $this->sentUrls());

        Http::fake(['*' => Http::response([])]);
        TenantSiteSync::update($tenant->fresh(), ['promos' => false, 'legacy_promos' => true]);
        $dispatcher->promosSync($tenant->fresh());
        $this->assertSame([self::LEGACY], $this->sentUrls());
    }

    public function test_same_address_is_never_called_twice(): void
    {
        $tenant = $this->tenant();
        TenantSiteSync::update($tenant, ['url' => self::LEGACY]);

        app(WordPressWebhookDispatcher::class)->promosSync($tenant->fresh());

        $this->assertSame([self::LEGACY], $this->sentUrls());
    }

    public function test_dedicated_secret_signs_only_the_tenant_address(): void
    {
        $tenant = $this->tenant();
        TenantSiteSync::update($tenant, ['url' => self::OWN, 'secret' => 'segreto-dedicato']);
        $this->assertSame('segreto-dedicato', TenantSiteSync::secret($tenant->fresh()));
        $this->assertStringNotContainsString('segreto-dedicato', json_encode($tenant->fresh()->settings));

        app(WordPressWebhookDispatcher::class)->promosSync($tenant->fresh());

        Http::assertSent(fn (HttpRequest $r) => $r->url() === self::OWN
            && $r->header('X-Hub-Signature')[0] === 'sha256='.hash_hmac('sha256', $r->body(), 'segreto-dedicato'));
        Http::assertSent(fn (HttpRequest $r) => $r->url() === self::LEGACY
            && $r->header('X-Hub-Signature')[0] === 'sha256='.hash_hmac('sha256', $r->body(), 'global-secret'));
    }

    public function test_nothing_is_sent_when_auto_sync_is_off(): void
    {
        $tenant = $this->tenant('beauty', false);
        TenantSiteSync::update($tenant, ['url' => self::OWN]);

        app(WordPressWebhookDispatcher::class)->servicesSync($tenant->fresh());
        app(WordPressWebhookDispatcher::class)->promosSync($tenant->fresh());

        Http::assertNothingSent();
    }

    public function test_a_failing_site_does_not_break_the_other_address(): void
    {
        Http::fake([
            self::OWN => Http::response('boom', 500),
            self::LEGACY => Http::response(['ok' => true]),
        ]);
        $tenant = $this->tenant();
        TenantSiteSync::update($tenant, ['url' => self::OWN]);

        app(WordPressWebhookDispatcher::class)->promosSync($tenant->fresh());

        $this->assertEqualsCanonicalizing([self::LEGACY, self::OWN], $this->sentUrls());
    }

    public function test_command_sets_shows_tests_and_clears_the_address(): void
    {
        $tenant = $this->tenant('beauty', false);

        $this->artisan('hub:site-sync', ['tenant' => 'beauty', 'url' => 'ftp://insicuro.example/x'])
            ->expectsOutputToContain('Indirizzo non valido')->assertFailed();
        $this->artisan('hub:site-sync', ['tenant' => 'beauty', 'url' => 'non-un-url'])->assertFailed();
        $this->artisan('hub:site-sync', ['tenant' => 'nessuno'])->assertFailed();
        $this->artisan('hub:site-sync', ['tenant' => 'beauty', '--drop-legacy' => true])
            ->expectsOutputToContain('Prima imposta')->assertFailed();

        $this->artisan('hub:site-sync', ['tenant' => 'beauty', 'url' => self::OWN, '--auto' => true, '--test' => true])
            ->expectsOutputToContain('Impostazioni salvate')
            ->expectsOutputToContain('risposta HTTP 200')
            ->assertSuccessful();

        $tenant->refresh();
        $this->assertSame(self::OWN, TenantSiteSync::url($tenant));
        $this->assertTrue($tenant->hasAutoSiteSync());
        Http::assertSent(fn (HttpRequest $r) => $r->url() === self::OWN && $r->header('X-Hub-Event')[0] === 'site.ping');

        $this->artisan('hub:site-sync', ['tenant' => 'beauty', '--drop-legacy' => true, '--no-promos' => true])->assertSuccessful();
        $this->assertFalse(TenantSiteSync::sendsLegacyPromos($tenant->fresh()));
        $this->assertFalse(TenantSiteSync::sendsPromos($tenant->fresh()));

        $this->artisan('hub:site-sync', ['tenant' => 'beauty', '--promos' => true, '--keep-legacy' => true])->assertSuccessful();
        $this->assertTrue(TenantSiteSync::sendsLegacyPromos($tenant->fresh()));
        $this->assertTrue(TenantSiteSync::sendsPromos($tenant->fresh()));

        $this->artisan('hub:site-sync', ['tenant' => 'beauty', '--clear' => true])->assertSuccessful();
        $this->assertNull(TenantSiteSync::url($tenant->fresh()));
        $this->assertTrue($tenant->fresh()->hasAutoSiteSync()); // l'avviso automatico resta com'era
    }
}
