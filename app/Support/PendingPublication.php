<?php

namespace App\Support;

use App\Models\Promo;
use App\Models\Tenant;
use App\Services\WordPressWebhookDispatcher;
use Illuminate\Http\RedirectResponse;
use M35\HubPayments\Models\PayableService;

/**
 * Una pubblicazione che aspetta il credito: si ricorda cosa stavi pubblicando,
 * ti porta alla pagina «Credito» e, appena hai ricaricato, la pubblica da sola.
 */
class PendingPublication
{
    private const KEY = 'credit_pending';

    /** Manda alla pagina del credito ricordando cosa pubblicare dopo. */
    public static function redirect(Tenant $tenant, string $kind, int $id): RedirectResponse
    {
        session()->put(self::KEY, ['tenant' => $tenant->id, 'kind' => $kind, 'id' => $id]);

        return redirect()->route('admin.credit.show', $tenant);
    }

    /** @return array{kind: string, id: int, title: string}|null */
    public static function current(Tenant $tenant): ?array
    {
        $p = session(self::KEY);
        if (! is_array($p) || (int) ($p['tenant'] ?? 0) !== $tenant->id) {
            return null;
        }

        $item = self::item($tenant, (string) $p['kind'], (int) $p['id']);

        return $item ? ['kind' => (string) $p['kind'], 'id' => (int) $p['id'], 'title' => (string) $item->title] : null;
    }

    /** Dopo la ricarica: prova a pubblicare. Restituisce dove andare, o null se non c'era niente in attesa. */
    public static function resume(Tenant $tenant): ?RedirectResponse
    {
        $p = self::current($tenant);
        if (! $p) {
            return null;
        }

        $item = self::item($tenant, $p['kind'], $p['id']);
        if (! PayPerUse::charge($tenant, $p['kind'], $p['id'], $item->title)) {
            return null; // ancora poco credito: resta in attesa
        }

        session()->forget(self::KEY);
        self::publish($tenant, $item);

        return redirect()->to(self::showUrl($tenant, $item))->with('success', 'Fatto! «'.$item->title.'» è online.');
    }

    public static function forget(): void
    {
        session()->forget(self::KEY);
    }

    public static function publish(Tenant $tenant, Promo|PayableService $item): void
    {
        if ($item instanceof Promo) {
            if ($item->isDraft()) {
                $item->markPublished();
            }
            app(WordPressWebhookDispatcher::class)->promoPublished($tenant, $item->fresh());

            return;
        }

        if (! $item->published_to_site) {
            $item->update(['published_to_site' => true]);
        }
        app(WordPressWebhookDispatcher::class)->servicesSync($tenant);
    }

    public static function showUrl(Tenant $tenant, Promo|PayableService $item): string
    {
        if ($item instanceof Promo) {
            return route('admin.promos.show', [$tenant, $item]);
        }

        return route('admin.'.($item->type === 'product' ? 'products' : 'services').'.show', [$tenant, $item]);
    }

    private static function item(Tenant $tenant, string $kind, int $id): Promo|PayableService|null
    {
        return match ($kind) {
            'promo' => $tenant->promos()->whereKey($id)->first(),
            'product', 'service' => PayableService::where('tenant_id', $tenant->id)->where('type', $kind)->whereKey($id)->first(),
            default => null,
        };
    }
}
