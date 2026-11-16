<?php

namespace App\Support;

use App\Models\Promo;
use App\Models\Tenant;
use App\Services\WordPressWebhookDispatcher;
use Illuminate\Http\RedirectResponse;
use M35\HubPayments\Models\PayableService;
use M35\HubSitebuilder\Models\GeneratedSite;
use M35\HubSitebuilder\Services\SiteAddons;
use M35\HubSitebuilder\Services\SiteTranslations;

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

        return $item ? ['kind' => (string) $p['kind'], 'id' => (int) $p['id'], 'title' => self::title($tenant, $item)] : null;
    }

    /** Dopo la ricarica: prova a pubblicare. Restituisce dove andare, o null se non c'era niente in attesa. */
    public static function resume(Tenant $tenant): ?RedirectResponse
    {
        $p = self::current($tenant);
        if (! $p) {
            return null;
        }

        $item = self::item($tenant, $p['kind'], $p['id']);

        // Lingue del sito: con la carta salvata si traducono e si registra 1 € per ognuna riuscita.
        if ($p['kind'] === 'site_langs') {
            if (! PayPerUse::exempt($tenant) && ! PayPerUse::hasCard($tenant)) {
                return null;
            }
            session()->forget(self::KEY);
            $r = app(SiteTranslations::class)->activate($tenant, $item, SiteTranslations::wanted($item));
            $back = redirect()->route('admin.sitebuilder.show', $tenant);

            return $r['done']
                ? $back->with('success', 'Fatto! Il tuo sito ora è anche in '.SiteTranslations::names($r['done']).'.')
                : $back->withErrors(['tagline' => 'L\'IA non è riuscita a tradurre adesso: riprova tra un minuto. Non ti abbiamo addebitato niente.']);
        }

        // Opzioni del sito (SEO, IA, indicizza): con la carta salvata si attivano da sole.
        if (str_starts_with($p['kind'], 'addon_')) {
            if (! PayPerUse::exempt($tenant) && ! PayPerUse::hasCard($tenant)) {
                return null;
            }
            session()->forget(self::KEY);
            $name = substr($p['kind'], 6);
            $back = redirect()->route('admin.sitebuilder.show', $tenant);

            return app(SiteAddons::class)->activate($tenant, $item, $name)
                ? $back->with('success', 'Fatto! «'.SiteAddons::ADDONS[$name][1].'» è attiva sul tuo sito.')
                : $back->withErrors(['tagline' => 'L\'IA non è riuscita adesso: riprova tra un minuto. Non ti abbiamo addebitato niente.']);
        }

        if (! PayPerUse::charge($tenant, $p['kind'], $p['id'], self::title($tenant, $item))) {
            return null; // ancora poco credito: resta in attesa
        }

        session()->forget(self::KEY);
        self::publish($tenant, $item);

        $done = $item instanceof GeneratedSite
            ? 'Fatto! Il tuo sito è online: '.route('site.public.show', $tenant)
            : 'Fatto! «'.$item->title.'» è online.';

        return redirect()->to(self::showUrl($tenant, $item))->with('success', $done);
    }

    public static function forget(): void
    {
        session()->forget(self::KEY);
    }

    public static function publish(Tenant $tenant, Promo|PayableService|GeneratedSite $item): void
    {
        if ($item instanceof GeneratedSite) {
            $item->update(['status' => 'published', 'published_at' => $item->published_at ?? now()]);

            return;
        }

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

    public static function showUrl(Tenant $tenant, Promo|PayableService|GeneratedSite $item): string
    {
        if ($item instanceof GeneratedSite) {
            return route('admin.sitebuilder.show', $tenant);
        }

        if ($item instanceof Promo) {
            return route('admin.promos.show', [$tenant, $item]);
        }

        return route('admin.'.($item->type === 'product' ? 'products' : 'services').'.show', [$tenant, $item]);
    }

    private static function title(Tenant $tenant, Promo|PayableService|GeneratedSite $item): string
    {
        if ($item instanceof GeneratedSite) {
            $kind = (string) session(self::KEY.'.kind');
            if (str_starts_with($kind, 'addon_') && isset(SiteAddons::ADDONS[substr($kind, 6)])) {
                return SiteAddons::ADDONS[substr($kind, 6)][1].' per il tuo sito';
            }
            $langs = SiteTranslations::wanted($item);

            return session(self::KEY.'.kind') === 'site_langs' && $langs ? 'Il tuo sito in '.SiteTranslations::names($langs) : 'Sito web di '.$tenant->name;
        }

        return (string) $item->title;
    }

    private static function item(Tenant $tenant, string $kind, int $id): Promo|PayableService|GeneratedSite|null
    {
        return match ($kind) {
            PayPerUse::SITE, 'site_langs', 'addon_seo', 'addon_ai', 'addon_index' => class_exists(GeneratedSite::class) ? GeneratedSite::where('tenant_id', $tenant->id)->whereKey($id)->first() : null,
            'promo' => $tenant->promos()->whereKey($id)->first(),
            'product', 'service' => PayableService::where('tenant_id', $tenant->id)->where('type', $kind)->whereKey($id)->first(),
            default => null,
        };
    }
}
