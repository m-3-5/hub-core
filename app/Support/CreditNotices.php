<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\TenantUsageEntry;
use App\Notifications\CreditOverdraftNotification;
use App\Notifications\UsageReceiptNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/** Email del credito: ricevuta (non fiscale) a ogni acquisto e ricarica, avviso quando si usa il fido. Al cliente e a Max. */
class CreditNotices
{
    public static function receipt(Tenant $tenant, TenantUsageEntry $entry): void
    {
        self::send($tenant, new UsageReceiptNotification($tenant, $entry, PayPerUse::creditCents($tenant)));
    }

    public static function overdraft(Tenant $tenant): void
    {
        self::send($tenant, new CreditOverdraftNotification($tenant, PayPerUse::creditCents($tenant)));
    }

    /** Email vere dei titolari (non quelle provvisorie degli ospiti). */
    public static function owners(Tenant $tenant): Collection
    {
        return $tenant->users->reject(fn ($u) => ! $u->email || str_ends_with(strtolower($u->email), '@guest.hub-core.local'));
    }

    public static function maxEmail(): ?string
    {
        return config('mail.leads_monitor_email') ?: config('company.email');
    }

    private static function send(Tenant $tenant, object $notification): void
    {
        try {
            $owners = self::owners($tenant);
            if ($owners->isNotEmpty()) {
                Notification::send($owners, $notification);
            }
            if ($max = self::maxEmail()) {
                Notification::route('mail', $max)->notify($notification);
            }
        } catch (Throwable $e) {
            Log::warning('Credito: email non inviata', ['tenant' => $tenant->slug, 'message' => $e->getMessage()]);
        }
    }
}
