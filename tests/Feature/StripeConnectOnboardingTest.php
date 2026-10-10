<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use M35\HubPayments\Support\TenantConnect;
use Tests\TestCase;

/** Pagamenti protetti: il venditore si collega a Stripe Connect e convive con le sue chiavi per le vendite dirette. */
class StripeConnectOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.hub_billing.secret_key' => 'sk_test_'.str_repeat('p', 24)]);
    }

    private function seller(bool $ownKeys = false): array
    {
        $tenant = Tenant::create([
            'name' => 'Beauty', 'slug' => 'beauty', 'type' => 'azienda', 'plan' => 'demo',
            'settings' => $ownKeys ? ['stripe' => ['secret_key' => Crypt::encryptString('sk_test_'.str_repeat('a', 24))]] : [],
        ]);
        $user = User::factory()->create(['email' => 'emilia@beauty.test']);
        $tenant->users()->attach($user->id, ['role' => 'admin']);

        return [$tenant, $user];
    }

    private function fakeStripe(bool $ready = false): void
    {
        Http::fake([
            'api.stripe.com/v1/accounts/acct_1/login_links' => Http::response(['url' => 'https://connect.stripe.com/express/abc']),
            'api.stripe.com/v1/accounts/acct_1' => Http::response([
                'id' => 'acct_1', 'charges_enabled' => $ready, 'payouts_enabled' => $ready, 'details_submitted' => $ready,
                'requirements' => ['currently_due' => $ready ? [] : ['external_account', 'individual.verification.document']],
            ]),
            'api.stripe.com/v1/accounts' => Http::response(['id' => 'acct_1']),
            'api.stripe.com/v1/account_links' => Http::response(['url' => 'https://connect.stripe.com/setup/e/acct_1/xyz']),
        ]);
    }

    public function test_the_services_page_offers_protected_payments_next_to_the_own_stripe_keys(): void
    {
        [$tenant, $user] = $this->seller(ownKeys: true);

        $this->actingAs($user)->get(route('admin.services.index', $tenant))->assertOk()
            ->assertSee('Pagamenti protetti Hub Core')
            ->assertSee('Collega i pagamenti protetti')
            ->assertSee('vendite dirette sul tuo sito')
            ->assertSee(route('admin.connect.start', $tenant), false);
    }

    public function test_stripe_fees_are_explained_with_a_concrete_example(): void
    {
        config(['hub-payments.fees.card_percent' => 1.5, 'hub-payments.fees.card_fixed_cents' => 25]);

        $this->assertSame(175, \M35\HubPayments\Support\StripeFees::estimateCents(10000));
        $this->assertSame('1,5% + 0,25 € a pagamento', \M35\HubPayments\Support\StripeFees::rateLabel());
        $this->assertSame(['amount' => '100,00', 'fee' => '1,75', 'net' => '98,25'], \M35\HubPayments\Support\StripeFees::example());

        [$tenant, $user] = $this->seller();

        $this->actingAs($user)->get(route('admin.services.index', $tenant))->assertOk()
            ->assertSee('Trattenute di Stripe')->assertSee('1,5% + 0,25 € a pagamento')->assertSee('ricevi circa')->assertSee('98,25');

        $this->get(route('pricing.show'))->assertOk()
            ->assertSee('pagamenti protetti e trattenute')->assertSee('Quanto mi trattiene Stripe quando vendo?')
            ->assertSee('anche direttamente sul tuo sito');
    }

    public function test_starting_creates_an_express_account_and_sends_the_seller_to_stripe(): void
    {
        [$tenant, $user] = $this->seller();
        $this->fakeStripe();

        $this->actingAs($user)->post(route('admin.connect.start', $tenant))->assertRedirect('https://connect.stripe.com/setup/e/acct_1/xyz');

        $this->assertSame('acct_1', TenantConnect::accountId($tenant->fresh()));
        $this->assertSame('incomplete', TenantConnect::state($tenant->fresh()));

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/v1/accounts')
            && $r['type'] === 'express' && $r['country'] === 'IT' && $r['email'] === 'emilia@beauty.test'
            && $r['capabilities[card_payments][requested]'] === 'true' && $r['capabilities[transfers][requested]'] === 'true'
            && (string) $r['metadata[tenant_id]'] === (string) $tenant->id);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/v1/account_links')
            && $r['account'] === 'acct_1' && $r['type'] === 'account_onboarding'
            && $r['return_url'] === route('admin.connect.return', $tenant) && $r['refresh_url'] === route('admin.connect.refresh', $tenant));
    }

    public function test_starting_again_reuses_the_same_account(): void
    {
        [$tenant, $user] = $this->seller();
        TenantConnect::setAccount($tenant, 'acct_1');
        $this->fakeStripe();

        $this->actingAs($user)->post(route('admin.connect.start', $tenant))->assertRedirect();

        Http::assertNotSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/v1/accounts'));
    }

    public function test_returning_from_stripe_saves_the_status_incomplete(): void
    {
        [$tenant, $user] = $this->seller();
        TenantConnect::setAccount($tenant, 'acct_1');
        $this->fakeStripe(ready: false);

        $this->actingAs($user)->get(route('admin.connect.return', $tenant))
            ->assertRedirect(route('admin.services.index', $tenant))
            ->assertSessionHas('status');

        $tenant->refresh();
        $this->assertSame('incomplete', TenantConnect::state($tenant));
        $this->assertFalse(TenantConnect::isReady($tenant));
        $this->assertContains('external_account', TenantConnect::missing($tenant));
    }

    public function test_when_stripe_confirms_charges_and_payouts_the_seller_is_ready(): void
    {
        [$tenant, $user] = $this->seller();
        TenantConnect::setAccount($tenant, 'acct_1');
        $this->fakeStripe(ready: true);

        $this->actingAs($user)->post(route('admin.connect.sync', $tenant))->assertRedirect();

        $this->assertTrue(TenantConnect::isReady($tenant->fresh()));
        $this->actingAs($user)->get(route('admin.services.index', $tenant))->assertSee('ricevi i soldi delle vendite')->assertSee('Vedi i miei bonifici');
    }

    public function test_the_seller_can_open_the_stripe_page_with_the_payouts(): void
    {
        [$tenant, $user] = $this->seller();
        TenantConnect::setAccount($tenant, 'acct_1');
        $this->fakeStripe(ready: true);

        $this->actingAs($user)->post(route('admin.connect.dashboard', $tenant))->assertRedirect('https://connect.stripe.com/express/abc');
    }

    public function test_dashboard_needs_a_connected_account(): void
    {
        [$tenant, $user] = $this->seller();

        $this->actingAs($user)->post(route('admin.connect.dashboard', $tenant))->assertNotFound();
    }

    public function test_without_the_platform_stripe_key_it_explains_instead_of_failing(): void
    {
        config(['services.hub_billing.secret_key' => null]);
        [$tenant, $user] = $this->seller();
        Http::fake();

        $this->actingAs($user)->post(route('admin.connect.start', $tenant))->assertSessionHasErrors('connect');

        Http::assertNothingSent();
    }

    public function test_a_stripe_error_shows_a_friendly_message(): void
    {
        [$tenant, $user] = $this->seller();
        Http::fake(['api.stripe.com/*' => Http::response(['error' => ['message' => 'Connect non abilitato']], 400)]);

        $this->actingAs($user)->post(route('admin.connect.start', $tenant))->assertSessionHasErrors('connect');

        $this->assertNull(TenantConnect::accountId($tenant->fresh()));
    }

    public function test_another_tenants_user_cannot_use_the_connect_routes(): void
    {
        [$tenant] = $this->seller();
        $stranger = User::factory()->create();
        $other = Tenant::create(['name' => 'Altro', 'slug' => 'altro', 'type' => 'azienda', 'plan' => 'demo']);
        $other->users()->attach($stranger->id, ['role' => 'admin']);
        Http::fake();

        $this->actingAs($stranger)->post(route('admin.connect.start', $tenant))->assertForbidden();

        Http::assertNothingSent();
    }
}
