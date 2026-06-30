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
    ): array {
        $product = $this->post('/v1/products', array_filter([
            'name' => $title,
            'description' => $description,
        ]));

        $price = $this->post('/v1/prices', [
            'product' => $product['id'],
            'unit_amount' => $amountCents,
            'currency' => strtolower($currency),
        ]);

        $link = $this->createPaymentLinkForPrice($price['id']);

        return [
            'product_id' => $product['id'],
            'price_id' => $price['id'],
            'payment_link_id' => $link['id'],
            'url' => $link['url'],
        ];
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
