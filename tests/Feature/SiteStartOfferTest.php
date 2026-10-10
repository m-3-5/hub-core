<?php

namespace Tests\Feature;

use App\Models\SiteLead;
use App\Models\SiteOrder;
use App\Models\User;
use App\Notifications\SiteOrderPaidNotification;
use App\Notifications\SiteOrderWelcomeNotification;
use App\Services\SiteOrderBilling;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Promo «1 € per partire»: pagamento reale di 1 €, 15 giorni di prova, poi rate mensili che si fermano da sole. */
class SiteStartOfferTest extends TestCase
{
    use RefreshDatabase;

    private string $whsec;

    protected function setUp(): void
    {
        parent::setUp();

        // Valore finto composto a runtime: i letterali "whsec_..." fanno scattare lo scanner dei segreti di GitHub.
        $this->whsec = 'whsec'.'_'.str_repeat('b', 32);

        config([
            'landing.offer_ends' => now()->addDays(20)->toDateString(),
            'landing.leads_email' => 'leads@example.test',
            'services.hub_billing.secret_key' => 'sk_test_'.str_repeat('c', 24),
            'services.hub_billing.webhook_secret' => $this->whsec,
        ]);
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'name' => 'Maria Rossi', 'phone' => '333 1234567', 'email' => 'maria@example.com',
            'package' => 'aziendale', 'consent' => '1', 'rendered_at' => time() - 10,
        ], $extra);
    }

    private function fakeCheckout(): void
    {
        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1']),
            'api.stripe.com/v1/subscriptions/*' => Http::response(['id' => 'sub_1']),
        ]);
    }

    private function webhook(array $event)
    {
        $payload = json_encode($event);
        $t = time();
        $signature = hash_hmac('sha256', $t.'.'.$payload, $this->whsec);

        return $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$t},v1={$signature}",
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }

    public function test_installment_is_the_package_price_split_in_equal_monthly_rates(): void
    {
        $this->assertSame(2900, SiteOrderBilling::installmentCents(290, 10));
        $this->assertSame(5900, SiteOrderBilling::installmentCents(590, 10));
        $this->assertSame(9900, SiteOrderBilling::installmentCents(990, 10));
        $this->assertSame(2417, SiteOrderBilling::installmentCents(290, 12));
    }

    public function test_landing_shows_the_one_euro_offer_with_the_monthly_rate_for_each_package(): void
    {
        $html = $this->get('/siti-web-corigliano-rossano')->assertOk()->getContent();

        foreach (['Parti con 1 €', 'id="parti"'] as $needle) {
            $this->assertStringContainsString($needle, $html, $needle);
        }

        $this->assertStringContainsString('10 rate da 29 €', $html);
        $this->assertStringContainsString('10 rate da 59 €', $html);
        $this->assertStringContainsString('10 rate da 99 €', $html);
        $this->assertStringContainsString(route('landing.web.start'), $html);
    }

    public function test_offer_is_hidden_without_stripe_or_after_the_deadline(): void
    {
        config(['services.hub_billing.secret_key' => null]);
        $this->get('/siti-web-corigliano-rossano')->assertOk()->assertDontSee('id="parti"', false);

        config(['services.hub_billing.secret_key' => 'sk_test_'.str_repeat('c', 24), 'landing.offer_ends' => now()->subDay()->toDateString()]);
        $this->get('/siti-web-corigliano-rossano')->assertOk()->assertDontSee('id="parti"', false);
        $this->post('/siti-web-corigliano-rossano/parti', $this->payload())->assertSessionHasErrors('package', null, 'start');
    }

    public function test_starting_creates_the_order_and_sends_the_customer_to_a_real_one_euro_checkout(): void
    {
        $this->fakeCheckout();

        $this->post('/siti-web-corigliano-rossano/parti', $this->payload())->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_1');

        $order = SiteOrder::firstOrFail();
        $this->assertSame('aziendale', $order->plan);
        $this->assertSame(100, $order->start_cents);
        $this->assertSame(15, $order->trial_days);
        $this->assertSame(10, $order->installments);
        $this->assertSame(5900, $order->installment_cents);
        $this->assertSame(59000, $order->package_cents);
        $this->assertSame('pending', $order->status);
        $this->assertSame('cs_test_1', $order->stripe_session_id);
        $this->assertSame('landing-1-euro', SiteLead::firstOrFail()->source);

        Http::assertSent(function (HttpRequest $request) use ($order) {
            if (! str_ends_with($request->url(), '/v1/checkout/sessions')) {
                return false;
            }

            $d = $request->data();

            return $d['mode'] === 'subscription'
                && (int) $d['line_items[0][price_data][unit_amount]'] === 100
                && ! isset($d['line_items[0][price_data][recurring][interval]'])
                && (int) $d['line_items[1][price_data][unit_amount]'] === 5900
                && $d['line_items[1][price_data][recurring][interval]'] === 'month'
                && (string) $d['subscription_data[trial_period_days]'] === '15'
                && (string) $d['metadata[site_order_id]'] === (string) $order->id
                && $d['customer_email'] === 'maria@example.com';
        });
    }

    public function test_incomplete_or_spam_requests_never_reach_stripe(): void
    {
        $this->fakeCheckout();

        $this->post('/siti-web-corigliano-rossano/parti', $this->payload(['email' => '']))->assertSessionHasErrors('email', null, 'start');
        $this->post('/siti-web-corigliano-rossano/parti', $this->payload(['package' => 'app']))->assertSessionHasErrors('package', null, 'start');
        $this->post('/siti-web-corigliano-rossano/parti', $this->payload(['consent' => null]))->assertSessionHasErrors('consent', null, 'start');
        $this->post('/siti-web-corigliano-rossano/parti', $this->payload(['company' => 'bot']))->assertSessionHasErrors('package', null, 'start');
        $this->post('/siti-web-corigliano-rossano/parti', $this->payload(['rendered_at' => time()]))->assertSessionHasErrors('package', null, 'start');

        $this->assertSame(0, SiteOrder::count());
        Http::assertNothingSent();
    }

    public function test_a_failed_checkout_gives_a_friendly_message_and_keeps_the_form_values(): void
    {
        Http::fake(['api.stripe.com/*' => Http::response(['error' => ['message' => 'boom']], 500)]);

        $this->post('/siti-web-corigliano-rossano/parti', $this->payload())
            ->assertRedirect(route('landing.web').'#parti')
            ->assertSessionHasErrors('package', null, 'start')
            ->assertSessionHasInput('email', 'maria@example.com');
    }

    public function test_payment_completed_starts_the_trial_schedules_the_end_of_the_rates_and_notifies_everybody(): void
    {
        $this->fakeCheckout();
        Notification::fake();
        $this->post('/siti-web-corigliano-rossano/parti', $this->payload());
        $order = SiteOrder::firstOrFail();

        $this->webhook([
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_test_1', 'customer' => 'cus_1', 'subscription' => 'sub_1', 'metadata' => ['site_order_id' => (string) $order->id]]],
        ])->assertOk();

        $order->refresh();
        $this->assertSame('trial', $order->status);
        $this->assertSame('sub_1', $order->stripe_subscription_id);
        $this->assertNotNull($order->paid_at);
        $this->assertTrue($order->trial_ends_at->between(now()->addDays(14), now()->addDays(16)));
        // 10 rate: la prima a fine prova, l'ultima 9 mesi dopo; si ferma a metà dell'ultimo mese.
        $this->assertTrue($order->completes_at->between($order->trial_ends_at->copy()->addMonths(9)->addDays(14), $order->trial_ends_at->copy()->addMonths(9)->addDays(16)));
        $this->assertSame('1 € per partire: pagato', $order->lead->message);

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/v1/subscriptions/sub_1')
            && (int) $r['cancel_at'] === $order->completes_at->getTimestamp());

        Notification::assertSentOnDemand(SiteOrderPaidNotification::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'leads@example.test');
        Notification::assertSentOnDemand(SiteOrderWelcomeNotification::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'maria@example.com');
    }

    public function test_repeated_webhook_does_not_notify_twice(): void
    {
        $this->fakeCheckout();
        Notification::fake();
        $this->post('/siti-web-corigliano-rossano/parti', $this->payload());
        $order = SiteOrder::firstOrFail();
        $event = ['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_test_1', 'customer' => 'cus_1', 'subscription' => 'sub_1', 'metadata' => ['site_order_id' => (string) $order->id]]]];

        $this->webhook($event)->assertOk();
        $this->webhook($event)->assertOk();

        Notification::assertSentOnDemandTimes(SiteOrderPaidNotification::class, 1);
    }

    public function test_subscription_events_follow_the_order_through_payment_and_the_end_of_the_rates(): void
    {
        $order = SiteOrder::create([
            'name' => 'Maria', 'phone' => '333', 'email' => 'maria@example.com', 'plan' => 'vetrina', 'start_cents' => 100, 'trial_days' => 15,
            'installments' => 10, 'installment_cents' => 2900, 'package_cents' => 29000, 'status' => 'trial',
            'stripe_subscription_id' => 'sub_9', 'paid_at' => now()->subDays(20), 'trial_ends_at' => now()->subDays(5), 'completes_at' => now()->addMonths(9),
        ]);

        $this->webhook(['type' => 'customer.subscription.updated', 'data' => ['object' => ['id' => 'sub_9', 'status' => 'active', 'metadata' => ['site_order_id' => (string) $order->id]]]])->assertOk();
        $this->assertSame('paying', $order->fresh()->status);

        $this->webhook(['type' => 'customer.subscription.updated', 'data' => ['object' => ['id' => 'sub_9', 'status' => 'past_due', 'metadata' => ['site_order_id' => (string) $order->id]]]])->assertOk();
        $this->assertSame('past_due', $order->fresh()->status);

        // Annullato prima della fine delle rate.
        $this->webhook(['type' => 'customer.subscription.deleted', 'data' => ['object' => ['id' => 'sub_9', 'status' => 'canceled', 'metadata' => ['site_order_id' => (string) $order->id]]]])->assertOk();
        $this->assertSame('canceled', $order->fresh()->status);

        // Fine naturale delle rate.
        $order->update(['status' => 'paying', 'completes_at' => now()->subHour()]);
        $this->webhook(['type' => 'customer.subscription.deleted', 'data' => ['object' => ['id' => 'sub_9', 'status' => 'canceled', 'metadata' => ['site_order_id' => (string) $order->id]]]])->assertOk();
        $this->assertSame('completed', $order->fresh()->status);
    }

    public function test_super_admin_sees_the_paid_orders_in_the_requests_page(): void
    {
        $order = SiteOrder::create([
            'name' => 'Maria Bianchi', 'phone' => '333 1234567', 'email' => 'maria@example.com', 'plan' => 'professionale', 'start_cents' => 100, 'trial_days' => 15,
            'installments' => 10, 'installment_cents' => 9900, 'package_cents' => 99000, 'status' => 'trial', 'paid_at' => now(),
            'trial_ends_at' => now()->addDays(15), 'completes_at' => now()->addMonths(10),
        ]);

        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($admin)->get('/admin/leads')->assertOk()->assertSee('Maria Bianchi')->assertSee('In prova')->assertSee('10 × 99 €');
    }
}
