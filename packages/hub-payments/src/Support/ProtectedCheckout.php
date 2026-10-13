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
    public static function begin(Tenant $tenant, PayableService $service, int $quantity, ?string $email, ?string $buyerIp = null): string
    {
        return self::checkout(
            $tenant,
            ['id' => $service->id, 'type' => $service->type, 'title' => $service->title],
            $service->amount_cents,
            max(1, min(20, $quantity)),
            $service->currency,
            $service->stripeImageUrl(),
            route('services.public.show', [$tenant, $service]),
            $email,
            $buyerIp,
        );
    }

    /** Acquisto di un oggetto da un annuncio (privati e aziende): un solo pezzo, prezzo deciso dall'hub. */
    public static function beginForAd(Tenant $tenant, \App\Models\ClassifiedAd $ad, ?string $email, ?string $buyerIp = null): string
    {
        return self::checkout(
            $tenant,
            ['id' => $ad->id, 'type' => 'classified', 'title' => $ad->title],
            $ad->priceCents(),
            1,
            'eur',
            $ad->coverUrl() && str_starts_with($ad->coverUrl(), 'http') ? $ad->coverUrl() : null,
            route('classifieds.show', [$tenant, $ad]),
            $email,
            $buyerIp,
        );
    }

    /**
     * @param  array{id: int, type: string, title: string}  $item
     *
     * @throws RuntimeException
     */
    private static function checkout(Tenant $tenant, array $item, int $unitCents, int $quantity, string $currency, ?string $image, string $cancelUrl, ?string $email, ?string $buyerIp): string
    {
        $accountId = TenantConnect::accountId($tenant);

        $order = PayableOrder::create([
            'tenant_id' => $tenant->id,
            'channel' => TenantCommission::CHANNEL,
            'flow' => 'protected',
            'status' => 'pending',
            'connect_account_id' => $accountId,
            'items' => [$item + ['quantity' => $quantity, 'unit_amount_cents' => $unitCents]],
            'currency' => $currency,
            'amount_cents' => $unitCents * $quantity,
            'customer_email' => $email,
            'buyer_token' => Str::random(40),
            // Il compratore ha spuntato le condizioni di acquisto protetto sulla pagina prima di arrivare qui.
            'buyer_terms_version' => BuyerTerms::VERSION,
            'buyer_terms_accepted_at' => now(),
            'buyer_terms_ip' => $buyerIp,
        ]);

        $fields = [
            'mode' => 'payment',
            'line_items[0][quantity]' => $quantity,
            'line_items[0][price_data][currency]' => $currency,
            'line_items[0][price_data][unit_amount]' => $unitCents,
            'line_items[0][price_data][product_data][name]' => Str::limit($item['title'], 120, ''),
            'success_url' => route('protected.order.show', $order->buyer_token).'?pagato=1',
            'cancel_url' => $cancelUrl,
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
            'payment_intent_data[description]' => Str::limit($item['title'].' — '.$tenant->name, 200, ''),
            // Testo informativo accanto al pulsante «Paga» di Stripe.
            'custom_text[submit][message]' => 'Il tuo pagamento è protetto da Hub Core: '.Str::limit($tenant->name, 60, '')
                .' lo riceve dopo la consegna (tua conferma o dopo '.self::holdDays().' giorni senza segnalazioni). '
                .'Se qualcosa non va, segnalalo dalla pagina del tuo ordine. [Condizioni di acquisto]('.route('terms.buyer').')',
        ];

        if ($image) {
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
