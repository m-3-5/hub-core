<?php

namespace M35\HubPayments\Support;

use App\Models\ActivityLog;
use App\Models\Tenant;
use App\Models\User;

/**
 * Condizioni economiche per chi vende: vanno accettate (con data, versione e utente) prima di collegare i pagamenti protetti.
 * Se il testo cambia in modo che conta, si alza VERSION e l'accettazione va rinnovata.
 */
class SellerTerms
{
    public const VERSION = '2026-10-11';

    public static function accepted(Tenant $tenant): bool
    {
        return ($tenant->settings['seller_terms']['version'] ?? null) === self::VERSION;
    }

    public static function acceptedAt(Tenant $tenant): ?string
    {
        return $tenant->settings['seller_terms']['accepted_at'] ?? null;
    }

    public static function accept(Tenant $tenant, ?User $user, ?string $ip): void
    {
        $settings = $tenant->settings ?? [];
        $settings['seller_terms'] = [
            'version' => self::VERSION,
            'accepted_at' => now()->toIso8601String(),
            'user_id' => $user?->id,
            'ip' => $ip,
        ];

        $tenant->forceFill(['settings' => $settings])->save();

        ActivityLog::record($tenant, 'seller_terms_accepted', input: ['version' => self::VERSION]);
    }
}
