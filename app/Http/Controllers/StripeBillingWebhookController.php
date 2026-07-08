<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class StripeBillingWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $secret = config('services.hub_billing.webhook_secret');
        $payload = $request->getContent();

        if (! $secret || ! $this->verifySignature($payload, $request->header('Stripe-Signature'), $secret)) {
            return response('Invalid signature', 401);
        }

        $event = json_decode($payload, true);
        $type = $event['type'] ?? null;
        $object = $event['data']['object'] ?? [];

        match ($type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($object),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($object),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($object),
            default => null,
        };

        return response('OK', 200);
    }

    /** @param  array<string, mixed>  $session */
    private function handleCheckoutCompleted(array $session): void
    {
        $tenant = Tenant::find($session['metadata']['tenant_id'] ?? $session['client_reference_id'] ?? null);

        if (! $tenant) {
            Log::warning('Stripe billing webhook: tenant non trovato', ['session' => $session['id'] ?? null]);

            return;
        }

        $tenant->forceFill([
            'stripe_customer_id' => $session['customer'] ?? $tenant->stripe_customer_id,
            'stripe_subscription_id' => $session['subscription'] ?? $tenant->stripe_subscription_id,
            'billing_interval' => $session['metadata']['interval'] ?? $tenant->billing_interval,
            'subscription_status' => 'active',
        ])->save();
    }

    /** @param  array<string, mixed>  $subscription */
    private function handleSubscriptionUpdated(array $subscription): void
    {
        $tenant = $this->tenantForSubscription($subscription);

        if (! $tenant) {
            return;
        }

        $status = match ($subscription['status'] ?? null) {
            'active', 'trialing' => 'active',
            'past_due', 'unpaid', 'incomplete' => 'past_due',
            default => 'canceled',
        };

        $tenant->forceFill(['subscription_status' => $status])->save();
    }

    /** @param  array<string, mixed>  $subscription */
    private function handleSubscriptionDeleted(array $subscription): void
    {
        $tenant = $this->tenantForSubscription($subscription);

        $tenant?->forceFill(['subscription_status' => 'canceled'])->save();
    }

    /** @param  array<string, mixed>  $subscription */
    private function tenantForSubscription(array $subscription): ?Tenant
    {
        $tenantId = $subscription['metadata']['tenant_id'] ?? null;

        if ($tenantId) {
            return Tenant::find($tenantId);
        }

        return Tenant::where('stripe_subscription_id', $subscription['id'] ?? null)->first();
    }

    private function verifySignature(string $payload, ?string $header, string $secret): bool
    {
        if (! $header) {
            return false;
        }

        $parts = [];
        foreach (explode(',', $header) as $chunk) {
            [$key, $value] = array_pad(explode('=', $chunk, 2), 2, null);
            $parts[$key][] = $value;
        }

        $timestamp = $parts['t'][0] ?? null;
        $signatures = $parts['v1'] ?? [];

        if (! $timestamp || empty($signatures)) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, (string) $signature)) {
                return true;
            }
        }

        return false;
    }
}
