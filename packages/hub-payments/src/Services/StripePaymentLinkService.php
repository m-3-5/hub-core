<?php

namespace M35\HubPayments\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class StripePaymentLinkService
{
    public function __construct(private readonly string $secretKey) {}

    /**
     * @return array{product_id: string, price_id: string, payment_link_id: string, url: string}
     */
    public function createPaymentLink(
        string $title,
        ?string $description,
        int $amountCents,
        string $currency = 'eur',
        ?string $imageUrl = null,
    ): array {
        $product = $this->createProduct($title, $description, $imageUrl);
        $price = $this->createPrice($product['id'], $amountCents, $currency);
        $link = $this->createPaymentLinkForPrice($price['id']);

        return [
            'product_id' => $product['id'],
            'price_id' => $price['id'],
            'payment_link_id' => $link['id'],
            'url' => $link['url'],
        ];
    }

    /**
     * Preventivo: prodotto + prezzo + Payment Link pagabile una volta sola (restrictions.completed_sessions.limit=1).
     *
     * @param  array<string, string>  $metadata
     * @return array{product_id: string, price_id: string, payment_link_id: string, url: string}
     */
    public function createSingleUsePaymentLink(
        string $title,
        ?string $description,
        int $amountCents,
        string $currency = 'eur',
        array $metadata = [],
    ): array {
        $product = $this->createProduct($title, $description);
        $price = $this->createPrice($product['id'], $amountCents, $currency);

        $extra = ['restrictions[completed_sessions][limit]' => 1];

        foreach ($metadata as $key => $value) {
            $extra["metadata[$key]"] = $value;
        }

        $link = $this->createPaymentLinkForPrice($price['id'], $extra);

        return [
            'product_id' => $product['id'],
            'price_id' => $price['id'],
            'payment_link_id' => $link['id'],
            'url' => $link['url'],
        ];
    }

    /**
     * Registra un webhook sul conto Stripe del tenant. Il segreto di firma ("secret") viene restituito solo qui.
     *
     * @param  array<int, string>  $events
     * @return array{id: string, secret: string}
     */
    public function createWebhookEndpoint(string $url, array $events, string $description): array
    {
        $fields = ['url' => $url, 'description' => $description];

        foreach (array_values($events) as $i => $event) {
            $fields["enabled_events[$i]"] = $event;
        }

        $endpoint = $this->post('/v1/webhook_endpoints', $fields);

        if (empty($endpoint['id']) || empty($endpoint['secret'])) {
            throw new RuntimeException('Stripe: webhook creato senza segreto di firma.');
        }

        return ['id' => $endpoint['id'], 'secret' => $endpoint['secret']];
    }

    public function deleteWebhookEndpoint(string $endpointId): void
    {
        try {
            Http::withToken($this->secretKey)
                ->timeout(30)
                ->delete('https://api.stripe.com/v1/webhook_endpoints/'.$endpointId)
                ->throw();
        } catch (RequestException $e) {
            throw new RuntimeException('Stripe (webhook_endpoints): '.($e->response?->json('error.message') ?? $e->getMessage()), 0, $e);
        }
    }

    public function updateProduct(string $productId, string $title, ?string $description, ?string $imageUrl = null): void
    {
        $fields = array_filter([
            'name' => $title,
            'description' => $description,
        ], fn ($value) => $value !== null);

        if ($imageUrl !== null) {
            $fields['images[0]'] = $imageUrl;
        }

        $this->post('/v1/products/'.$productId, $fields);
    }

    /**
     * @return array{id: string, unit_amount: int}
     */
    public function createPrice(string $productId, int $amountCents, string $currency = 'eur'): array
    {
        return $this->post('/v1/prices', [
            'product' => $productId,
            'unit_amount' => $amountCents,
            'currency' => strtolower($currency),
        ]);
    }

    public function updatePaymentLinkPrice(string $paymentLinkId, string $priceId): void
    {
        $this->post('/v1/payment_links/'.$paymentLinkId, [
            'line_items[0][price]' => $priceId,
            'line_items[0][quantity]' => 1,
        ]);
    }

    /**
     * Crea una Checkout Session (carrello): $fields già nel formato form di Stripe.
     *
     * @param  array<string, mixed>  $fields
     * @return array{id: string, url: string}
     */
    public function createCheckoutSession(array $fields): array
    {
        $session = $this->post('/v1/checkout/sessions', $fields);

        if (empty($session['id']) || empty($session['url'])) {
            throw new RuntimeException('Stripe: sessione di pagamento creata senza indirizzo.');
        }

        return ['id' => $session['id'], 'url' => $session['url']];
    }

    public function deactivatePaymentLink(string $paymentLinkId): void
    {
        $this->post('/v1/payment_links/'.$paymentLinkId, [
            'active' => 'false',
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listPaymentLinks(): array
    {
        $links = $this->get('/v1/payment_links', ['limit' => 100])['data'] ?? [];

        foreach ($links as &$link) {
            $link['line_items']['data'] = $this->fetchLineItems($link['id']);
        }

        return $links;
    }

    /** @return array<string, mixed> */
    public function getPaymentLink(string $paymentLinkId): array
    {
        $link = $this->get('/v1/payment_links/'.$paymentLinkId);
        $link['line_items']['data'] = $this->fetchLineItems($paymentLinkId);

        return $link;
    }

    /** @return array<int, array<string, mixed>> */
    private function fetchLineItems(string $paymentLinkId): array
    {
        try {
            return $this->get('/v1/payment_links/'.$paymentLinkId.'/line_items', [
                'expand' => ['data.price.product'],
            ])['data'] ?? [];
        } catch (RuntimeException) {
            return [];
        }
    }

    /**
     * @return array{id: string, url: string}
     */
    public function replacePaymentLink(string $oldPaymentLinkId, string $priceId): array
    {
        $link = $this->createPaymentLinkForPrice($priceId);

        try {
            $this->deactivatePaymentLink($oldPaymentLinkId);
        } catch (RuntimeException) {
            // Il vecchio link può essere già disattivato o rimosso da Stripe.
        }

        return [
            'id' => $link['id'],
            'url' => $link['url'],
        ];
    }

    /** @return array<string, mixed> */
    private function createProduct(string $title, ?string $description, ?string $imageUrl = null): array
    {
        $fields = array_filter([
            'name' => $title,
            'description' => $description,
        ]);

        if ($imageUrl) {
            $fields['images[0]'] = $imageUrl;
        }

        return $this->post('/v1/products', $fields);
    }

    /**
     * @param  array<string, mixed>  $extra  campi aggiuntivi (restrizioni, metadata)
     * @return array<string, mixed>
     */
    private function createPaymentLinkForPrice(string $priceId, array $extra = []): array
    {
        $base = ['line_items[0][price]' => $priceId, 'line_items[0][quantity]' => 1] + $extra;

        try {
            return $this->post('/v1/payment_links', $base + ['automatic_payment_methods[enabled]' => 'true']);
        } catch (RuntimeException) {
            return $this->post('/v1/payment_links', $base + ['payment_method_types[0]' => 'card']);
        }
    }

    /** @param  array<string, mixed>  $query */
    private function get(string $path, array $query = []): array
    {
        try {
            $response = Http::withToken($this->secretKey)
                ->timeout(30)
                ->get('https://api.stripe.com'.$path, $query)
                ->throw();
        } catch (RequestException $e) {
            $message = $e->response?->json('error.message') ?? $e->getMessage();

            throw new RuntimeException('Stripe ('.$path.'): '.$message, 0, $e);
        }

        return $response->json();
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

        $data = $response->json();

        if ($path === '/v1/payment_links' && empty($data['url'])) {
            throw new RuntimeException('Stripe: Payment Link creato ma senza URL.');
        }

        return $data;
    }
}
