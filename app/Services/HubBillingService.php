<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HubBillingService
{
    public function __construct(private readonly string $secretKey) {}

    /**
     * @return array{id: string, url: string}
     */
    public function createCheckoutSession(Tenant $tenant, string $interval): array
    {
        $priceId = $interval === 'year'
            ? config('services.hub_billing.price_annual')
            : config('services.hub_billing.price_monthly');

        if (! $priceId) {
            throw new RuntimeException('Prezzo Stripe non configurato per l\'intervallo "'.$interval.'".');
        }

        $customerId = $tenant->stripe_customer_id ?? $this->createCustomer($tenant)['id'];

        if (! $tenant->stripe_customer_id) {
            $tenant->forceFill(['stripe_customer_id' => $customerId])->save();
        }

        return $this->post('/v1/checkout/sessions', [
            'mode' => 'subscription',
            'customer' => $customerId,
            'line_items[0][price]' => $priceId,
            'line_items[0][quantity]' => 1,
            'success_url' => route('admin.billing.show', $tenant).'?checkout=success',
            'cancel_url' => route('admin.billing.show', $tenant).'?checkout=cancelled',
            'client_reference_id' => (string) $tenant->id,
            'metadata[tenant_id]' => (string) $tenant->id,
            'metadata[interval]' => $interval,
            'subscription_data[metadata][tenant_id]' => (string) $tenant->id,
        ]);
    }

    public function cancelSubscription(string $subscriptionId): void
    {
        $this->post('/v1/subscriptions/'.$subscriptionId, ['cancel_at_period_end' => 'true']);
    }

    /** @return array{id: string} */
    private function createCustomer(Tenant $tenant): array
    {
        return $this->post('/v1/customers', [
            'name' => $tenant->name,
            'metadata[tenant_id]' => (string) $tenant->id,
            'metadata[tenant_slug]' => $tenant->slug,
        ]);
    }

    /** @param  array<string, mixed>  $fields */
    private function post(string $path, array $fields): array
    {
        try {
            $response = Http::withToken($this->secretKey)
                ->asForm()
                ->timeout(30)
                ->post('https://api.stripe.com'.$path, $fields)
                ->throw();
        } catch (RequestException $e) {
            $message = $e->response?->json('error.message') ?? $e->getMessage();

            throw new RuntimeException('Stripe ('.$path.'): '.$message, 0, $e);
        }

        return $response->json();
    }
}
