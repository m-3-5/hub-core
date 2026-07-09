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

    /**
     * Addebita un importo extra (fuori quota) sulla carta già salvata dal tenant
     * quando si è abbonato — nessuna nuova richiesta di pagamento, "off-session".
     *
     * @return array{id: string, status: string}
     */
    public function chargeOffSession(Tenant $tenant, int $amountCents, string $description): array
    {
        if (! $tenant->stripe_customer_id) {
            throw new RuntimeException('Il tenant non ha ancora un metodo di pagamento salvato (nessun abbonamento attivo).');
        }

        $customer = $this->get('/v1/customers/'.$tenant->stripe_customer_id);
        $paymentMethodId = $customer['invoice_settings']['default_payment_method'] ?? null;

        if (! $paymentMethodId) {
            throw new RuntimeException('Nessun metodo di pagamento predefinito trovato per questo tenant.');
        }

        return $this->post('/v1/payment_intents', [
            'amount' => $amountCents,
            'currency' => 'eur',
            'customer' => $tenant->stripe_customer_id,
            'payment_method' => $paymentMethodId,
            'off_session' => 'true',
            'confirm' => 'true',
            'description' => $description,
        ]);
    }

    /** @return array<string, mixed> */
    private function get(string $path): array
    {
        try {
            $response = Http::withToken($this->secretKey)
                ->timeout(30)
                ->get('https://api.stripe.com'.$path)
                ->throw();
        } catch (RequestException $e) {
            $message = $e->response?->json('error.message') ?? $e->getMessage();

            throw new RuntimeException('Stripe ('.$path.'): '.$message, 0, $e);
        }

        return $response->json();
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
