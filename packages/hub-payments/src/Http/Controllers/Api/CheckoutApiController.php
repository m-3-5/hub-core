<?php

namespace M35\HubPayments\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use M35\HubPayments\Models\PayableOrder;
use M35\HubPayments\Models\PayableService;
use M35\HubPayments\Services\StripePaymentLinkService;
use M35\HubPayments\Support\TenantReturnHosts;
use M35\HubPayments\Support\TenantStripeConfig;
use RuntimeException;

/** Cassa del carrello: il sito del cliente manda solo id e quantità, i prezzi li decide l'hub. */
class CheckoutApiController extends Controller
{
    public function store(Request $request, string $tenantSlug): JsonResponse
    {
        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'success_url' => ['required', 'url', 'max:2000'],
            'cancel_url' => ['required', 'url', 'max:2000'],
            'customer_email' => ['nullable', 'email', 'max:190'],
        ]);

        foreach (['success_url', 'cancel_url'] as $field) {
            if (! TenantReturnHosts::allows($tenant, $data[$field])) {
                return response()->json([
                    'message' => 'Indirizzo di ritorno non consentito per questa attività.',
                    'errors' => [$field => ['Dominio non consentito.']],
                ], 422);
            }
        }

        // Stessa voce ripetuta: somma le quantità.
        $quantities = [];
        foreach ($data['items'] as $row) {
            $quantities[(int) $row['id']] = ($quantities[(int) $row['id']] ?? 0) + (int) $row['quantity'];
        }

        $services = PayableService::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', array_keys($quantities))
            ->whereIn('type', ['service', 'product'])
            ->where('status', 'active')
            ->where('published_to_site', true)
            ->whereNotNull('stripe_price_id')
            ->get()
            ->keyBy('id');

        $missing = array_values(array_diff(array_keys($quantities), $services->keys()->all()));

        if ($missing) {
            return response()->json([
                'message' => 'Alcune voci non sono disponibili.',
                'errors' => ['items' => ['Voci non valide o non pubblicate: '.implode(', ', $missing)]],
            ], 422);
        }

        if ($services->pluck('currency')->unique()->count() > 1) {
            return response()->json(['message' => 'Il carrello contiene valute diverse.'], 422);
        }

        $secretKey = TenantStripeConfig::secretKey($tenant);

        if (! $secretKey) {
            return response()->json(['message' => 'Pagamenti non attivi per questa attività.'], 409);
        }

        $snapshot = [];
        $total = 0;
        $fields = [];
        $i = 0;

        foreach ($quantities as $id => $quantity) {
            $service = $services[$id];
            $snapshot[] = [
                'id' => $service->id,
                'type' => $service->type,
                'title' => $service->title,
                'quantity' => $quantity,
                'unit_amount_cents' => $service->amount_cents,
            ];
            $total += $service->amount_cents * $quantity;
            $fields["line_items[$i][price]"] = $service->stripe_price_id;
            $fields["line_items[$i][quantity]"] = $quantity;
            $i++;
        }

        $order = PayableOrder::create([
            'tenant_id' => $tenant->id,
            'channel' => 'site',
            'status' => 'pending',
            'items' => $snapshot,
            'currency' => $services->first()->currency,
            'amount_cents' => $total,
            'customer_email' => $data['customer_email'] ?? null,
        ]);

        $fields += [
            'mode' => 'payment',
            'success_url' => $data['success_url'],
            'cancel_url' => $data['cancel_url'],
            // Il telefono serve per ritiro o prenotazione in salone (nessuna spedizione).
            'phone_number_collection[enabled]' => 'true',
            'client_reference_id' => (string) $order->id,
            'metadata[hub_order_id]' => (string) $order->id,
            'metadata[hub_tenant]' => $tenant->slug,
            'payment_intent_data[metadata][hub_order_id]' => (string) $order->id,
        ];

        if (! empty($data['customer_email'])) {
            $fields['customer_email'] = $data['customer_email'];
        }

        try {
            $session = (new StripePaymentLinkService($secretKey))->createCheckoutSession($fields);
        } catch (RuntimeException $e) {
            $order->delete();
            Log::warning('Checkout carrello fallito', ['tenant' => $tenant->slug, 'message' => $e->getMessage()]);

            return response()->json(['message' => 'Non riesco ad aprire la cassa in questo momento. Riprova.'], 502);
        }

        $host = strtolower((string) parse_url($session['url'], PHP_URL_HOST));

        if ($host !== 'stripe.com' && ! str_ends_with($host, '.stripe.com')) {
            $order->delete();

            return response()->json(['message' => 'Risposta di pagamento non valida.'], 502);
        }

        $order->update(['stripe_session_id' => $session['id']]);

        return response()->json(['id' => $session['id'], 'url' => $session['url']], 201);
    }
}
