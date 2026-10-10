<?php

namespace App\Services;

use App\Models\SiteOrder;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Pagamenti della promo «1 € per partire» sul conto Stripe di M 3.5 (lo stesso della fatturazione hub):
 * checkout con 1 € subito + abbonamento a rate che parte dopo i giorni di prova e si ferma da solo a fine rate.
 */
class SiteOrderBilling
{
    public function __construct(private readonly string $secretKey) {}

    /** Importo di una rata in centesimi: prezzo del pacchetto diviso in rate, arrotondato per eccesso. */
    public static function installmentCents(int $packageEur, int $months): int
    {
        return (int) ceil($packageEur * 100 / max(1, $months));
    }

    /** @return array{id: string, url: string} */
    public function createCheckout(SiteOrder $order): array
    {
        $plan = $order->planLabel();

        return $this->post('/v1/checkout/sessions', [
            'mode' => 'subscription',
            'customer_email' => $order->email,
            'locale' => 'it',
            'line_items[0][quantity]' => 1,
            'line_items[0][price_data][currency]' => 'eur',
            'line_items[0][price_data][unit_amount]' => $order->start_cents,
            'line_items[0][price_data][product_data][name]' => 'Avvio sito web '.$plan.' — M 3.5',
            'line_items[1][quantity]' => 1,
            'line_items[1][price_data][currency]' => 'eur',
            'line_items[1][price_data][unit_amount]' => $order->installment_cents,
            'line_items[1][price_data][recurring][interval]' => 'month',
            'line_items[1][price_data][product_data][name]' => 'Sito web '.$plan.' — rata mensile ('.$order->installments.' rate)',
            'subscription_data[trial_period_days]' => (string) $order->trial_days,
            'subscription_data[metadata][site_order_id]' => (string) $order->id,
            'metadata[site_order_id]' => (string) $order->id,
            'client_reference_id' => 'site-'.$order->id,
            'custom_text[submit][message]' => 'Oggi paghi '.SiteOrder::euro($order->start_cents).' €. Dopo '.$order->trial_days.' giorni partono '.$order->installments.' rate mensili da '.SiteOrder::euro($order->installment_cents).' € (+ IVA) e poi il sito è tuo. '.config('landing.start.terms'),
            'success_url' => route('landing.web').'?avvio=ok#avvio-ok',
            'cancel_url' => route('landing.web').'?avvio=annullato#parti',
        ]);
    }

    /** Fa fermare l'abbonamento alla data indicata (fine delle rate). */
    public function cancelAt(string $subscriptionId, \DateTimeInterface $when): void
    {
        $this->post('/v1/subscriptions/'.$subscriptionId, ['cancel_at' => (string) $when->getTimestamp()]);
    }

    /** @param  array<string, mixed>  $fields */
    private function post(string $path, array $fields): array
    {
        try {
            return Http::withToken($this->secretKey)->asForm()->timeout(30)
                ->post(rtrim((string) config('services.hub_billing.api_base', 'https://api.stripe.com'), '/').$path, $fields)
                ->throw()->json();
        } catch (RequestException $e) {
            throw new RuntimeException('Stripe ('.$path.'): '.($e->response?->json('error.message') ?? $e->getMessage()), 0, $e);
        }
    }
}
