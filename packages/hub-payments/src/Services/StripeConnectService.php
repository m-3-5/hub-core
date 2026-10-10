<?php

namespace M35\HubPayments\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Stripe Connect (account Express) sul conto Stripe di M 3.5: il venditore si collega una volta, poi i pagamenti
 * «protetti» passano da noi e vengono girati al venditore dopo la consegna. M 3.5 non maneggia mai i soldi
 * fuori da Stripe: identità (KYC), IBAN e carte dei venditori li raccoglie Stripe.
 */
class StripeConnectService
{
    public function __construct(private readonly string $platformSecretKey) {}

    public static function isAvailable(): bool
    {
        return (bool) config('services.hub_billing.secret_key');
    }

    public static function make(): self
    {
        return new self((string) config('services.hub_billing.secret_key'));
    }

    private function base(): string
    {
        return rtrim((string) config('hub-payments.stripe_api_base', 'https://api.stripe.com'), '/');
    }

    /** @return array<string, mixed> account Stripe appena creato */
    public function createExpressAccount(int $tenantId, string $tenantName, ?string $email, string $country = 'IT'): array
    {
        return $this->post('/v1/accounts', array_filter([
            'type' => 'express',
            'country' => $country,
            'email' => $email,
            'business_profile[name]' => $tenantName,
            'capabilities[card_payments][requested]' => 'true',
            'capabilities[transfers][requested]' => 'true',
            'metadata[tenant_id]' => (string) $tenantId,
        ], fn ($v) => $v !== null && $v !== ''));
    }

    /** Link (monouso) alla procedura guidata di Stripe: dati dell'attività, identità, IBAN o carta per ricevere i soldi. */
    public function onboardingLink(string $accountId, string $refreshUrl, string $returnUrl): string
    {
        return (string) $this->post('/v1/account_links', [
            'account' => $accountId,
            'refresh_url' => $refreshUrl,
            'return_url' => $returnUrl,
            'type' => 'account_onboarding',
        ])['url'];
    }

    /** @return array<string, mixed> */
    public function retrieve(string $accountId): array
    {
        try {
            return Http::withToken($this->platformSecretKey)->timeout(30)->get($this->base().'/v1/accounts/'.$accountId)->throw()->json();
        } catch (RequestException $e) {
            throw new RuntimeException('Stripe: '.($e->response?->json('error.message') ?? $e->getMessage()), 0, $e);
        }
    }

    /** Pagina Stripe dove il venditore vede saldo e bonifici ricevuti. */
    public function dashboardLink(string $accountId): string
    {
        return (string) $this->post('/v1/accounts/'.$accountId.'/login_links', [])['url'];
    }

    /** @param  array<string, mixed>  $fields */
    private function post(string $path, array $fields): array
    {
        try {
            return Http::withToken($this->platformSecretKey)->asForm()->timeout(30)->post($this->base().$path, $fields)->throw()->json();
        } catch (RequestException $e) {
            throw new RuntimeException('Stripe: '.($e->response?->json('error.message') ?? $e->getMessage()), 0, $e);
        }
    }
}
