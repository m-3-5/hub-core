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

        $methods = config('hub-payments.payment_methods', ['card', 'klarna']);
        $linkPayload = [
            'line_items[0][price]' => $price['id'],
            'line_items[0][quantity]' => 1,
        ];

        foreach (array_values($methods) as $index => $method) {
            $linkPayload['payment_method_types['.$index.']'] = $method;
        }

        $link = $this->post('/v1/payment_links', $linkPayload);

        return [
            'product_id' => $product['id'],
            'price_id' => $price['id'],
            'payment_link_id' => $link['id'],
            'url' => $link['url'],
        ];
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

            throw new RuntimeException('Stripe: '.$message, 0, $e);
        }

        return $response->json();
    }
}
