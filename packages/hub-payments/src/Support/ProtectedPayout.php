<?php

namespace M35\HubPayments\Support;

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\NewTicketNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use M35\HubPayments\Models\PayableOrder;
use M35\HubPayments\Notifications\BuyerOrderUpdateNotification;
use M35\HubPayments\Notifications\ProtectedProblemNotification;
use M35\HubPayments\Notifications\ProtectedReleasedNotification;
use M35\HubPayments\Services\StripeConnectService;
use RuntimeException;
use Throwable;

/**
 * Vita di un pagamento protetto dopo l'incasso: rilascio al venditore (con la commissione reale di Stripe già detratta),
 * segnalazione di un problema del compratore (blocca il rilascio) e rimborso.
 * Ogni operazione è idempotente: si blocca la riga dell'ordine e le richieste a Stripe hanno una chiave di idempotenza.
 */
class ProtectedPayout
{
    /**
     * Gira al venditore l'incasso meno commissione Stripe e commissione Hub Core.
     * Se il pagamento risulta rimborsato o contestato su Stripe, non rilascia e blocca l'ordine.
     *
     * @return bool true se i soldi sono stati girati ora
     *
     * @throws RuntimeException se Stripe non risponde: l'ordine resta «trattenuto» e si riprova
     */
    public static function release(PayableOrder $order): bool
    {
        $released = DB::transaction(function () use ($order): ?PayableOrder {
            $locked = PayableOrder::query()->lockForUpdate()->find($order->id);

            if (! $locked || ! $locked->isHeld()) {
                return null; // già rilasciato, bloccato o rimborsato: niente da fare
            }

            if (! $locked->stripe_payment_intent_id || ! $locked->connect_account_id) {
                throw new RuntimeException('Ordine senza riferimenti Stripe: impossibile rilasciare.');
            }

            $connect = StripeConnectService::make();
            $payment = $connect->paymentIntent($locked->stripe_payment_intent_id);
            $charge = $payment['latest_charge'] ?? null;
            $balance = is_array($charge) ? ($charge['balance_transaction'] ?? null) : null;

            if (! is_array($charge) || empty($charge['id']) || ! is_array($balance) || ! isset($balance['fee'])) {
                throw new RuntimeException('Stripe non ha ancora i dati dell\'addebito: si riprova più tardi.');
            }

            if (! empty($charge['refunded']) || ! empty($charge['disputed']) || (int) ($charge['amount_refunded'] ?? 0) > 0) {
                self::freezeLocked($locked, ! empty($charge['disputed'])
                    ? 'Contestazione con la carta aperta presso la banca del compratore.'
                    : 'Il pagamento risulta rimborsato su Stripe.');

                return null;
            }

            $paid = (int) ($charge['amount'] ?? $locked->amount_cents);
            $fee = (int) $balance['fee'];
            $payout = max(0, $paid - $fee - (int) $locked->commission_cents);
            $transferId = null;

            if ($payout > 0) {
                $transfer = $connect->createTransfer([
                    'amount' => $payout,
                    'currency' => $locked->currency,
                    'destination' => $locked->connect_account_id,
                    'source_transaction' => $charge['id'],
                    'transfer_group' => 'order_'.$locked->id,
                    'description' => 'Ordine #'.$locked->id.' — '.($locked->tenant?->name ?? ''),
                    'metadata[hub_order_id]' => (string) $locked->id,
                ], 'hub-release-'.$locked->id);

                $transferId = (string) ($transfer['id'] ?? '');
            }

            $locked->forceFill([
                'payout_status' => 'released',
                'released_at' => now(),
                'stripe_charge_id' => $charge['id'],
                'stripe_transfer_id' => $transferId ?: null,
                'stripe_fee_cents' => $fee,
                'payout_cents' => $payout,
            ])->save();

            return $locked;
        });

        if (! $released) {
            return false;
        }

        self::notifySellers($released, new ProtectedReleasedNotification($released->tenant, $released));
        self::notifyBuyer($released, 'released');

        return true;
    }

    /**
     * Il compratore segnala un problema: il rilascio si ferma e si avvisano venditore e team Hub Core.
     *
     * @return bool true se l'ordine è stato bloccato ora
     */
    public static function reportProblem(PayableOrder $order, string $message): bool
    {
        $frozen = DB::transaction(function () use ($order, $message): ?PayableOrder {
            $locked = PayableOrder::query()->lockForUpdate()->find($order->id);

            if (! $locked || ! $locked->isProtected() || $locked->status !== 'paid') {
                return null;
            }

            // Anche dopo il rilascio si registra la segnalazione (il team interviene a mano); si blocca solo ciò che è ancora trattenuto.
            $locked->forceFill([
                'problem_reported_at' => now(),
                'problem_message' => $message,
                'payout_status' => $locked->isHeld() ? 'frozen' : $locked->payout_status,
            ])->save();

            return $locked;
        });

        if (! $frozen) {
            return false;
        }

        self::openTicket($frozen, 'Segnalazione del compratore: '.$message);
        self::notifySellers($frozen, new ProtectedProblemNotification($frozen->tenant, $frozen));
        self::notifyBuyer($frozen, 'problem');

        return true;
    }

    /** Ferma il rilascio (contestazione o rimborso fatto fuori da Hub Core) e avvisa il team. */
    public static function freeze(PayableOrder $order, string $reason): void
    {
        $frozen = DB::transaction(function () use ($order, $reason): ?PayableOrder {
            $locked = PayableOrder::query()->lockForUpdate()->find($order->id);

            if (! $locked || ! $locked->isHeld()) {
                return null;
            }

            self::freezeLocked($locked, $reason);

            return $locked;
        });

        if ($frozen) {
            self::openTicket($frozen, $reason);
        }
    }

    /** Rimborso totale al compratore: se i soldi erano già girati si annulla prima il trasferimento al venditore. */
    public static function refund(PayableOrder $order): void
    {
        $refunded = DB::transaction(function () use ($order): ?PayableOrder {
            $locked = PayableOrder::query()->lockForUpdate()->find($order->id);

            if (! $locked || ! $locked->isProtected() || $locked->status !== 'paid' || $locked->payout_status === 'refunded') {
                return null;
            }

            if (! $locked->stripe_payment_intent_id) {
                throw new RuntimeException('Ordine senza pagamento Stripe: impossibile rimborsare.');
            }

            $connect = StripeConnectService::make();

            if ($locked->payout_status === 'released' && $locked->stripe_transfer_id) {
                $connect->reverseTransfer($locked->stripe_transfer_id, 'hub-reverse-'.$locked->id);
            }

            $refund = $connect->refund($locked->stripe_payment_intent_id, 'hub-refund-'.$locked->id);

            $locked->forceFill([
                'payout_status' => 'refunded',
                'refunded_at' => now(),
                'stripe_refund_id' => (string) ($refund['id'] ?? ''),
            ])->save();

            return $locked;
        });

        if ($refunded) {
            self::notifyBuyer($refunded, 'refunded');
        }
    }

    /** Eventi di Stripe (contestazione aperta / rimborso fatto dalla Dashboard) collegati a un ordine protetto. */
    public static function handleStripeEvent(string $kind, ?string $paymentIntentId): void
    {
        if (! $paymentIntentId) {
            return;
        }

        $order = PayableOrder::query()->where('flow', 'protected')->where('stripe_payment_intent_id', $paymentIntentId)->first();

        if (! $order) {
            return;
        }

        if ($kind === 'dispute') {
            self::freeze($order, 'Contestazione con la carta aperta presso la banca del compratore: rilascio fermato.');

            return;
        }

        if ($kind === 'refunded' && $order->payout_status !== 'refunded') {
            $order->forceFill(['payout_status' => 'refunded', 'refunded_at' => $order->refunded_at ?? now()])->save();
        }
    }

    private static function freezeLocked(PayableOrder $order, string $reason): void
    {
        $order->forceFill([
            'payout_status' => 'frozen',
            'problem_reported_at' => $order->problem_reported_at ?? now(),
            'problem_message' => $order->problem_message ?: $reason,
        ])->save();
    }

    private static function openTicket(PayableOrder $order, string $message): void
    {
        try {
            $tenant = $order->tenant;
            $ticket = $tenant->tickets()->create([
                'user_id' => null,
                'context_type' => 'protected_order',
                'context_id' => $order->id,
                'context_label' => 'Pagamento protetto — ordine #'.$order->id,
                'message' => $message,
                'status' => 'open',
            ]);

            Notification::send(User::where('is_super_admin', true)->get(), new NewTicketNotification($ticket));
        } catch (Throwable $e) {
            Log::warning('Pagamento protetto: avviso al team non inviato', ['order' => $order->id, 'message' => $e->getMessage()]);
        }
    }

    private static function notifySellers(PayableOrder $order, \Illuminate\Notifications\Notification $notification): void
    {
        try {
            $users = $order->tenant->users;

            if ($users->isNotEmpty()) {
                Notification::send($users, $notification);
            }
        } catch (Throwable $e) {
            Log::warning('Pagamento protetto: email al venditore non inviata', ['order' => $order->id, 'message' => $e->getMessage()]);
        }
    }

    private static function notifyBuyer(PayableOrder $order, string $kind): void
    {
        if (! $order->customer_email) {
            return;
        }

        try {
            Notification::route('mail', $order->customer_email)->notify(new BuyerOrderUpdateNotification($order->tenant, $order, $kind));
        } catch (Throwable $e) {
            Log::warning('Pagamento protetto: email al compratore non inviata', ['order' => $order->id, 'message' => $e->getMessage()]);
        }
    }

    /** Ordini trattenuti la cui data di rilascio è passata. @return \Illuminate\Database\Eloquent\Collection<int, PayableOrder> */
    public static function due()
    {
        return PayableOrder::query()
            ->where('flow', 'protected')
            ->where('payout_status', 'held')
            ->where('status', 'paid')
            ->where('release_at', '<=', now())
            ->orderBy('release_at')
            ->get();
    }
}
