<?php

namespace M35\HubPayments\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use M35\HubPayments\Models\PayableService;
use M35\HubPayments\Support\QuoteService;
use M35\HubPayments\Support\TenantStripeConfig;
use RuntimeException;

/** Preventivi a importo libero creati dal sito del cliente (chiamate firmate). */
class QuoteApiController extends Controller
{
    public function store(Request $request, string $tenantSlug): JsonResponse
    {
        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'amount_cents' => ['required', 'integer', 'min:'.QuoteService::MIN_CENTS, 'max:'.QuoteService::MAX_CENTS],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'created_by' => ['nullable', 'string', 'max:100'],
        ]);

        if (! TenantStripeConfig::isConfigured($tenant)) {
            return response()->json(['message' => 'Pagamenti non attivi per questa attività.'], 409);
        }

        try {
            $quote = QuoteService::create(
                $tenant,
                $data['title'],
                (int) $data['amount_cents'],
                $data['customer_name'] ?? null,
                $data['description'] ?? null,
                $data['created_by'] ?? null,
            );
        } catch (RuntimeException $e) {
            Log::warning('Creazione preventivo fallita', ['tenant' => $tenant->slug, 'message' => $e->getMessage()]);

            return response()->json(['message' => 'Non riesco a creare il link di pagamento in questo momento. Riprova.'], 502);
        }

        return response()->json(['quote' => $this->present($quote)], 201);
    }

    public function index(string $tenantSlug): JsonResponse
    {
        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();

        $quotes = PayableService::query()
            ->where('tenant_id', $tenant->id)
            ->where('type', 'quote')
            ->where('status', '!=', 'archived')
            ->latest('id')
            ->limit(200)
            ->get();

        return response()->json(['quotes' => $quotes->map(fn ($q) => $this->present($q))->values()]);
    }

    public function show(string $tenantSlug, int $quoteId): JsonResponse
    {
        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();

        $quote = PayableService::query()
            ->where('tenant_id', $tenant->id)
            ->where('type', 'quote')
            ->findOrFail($quoteId);

        return response()->json(['quote' => $this->present($quote)]);
    }

    /** @return array<string, mixed> */
    private function present(PayableService $quote): array
    {
        return QuoteService::present($quote);
    }
}
