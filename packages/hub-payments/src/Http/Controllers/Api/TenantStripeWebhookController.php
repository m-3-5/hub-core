<?php

namespace M35\HubPayments\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use M35\HubPayments\Support\PaymentRecorder;
use M35\HubPayments\Support\TenantStripeWebhook;

/**
 * Webhook del conto Stripe di ogni tenant (diverso da /api/stripe/webhook, che è la fatturazione dell'hub).
 * Il segreto di firma è per tenant, quindi un evento firmato per un tenant non vale per un altro.
 */
class TenantStripeWebhookController extends Controller
{
    public function handle(Request $request, string $tenantSlug): Response
    {
        $tenant = Tenant::where('slug', $tenantSlug)->first();
        $secret = $tenant ? TenantStripeWebhook::secret($tenant) : null;

        if (! $tenant || ! $secret) {
            return response('Webhook non configurato', 404);
        }

        $payload = $request->getContent();

        if (! TenantStripeWebhook::verifySignature($payload, $request->header('Stripe-Signature'), $secret)) {
            return response('Firma non valida', 401);
        }

        $event = json_decode($payload, true);

        if (! is_array($event)) {
            return response('Evento non valido', 400);
        }

        TenantStripeWebhook::touch($tenant);

        $type = $event['type'] ?? null;
        $object = $event['data']['object'] ?? [];

        if (in_array($type, TenantStripeWebhook::EVENTS, true) && is_array($object)) {
            try {
                PaymentRecorder::record($tenant, $object);
            } catch (\Throwable $e) {
                // 500: Stripe riprova la consegna, l'operazione è idempotente.
                Log::error('Webhook Stripe tenant: registrazione pagamento fallita', [
                    'tenant' => $tenant->slug,
                    'event' => $event['id'] ?? null,
                    'message' => $e->getMessage(),
                ]);

                return response('Errore interno', 500);
            }
        }

        return response('OK', 200);
    }
}
