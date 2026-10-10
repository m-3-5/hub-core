<?php

namespace M35\HubPayments\Support;

use App\Models\Tenant;
use Illuminate\Support\Str;
use M35\HubPayments\Models\PayableOrder;
use M35\HubPayments\Models\PayableService;
use M35\HubPayments\Services\StripeConnectService;
use M35\HubPayments\Services\StripePaymentLinkService;
use RuntimeException;

/**
 * Cassa dei pagamenti protetti sulle pagine pubbliche di inm35.it: il cliente paga sul conto Stripe di M 3.5
 * (sessione di pagamento creata al momento, con l'importo deciso dall'hub) e i soldi restano trattenuti
 * fino alla conferma del cliente o allo scadere dei giorni di attesa. Il venditore deve essere collegato (Stripe Connect)
 * e avere accettato le condizioni economiche.
 */
class ProtectedCheckout
{
    public static function holdDays(): int
    {
        return max(1, (int) config('hub-payments.protected.hold_days', 7));
    }

    public static function available(Tenant $tenant): bool
    {
        return StripeConnectService::isAvailable() && TenantConnect::isReady($tenant) && SellerTerms::accepted($tenant);
    }

    /**
     * Crea l'ordine in attesa e la sessione di pagamento Stripe; restituisce l'indirizzo dove mandare il cliente.
     *
     * @throws RuntimeException se Stripe non risponde o la risposta non è valida
     */
    public static function begin(Tenant $tenant, PayableService $service, int $quantity, ?string $email): string
    {
        $accountId = TenantConnect::accountId($tenant);
        $quantity = max(1, min(20, $quantity));

        $order = PayableOrder::create([
            'tenant_id' => $tenant->id,
            'channel' => TenantCommission::CHANNEL,
            'flow' => 'protected',
            'status' => 'pending',
            'connect_account_id' => $accountId,
            'items' => [[
                'id' => $service->id,
                'type' => $service->type,
                'title' => $service->title,
                'quantity' => $quantity,
                'unit_amount_cents' => $service->amount_cents,
            ]],
            'currency' => $service->currency,
            'amount_cents' => $service->amount_cents * $quantity,
            'customer_email' => $email,
            'buyer_token' => Str::random(40),
        ]);

        $fields = [
            'mode' => 'payment',
            'line_items[0][quantity]' => $quantity,
            'line_items[0][price_data][currency]' => $service->currency,
            'line_items[0][price_data][unit_amount]' => $service->amount_cents,
            'line_items[0][price_data][product_data][name]' => Str::limit($service->title, 120, ''),
            'success_url' => route('protected.order.show', $order->buyer_token).'?pagato=1',
            'cancel_url' => route('services.public.show', [$tenant, $service]),
            // Il telefono serve per ritiro o prenotazione (nessuna spedizione).
            'phone_number_collection[enabled]' => 'true',
            'client_reference_id' => (string) $order->id,
            'metadata[hub_order_id]' => (string) $order->id,
            'metadata[hub_tenant]' => $tenant->slug,
            'metadata[hub_flow]' => 'protected',
            'payment_intent_data[metadata][hub_order_id]' => (string) $order->id,
            'payment_intent_data[metadata][hub_flow]' => 'protected',
            // Lega il pagamento al futuro trasferimento verso il venditore.
            'payment_intent_data[transfer_group]' => 'order_'.$order->id,
            'payment_intent_data[description]' => Str::limit($service->title.' — '.$tenant->name, 200, ''),
        ];

        if ($image = $service->stripeImageUrl()) {
            $fields['line_items[0][price_data][product_data][images][0]'] = $image;
        }

        if ($email) {
            $fields['customer_email'] = $email;
        }

        try {
            $session = (new StripePaymentLinkService((string) config('services.hub_billing.secret_key')))->createCheckoutSession($fields);
        } catch (RuntimeException $e) {
            $order->delete();

            throw $e;
        }

        $host = strtolower((string) parse_url($session['url'], PHP_URL_HOST));

        if ($host !== 'stripe.com' && ! str_ends_with($host, '.stripe.com')) {
            $order->delete();

            throw new RuntimeException('Risposta di pagamento non valida.');
        }

        $order->update(['stripe_session_id' => $session['id']]);

        return $session['url'];
    }
}
