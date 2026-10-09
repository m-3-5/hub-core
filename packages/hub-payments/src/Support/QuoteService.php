<?php

namespace M35\HubPayments\Support;

use App\Models\Tenant;
use M35\HubPayments\Models\PayableService;
use M35\HubPayments\Services\StripePaymentLinkService;
use RuntimeException;

/**
 * Preventivi a importo libero: voci type=quote mai pubblicate, fuori da quota servizi e addebiti modulo,
 * con un Payment Link Stripe pagabile una volta sola.
 */
class QuoteService
{
    public const MIN_CENTS = 50;

    public const MAX_CENTS = 9_999_900;

    /**
     * @throws RuntimeException se Stripe non è configurato o rifiuta la richiesta
     */
    public static function create(
        Tenant $tenant,
        string $title,
        int $amountCents,
        ?string $customerName = null,
        ?string $description = null,
        ?string $createdBy = null,
        ?int $createdByUserId = null,
    ): PayableService {
        $secretKey = TenantStripeConfig::secretKey($tenant);

        if (! $secretKey) {
            throw new RuntimeException('Pagamenti non attivi per questa attività.');
        }

        $currency = config('hub-payments.currency', 'eur');
        $stripeDescription = trim(implode(' — ', array_filter([
            $customerName ? 'Cliente: '.$customerName : null,
            $description,
        ]))) ?: null;

        $result = (new StripePaymentLinkService($secretKey))->createSingleUsePaymentLink(
            $title,
            $stripeDescription,
            $amountCents,
            $currency,
            ['hub_tenant' => $tenant->slug],
        );

        return PayableService::create([
            'tenant_id' => $tenant->id,
            'created_by' => $createdByUserId,
            'type' => 'quote',
            'title' => $title,
            'slug' => PayableService::uniqueSlugForTenant($tenant->id, $title),
            'description' => $description,
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'stripe_product_id' => $result['product_id'],
            'stripe_price_id' => $result['price_id'],
            'stripe_payment_link_id' => $result['payment_link_id'],
            'payment_url' => $result['url'],
            'status' => 'active',
            'published_to_site' => false,
            'metadata' => array_filter([
                'customer_name' => $customerName,
                'created_by' => $createdBy,
            ], fn ($v) => $v !== null && $v !== ''),
        ]);
    }

    /** Annulla un preventivo non pagato: disattiva il link e lo archivia. */
    public static function cancel(Tenant $tenant, PayableService $quote): void
    {
        $secretKey = TenantStripeConfig::secretKey($tenant);

        if ($secretKey && $quote->stripe_payment_link_id) {
            try {
                (new StripePaymentLinkService($secretKey))->deactivatePaymentLink($quote->stripe_payment_link_id);
            } catch (RuntimeException) {
                // Il link può essere già disattivato su Stripe.
            }
        }

        $quote->update(['status' => 'archived', 'published_to_site' => false]);
    }

    /** @return array<string, mixed> */
    public static function present(PayableService $quote): array
    {
        return [
            'id' => $quote->id,
            'title' => $quote->title,
            'customer_name' => $quote->metadata['customer_name'] ?? null,
            'description' => $quote->description,
            'amount_cents' => $quote->amount_cents,
            'amount_label' => $quote->amountEuros().' €',
            'currency' => $quote->currency,
            'payment_url' => $quote->payment_url,
            'status' => self::status($quote),
            'paid_at' => $quote->paid_at?->toIso8601String(),
            'created_by' => $quote->metadata['created_by'] ?? $quote->creator?->name,
            'created_at' => $quote->created_at?->toIso8601String(),
        ];
    }

    /** pending | paid | cancelled */
    public static function status(PayableService $quote): string
    {
        return match (true) {
            $quote->isPaid() => 'paid',
            $quote->status === 'archived' => 'cancelled',
            default => 'pending',
        };
    }
}
