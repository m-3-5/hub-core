<?php

namespace App\Http\Controllers;

use App\Models\SiteOrder;
use App\Models\Tenant;
use App\Models\TenantModuleCharge;
use App\Notifications\SiteOrderPaidNotification;
use App\Notifications\SiteOrderWelcomeNotification;
use App\Notifications\TenantWelcomeNotification;
use App\Services\SiteOrderBilling;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Throwable;

class StripeBillingWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $secret = config('services.hub_billing.webhook_secret');
        $payload = $request->getContent();

        if (! $secret || ! $this->verifySignature($payload, $request->header('Stripe-Signature'), $secret)) {
            return response('Invalid signature', 401);
        }

        $event = json_decode($payload, true);
        $type = $event['type'] ?? null;
        $object = $event['data']['object'] ?? [];

        match ($type) {
            'checkout.session.completed', 'checkout.session.async_payment_succeeded' => $this->handleCheckoutCompleted($object),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($object),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($object),
            default => null,
        };

        return response('OK', 200);
    }

    /** @param  array<string, mixed>  $session */
    private function handleCheckoutCompleted(array $session): void
    {
        // Pagamento protetto di un cliente finale: arriva sul conto di M 3.5 e resta trattenuto fino alla consegna.
        if (($session['metadata']['hub_flow'] ?? null) === 'protected') {
            $tenant = Tenant::where('slug', $session['metadata']['hub_tenant'] ?? '')->first();

            if (! $tenant) {
                Log::warning('Stripe billing webhook: tenant del pagamento protetto non trovato', ['session' => $session['id'] ?? null]);

                return;
            }

            \M35\HubPayments\Support\PaymentRecorder::record($tenant, $session, viaPlatform: true);

            return;
        }

        if (! empty($session['metadata']['site_order_id'])) {
            $this->handleSiteOrderCheckout($session);

            return;
        }

        $tenant = Tenant::find($session['metadata']['tenant_id'] ?? $session['client_reference_id'] ?? null);

        if (! $tenant) {
            Log::warning('Stripe billing webhook: tenant non trovato', ['session' => $session['id'] ?? null]);

            return;
        }

        $tenant->forceFill([
            'stripe_customer_id' => $session['customer'] ?? $tenant->stripe_customer_id,
            'stripe_subscription_id' => $session['subscription'] ?? $tenant->stripe_subscription_id,
            'billing_interval' => $session['metadata']['interval'] ?? $tenant->billing_interval,
            'subscription_status' => 'active',
        ])->save();

        if (($session['metadata']['launch_offer'] ?? null) === '1') {
            $this->completeLaunchOfferActivation($tenant, $session);
        }
    }

    /** @param  array<string, mixed>  $session */
    private function completeLaunchOfferActivation(Tenant $tenant, array $session): void
    {
        $module = $session['metadata']['first_module'] ?? null;
        $ledgerModule = match ($module) {
            'services' => 'servizi',
            'promo' => 'promo',
            default => null,
        };

        if ($ledgerModule && ! $tenant->moduleCharges()->where('module', $ledgerModule)->where('charge_type', 'activation')->exists()) {
            TenantModuleCharge::create([
                'tenant_id' => $tenant->id,
                'module' => $ledgerModule,
                'charge_type' => 'activation',
                'period' => now()->format('Y-m'),
                'description' => 'Coperta dall\'offerta di lancio (1€)',
                'amount_cents' => 0,
                'paid' => true,
                'paid_at' => now(),
            ]);
        }

        // Chi ha già un account (paga dopo la prima settimana) ha già la sua password: niente email di benvenuto.
        if (($session['metadata']['existing_account'] ?? '0') === '1') {
            return;
        }

        $user = $tenant->users()->first();

        if ($user) {
            $passwordToken = Password::broker()->createToken($user);

            try {
                Notification::send($user, new TenantWelcomeNotification($tenant, $passwordToken, viaLaunchOffer: true));
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    /** @param  array<string, mixed>  $subscription */
    private function handleSubscriptionUpdated(array $subscription): void
    {
        if ($order = $this->siteOrderForSubscription($subscription)) {
            $status = match ($subscription['status'] ?? null) {
                'trialing' => 'trial',
                'active' => 'paying',
                'past_due', 'unpaid', 'incomplete' => 'past_due',
                default => $order->status,
            };

            $order->update(['status' => $status]);

            return;
        }

        $tenant = $this->tenantForSubscription($subscription);

        if (! $tenant) {
            return;
        }

        $status = match ($subscription['status'] ?? null) {
            'active', 'trialing' => 'active',
            'past_due', 'unpaid', 'incomplete' => 'past_due',
            default => 'canceled',
        };

        $tenant->forceFill(['subscription_status' => $status])->save();
    }

    /** @param  array<string, mixed>  $subscription */
    private function handleSubscriptionDeleted(array $subscription): void
    {
        if ($order = $this->siteOrderForSubscription($subscription)) {
            // Fine naturale delle rate (cancel_at raggiunto) = pagato per intero; prima = annullato.
            $finished = $order->completes_at && now()->gte($order->completes_at->copy()->subDay());
            $order->update(['status' => $finished ? 'completed' : 'canceled']);

            return;
        }

        $tenant = $this->tenantForSubscription($subscription);

        $tenant?->forceFill(['subscription_status' => 'canceled'])->save();
    }

    /** Pagamento dell'euro di partenza dalla landing siti web: parte la prova e si programma la fine delle rate. */
    private function handleSiteOrderCheckout(array $session): void
    {
        $order = SiteOrder::find($session['metadata']['site_order_id']);

        if (! $order || $order->paid_at) {
            return;
        }

        $trialEnds = now()->addDays($order->trial_days);

        $order->update([
            'status' => 'trial',
            'stripe_session_id' => $session['id'] ?? $order->stripe_session_id,
            'stripe_customer_id' => $session['customer'] ?? null,
            'stripe_subscription_id' => $session['subscription'] ?? null,
            'paid_at' => now(),
            'trial_ends_at' => $trialEnds,
            // Prima rata a fine prova, poi una al mese: l'abbonamento si ferma a metà dell'ultimo mese, senza addebiti in più.
            'completes_at' => $trialEnds->copy()->addMonths($order->installments - 1)->addDays(15),
        ]);

        $order->lead?->update(['message' => '1 € per partire: pagato', 'status' => 'new']);

        $secretKey = config('services.hub_billing.secret_key');

        if ($secretKey && $order->stripe_subscription_id) {
            try {
                (new SiteOrderBilling($secretKey))->cancelAt($order->stripe_subscription_id, $order->completes_at);
            } catch (Throwable $e) {
                // Senza questo le rate non si fermerebbero da sole: segnalato in modo evidente.
                Log::error('Promo 1 euro: impossibile programmare la fine delle rate', ['order' => $order->id, 'error' => $e->getMessage()]);
            }
        }

        if ($to = config('landing.leads_email') ?: config('mail.leads_monitor_email')) {
            try {
                Notification::route('mail', $to)->notify(new SiteOrderPaidNotification($order));
            } catch (Throwable $e) {
                report($e);
            }
        }

        try {
            Notification::route('mail', $order->email)->notify(new SiteOrderWelcomeNotification($order));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** @param  array<string, mixed>  $subscription */
    private function siteOrderForSubscription(array $subscription): ?SiteOrder
    {
        if ($id = $subscription['metadata']['site_order_id'] ?? null) {
            return SiteOrder::find($id);
        }

        return SiteOrder::where('stripe_subscription_id', $subscription['id'] ?? null)->first();
    }

    /** @param  array<string, mixed>  $subscription */
    private function tenantForSubscription(array $subscription): ?Tenant
    {
        $tenantId = $subscription['metadata']['tenant_id'] ?? null;

        if ($tenantId) {
            return Tenant::find($tenantId);
        }

        return Tenant::where('stripe_subscription_id', $subscription['id'] ?? null)->first();
    }

    private function verifySignature(string $payload, ?string $header, string $secret): bool
    {
        if (! $header) {
            return false;
        }

        $parts = [];
        foreach (explode(',', $header) as $chunk) {
            [$key, $value] = array_pad(explode('=', $chunk, 2), 2, null);
            $parts[$key][] = $value;
        }

        $timestamp = $parts['t'][0] ?? null;
        $signatures = $parts['v1'] ?? [];

        if (! $timestamp || empty($signatures)) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, (string) $signature)) {
                return true;
            }
        }

        return false;
    }
}
