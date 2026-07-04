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
            try {
                $link['line_items']['data'] = $this->get('/v1/payment_links/'.$link['id'].'/line_items', [
                    'expand' => ['data.price.product'],
                ])['data'] ?? [];
            } catch (RuntimeException) {
                $link['line_items']['data'] = [];
            }
        }

        return $links;
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

    /** @return array<string, mixed> */
    private function createPaymentLinkForPrice(string $priceId): array
    {
        try {
            return $this->post('/v1/payment_links', [
                'line_items[0][price]' => $priceId,
                'line_items[0][quantity]' => 1,
                'automatic_payment_methods[enabled]' => 'true',
            ]);
        } catch (RuntimeException) {
            return $this->post('/v1/payment_links', [
                'line_items[0][price]' => $priceId,
                'line_items[0][quantity]' => 1,
                'payment_method_types[0]' => 'card',
            ]);
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
