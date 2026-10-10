<?php

namespace Tests\Feature;

use App\Models\PendingRegistration;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\LaunchOfferReminderNotification;
use App\Notifications\TenantWelcomeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Prima settimana gratis senza pagare; l'euro di lancio si chiede dopo (email + avviso nell'app). */
class FreeWeekOfferTest extends TestCase
{
    use RefreshDatabase;

    private string $whsec;

    protected function setUp(): void
    {
        parent::setUp();

        $this->whsec = 'whsec'.'_'.str_repeat('f', 32);
        config([
            'services.hub_billing.secret_key' => 'sk_test_'.str_repeat('d', 24),
            'services.hub_billing.webhook_secret' => $this->whsec,
            'services.hub_billing.free_days' => 7,
            'services.hub_billing.launch_offer_price_eur' => 1,
            'services.hub_billing.monthly_price_eur' => 29,
        ]);
    }

    private function pending(string $type = 'azienda'): PendingRegistration
    {
        return PendingRegistration::create([
            'token' => 'tok-'.$type, 'type' => $type, 'first_module' => $type === 'privato' ? null : 'promo', 'name' => 'Salone Anna',
            'contact_name' => 'Anna Rossi', 'email' => $type.'@example.com', 'phone' => null, 'expires_at' => now()->addDay(),
        ]);
    }

    private function trialingTenant(array $settings = ['free_week' => true], ?string $email = 'anna@example.com'): array
    {
        $tenant = Tenant::create([
            'name' => 'Salone Anna', 'slug' => 'salone-anna', 'type' => 'azienda', 'plan' => 'demo',
            'subscription_status' => 'trialing', 'trial_ends_at' => now()->subHour(), 'settings' => $settings,
        ]);
        $tenant->forceFill(['guest_verified_at' => now()])->save();
        $user = User::factory()->create(['email' => $email, 'name' => 'Anna']);
        $tenant->users()->attach($user->id, ['role' => 'admin']);

        return [$tenant, $user];
    }

    public function test_a_company_registers_without_paying_and_gets_a_free_week(): void
    {
        Http::fake();
        Notification::fake();
        $this->pending();

        $this->get(route('registration.confirm', 'tok-azienda'))->assertRedirect();

        $tenant = Tenant::sole();
        $this->assertSame('trialing', $tenant->subscription_status);
        $this->assertTrue($tenant->trial_ends_at->between(now()->addDays(6), now()->addDays(8)));
        $this->assertTrue($tenant->settings['free_week']);
        Http::assertNothingSent(); // nessun checkout Stripe alla registrazione
        Notification::assertSentTo(User::sole(), TenantWelcomeNotification::class);
    }

    public function test_a_private_user_stays_free_forever(): void
    {
        Notification::fake();
        $this->pending('privato');

        $this->get(route('registration.confirm', 'tok-privato'))->assertRedirect();

        $this->assertSame('free', Tenant::sole()->subscription_status);
        $this->assertNull(Tenant::sole()->trial_ends_at);
    }

    public function test_the_billing_page_offers_the_launch_euro_and_starts_a_checkout_that_returns_to_the_account(): void
    {
        [$tenant, $user] = $this->trialingTenant();
        Http::fake(['api.stripe.com/*' => Http::response(['id' => 'cs_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_1', 'data' => []])]);

        $this->actingAs($user)->get(route('admin.billing.show', $tenant))
            ->assertOk()->assertSee('Attiva con 1 €')->assertSee(route('admin.billing.launch', $tenant), false);

        $this->actingAs($user)->post(route('admin.billing.launch', $tenant))->assertRedirect('https://checkout.stripe.com/c/pay/cs_1');

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v1/checkout/sessions')
            && $r['metadata[existing_account]'] === '1'
            && $r['metadata[launch_offer]'] === '1'
            && str_contains($r['success_url'], '/billing')
            && (string) $r['subscription_data[trial_period_days]'] === '30');
    }

    public function test_paying_after_the_week_does_not_send_another_welcome_email(): void
    {
        [$tenant] = $this->trialingTenant();
        Notification::fake();
        $payload = json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => [
            'id' => 'cs_1', 'customer' => 'cus_1', 'subscription' => 'sub_1',
            'metadata' => ['tenant_id' => (string) $tenant->id, 'launch_offer' => '1', 'first_module' => 'promo', 'interval' => 'month', 'existing_account' => '1'],
        ]]]);
        $t = time();

        $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$t},v1=".hash_hmac('sha256', $t.'.'.$payload, $this->whsec),
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk();

        $this->assertSame('active', $tenant->fresh()->subscription_status);
        Notification::assertNothingSent();
    }

    public function test_a_paying_or_private_account_cannot_start_the_launch_checkout(): void
    {
        [$tenant, $user] = $this->trialingTenant();
        $tenant->forceFill(['subscription_status' => 'active'])->save();

        $this->actingAs($user)->post(route('admin.billing.launch', $tenant))->assertNotFound();
    }

    public function test_one_reminder_is_sent_when_the_free_week_ends(): void
    {
        [$tenant, $user] = $this->trialingTenant();
        Notification::fake();

        $this->artisan('hub:launch-offer-reminders')->assertSuccessful();
        $this->artisan('hub:launch-offer-reminders')->assertSuccessful();

        Notification::assertSentToTimes($user, LaunchOfferReminderNotification::class, 1);
        $this->assertNotEmpty($tenant->fresh()->settings['launch_reminder_sent_at']);
    }

    public function test_no_reminder_for_old_customers_guests_or_weeks_still_running(): void
    {
        Notification::fake();

        $this->trialingTenant(settings: []); // cliente di prima della nuova regola
        $running = Tenant::create(['name' => 'In corso', 'slug' => 'in-corso', 'type' => 'azienda', 'plan' => 'demo', 'subscription_status' => 'trialing', 'trial_ends_at' => now()->addDays(3), 'settings' => ['free_week' => true]]);
        $running->users()->attach(User::factory()->create()->id, ['role' => 'admin']);
        $guest = Tenant::create(['name' => 'Ospite', 'slug' => 'ospite', 'type' => 'azienda', 'plan' => 'demo', 'subscription_status' => 'trialing', 'trial_ends_at' => now()->subDay(), 'settings' => ['free_week' => true]]);
        $guest->users()->attach(User::factory()->create(['email' => 'guest-1@guest.hub-core.local'])->id, ['role' => 'admin']);

        $this->artisan('hub:launch-offer-reminders')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_in_app_banner_asks_for_the_launch_euro_when_the_week_is_over(): void
    {
        [$tenant, $user] = $this->trialingTenant();

        $this->actingAs($user)->get(route('app.home', $tenant))->assertOk()
            ->assertSee('La settimana gratuita di Salone Anna è finita')
            ->assertSee('attiva con 1 € per continuare');
    }

    public function test_pricing_page_explains_the_three_steps_and_has_a_back_button(): void
    {
        $html = $this->get(route('pricing.show'))->assertOk()->getContent();

        $this->assertStringContainsString('← Indietro', $html);
        $this->assertStringContainsString('id="back-top"', $html);
        $this->assertStringContainsString('id="back-dock"', $html);
        $this->assertStringContainsString('1 · Prima settimana gratis', $html);
        $this->assertStringContainsString('2 · Poi solo 1 €', $html);
        $this->assertStringContainsString('3 · Altri 30 giorni di prova', $html);
        $this->assertStringContainsString(route('registration.create'), $html);
        $this->assertStringContainsString('id="pmax-panel"', $html);
    }
}
