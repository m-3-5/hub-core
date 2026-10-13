<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Il webhook di fatturazione risponde sia a /api/stripe/webhook sia a /stripe/billing/webhook (indirizzo registrato in Stripe). */
class StripeWebhookEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function send(string $path, string $secret, array $object): \Illuminate\Testing\TestResponse
    {
        $payload = json_encode(['type' => 'customer.subscription.updated', 'data' => ['object' => $object]]);
        $t = time();

        return $this->call('POST', $path, [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$t},v1=".hash_hmac('sha256', $t.'.'.$payload, $secret),
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }

    private function tenant(): Tenant
    {
        return Tenant::create(['name' => 'Beauty', 'slug' => 'beauty', 'type' => 'azienda', 'plan' => 'demo', 'stripe_subscription_id' => 'sub_1', 'subscription_status' => 'trialing']);
    }

    public function test_the_address_registered_in_stripe_is_served_and_updates_the_subscription(): void
    {
        config(['services.hub_billing.webhook_secret' => 'whsec_live']);
        $tenant = $this->tenant();

        $this->send('/stripe/billing/webhook', 'whsec_live', ['id' => 'sub_1', 'status' => 'active', 'metadata' => ['tenant_id' => (string) $tenant->id]])->assertOk();

        $this->assertSame('active', $tenant->fresh()->subscription_status);
    }

    public function test_both_addresses_accept_the_same_signed_event(): void
    {
        config(['services.hub_billing.webhook_secret' => 'whsec_live']);
        $tenant = $this->tenant();

        $this->send('/api/stripe/webhook', 'whsec_live', ['id' => 'sub_1', 'status' => 'past_due', 'metadata' => ['tenant_id' => (string) $tenant->id]])->assertOk();

        $this->assertSame('past_due', $tenant->fresh()->subscription_status);
    }

    public function test_live_and_test_mode_secrets_can_both_be_configured(): void
    {
        config(['services.hub_billing.webhook_secret' => 'whsec_live, whsec_test']);
        $tenant = $this->tenant();

        $this->send('/stripe/billing/webhook', 'whsec_test', ['id' => 'sub_1', 'status' => 'active', 'metadata' => ['tenant_id' => (string) $tenant->id]])->assertOk();
        $this->assertSame('active', $tenant->fresh()->subscription_status);

        $this->send('/stripe/billing/webhook', 'whsec_live', ['id' => 'sub_1', 'status' => 'past_due', 'metadata' => ['tenant_id' => (string) $tenant->id]])->assertOk();
        $this->assertSame('past_due', $tenant->fresh()->subscription_status);
    }

    public function test_a_wrong_or_missing_signature_is_rejected_and_nothing_changes(): void
    {
        config(['services.hub_billing.webhook_secret' => 'whsec_live']);
        $tenant = $this->tenant();

        $this->send('/stripe/billing/webhook', 'whsec_other', ['id' => 'sub_1', 'status' => 'canceled'])->assertStatus(401);
        $this->postJson('/stripe/billing/webhook', ['type' => 'x'])->assertStatus(401);

        config(['services.hub_billing.webhook_secret' => null]);
        $this->send('/stripe/billing/webhook', '', ['id' => 'sub_1', 'status' => 'canceled'])->assertStatus(401);

        $this->assertSame('trialing', $tenant->fresh()->subscription_status);
    }

    public function test_a_sellers_direct_sale_with_a_numeric_reference_never_touches_a_tenant_subscription(): void
    {
        config(['services.hub_billing.webhook_secret' => 'whsec_live']);
        $tenant = $this->tenant();
        $payload = json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => [
            'id' => 'cs_live_direct', 'payment_status' => 'paid',
            'client_reference_id' => (string) $tenant->id, // numero d'ordine del venditore che coincide con un id di tenant
            'metadata' => [],
        ]]]);
        $t = time();

        $this->call('POST', '/stripe/billing/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$t},v1=".hash_hmac('sha256', $t.'.'.$payload, 'whsec_live'),
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk();

        $this->assertSame('trialing', $tenant->fresh()->subscription_status);
        $this->assertSame('sub_1', $tenant->fresh()->stripe_subscription_id);
    }
}
