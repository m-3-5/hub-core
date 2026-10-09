<?php

namespace M35\HubPayments\Support;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use M35\HubPayments\Models\PayableOrder;
use M35\HubPayments\Models\PayableService;
use M35\HubPayments\Notifications\PaymentReceivedNotification;
use Throwable;

/**
 * Registra un pagamento riuscito di Stripe Checkout per un tenant:
 * - carrello: l'ordine "pending" creato dalla cassa passa a "paid" con i dati del cliente;
 * - preventivo (Payment Link): il preventivo passa a "paid" e viene registrato l'ordine.
 * Idempotente: Stripe può consegnare lo stesso evento più volte, l'email parte una volta sola.
 */
class PaymentRecorder
{
    /**
     * @param  array<string, mixed>  $session  oggetto Checkout Session di Stripe
     */
    public static function record(Tenant $tenant, array $session): ?PayableOrder
    {
        if (($session['payment_status'] ?? null) !== 'paid') {
            return null; // metodi asincroni: si attende async_payment_succeeded
        }

        $order = null;
        $isNew = false;

        $orderId = $session['metadata']['hub_order_id'] ?? $session['client_reference_id'] ?? null;

        if ($orderId !== null && ctype_digit((string) $orderId)) {
            [$order, $isNew] = self::markCartOrder($tenant, (int) $orderId, $session);
        } elseif (! empty($session['payment_link'])) {
            [$order, $isNew] = self::markPaymentLink($tenant, (string) $session['payment_link'], $session);
        }

        if ($order && $isNew) {
            self::notify($tenant, $order);
        }

        return $order;
    }

    /**
     * @param  array<string, mixed>  $session
     * @return array{0: ?PayableOrder, 1: bool} ordine e se è stato appena segnato come pagato
     */
    private static function markCartOrder(Tenant $tenant, int $orderId, array $session): array
    {
        return DB::transaction(function () use ($tenant, $orderId, $session) {
            $order = PayableOrder::query()
                ->where('tenant_id', $tenant->id)
                ->where('id', $orderId)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                Log::warning('Webhook Stripe: ordine non trovato per il tenant', ['tenant' => $tenant->slug, 'order' => $orderId]);

                return [null, false];
            }

            if ($order->status === 'paid') {
                return [$order, false];
            }

            $order->fill(self::customerFields($session) + [
                'status' => 'paid',
                'paid_at' => now(),
                'stripe_session_id' => $order->stripe_session_id ?: ($session['id'] ?? null),
            ]);

            if (isset($session['amount_total']) && is_int($session['amount_total'])) {
                $order->amount_cents = $session['amount_total'];
            }

            $order->commission_cents = TenantCommission::compute($tenant, $order->channel, $order->amount_cents);
            $order->save();

            return [$order, true];
        });
    }

    /**
     * Pagamento tramite Payment Link: preventivo (pagabile una volta) oppure servizio/prodotto.
     * Le pagine pubbliche di inm35.it aggiungono client_reference_id=hub al link: così l'ordine è del canale "hub".
     *
     * @param  array<string, mixed>  $session
     * @return array{0: ?PayableOrder, 1: bool}
     */
    private static function markPaymentLink(Tenant $tenant, string $paymentLinkId, array $session): array
    {
        return DB::transaction(function () use ($tenant, $paymentLinkId, $session) {
            $quote = PayableService::query()
                ->where('tenant_id', $tenant->id)
                ->whereIn('type', ['quote', 'service', 'product'])
                ->where('stripe_payment_link_id', $paymentLinkId)
                ->lockForUpdate()
                ->first();

            if (! $quote) {
                return [null, false]; // link che non appartiene a una voce dell'hub
            }

            $isQuote = $quote->type === 'quote';
            $channel = ! $isQuote && ($session['client_reference_id'] ?? null) === TenantCommission::CHANNEL
                ? TenantCommission::CHANNEL
                : 'site';

            $existing = isset($session['id'])
                ? PayableOrder::query()->where('stripe_session_id', $session['id'])->first()
                : null;

            if ($existing) {
                return [$existing, false];
            }

            // Il preventivo si esaurisce con il pagamento; servizi e prodotti restano in vendita.
            if ($isQuote) {
                $quote->update(['status' => 'paid', 'paid_at' => now()]);
            }

            $amount = is_int($session['amount_total'] ?? null) ? $session['amount_total'] : $quote->amount_cents;

            $order = PayableOrder::create(self::customerFields($session) + [
                'tenant_id' => $tenant->id,
                'channel' => $channel,
                'status' => 'paid',
                'stripe_session_id' => $session['id'] ?? null,
                'items' => [[
                    'id' => $quote->id,
                    'type' => $quote->type,
                    'title' => $quote->title,
                    'quantity' => 1,
                    'unit_amount_cents' => $quote->amount_cents,
                ]],
                'currency' => $quote->currency,
                'amount_cents' => $amount,
                'commission_cents' => TenantCommission::compute($tenant, $channel, $amount),
                'paid_at' => now(),
            ]);

            return [$order, true];
        });
    }

    /**
     * @param  array<string, mixed>  $session
     * @return array<string, string|null>
     */
    private static function customerFields(array $session): array
    {
        $details = $session['customer_details'] ?? [];

        return array_filter([
            'customer_email' => $details['email'] ?? $session['customer_email'] ?? null,
            'customer_name' => $details['name'] ?? null,
            'customer_phone' => $details['phone'] ?? null,
        ], fn ($v) => is_string($v) && $v !== '');
    }

    private static function notify(Tenant $tenant, PayableOrder $order): void
    {
        $recipients = $tenant->users;

        if ($recipients->isEmpty()) {
            return;
        }

        try {
            Notification::send($recipients, new PaymentReceivedNotification($tenant, $order));
        } catch (Throwable $e) {
            // L'ordine è già registrato: un problema di posta non deve far ripetere il webhook a Stripe.
            Log::warning('Email pagamento ricevuto non inviata', ['tenant' => $tenant->slug, 'message' => $e->getMessage()]);
        }
    }
}
