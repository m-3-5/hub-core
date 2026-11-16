<?php

namespace App\Support;

use App\Models\Promo;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use M35\HubSitebuilder\Services\SiteAddons;
use M35\HubSitebuilder\Services\SiteTranslations;

/**
 * Email «consigli del mese»: solo ai clienti già nostri, una al mese, sempre con il link per non riceverle più
 * (chi lo preme non riceve più niente di promozionale; le email di servizio, come pagamenti e password, continuano).
 */
class MarketingEmails
{
    public static function optedOut(Tenant $tenant): bool
    {
        return ! empty($tenant->settings['marketing']['optout_at']);
    }

    public static function setOptOut(Tenant $tenant, bool $out): void
    {
        $settings = $tenant->settings ?? [];
        $settings['marketing']['optout_at'] = $out ? now()->toIso8601String() : null;
        $tenant->forceFill(['settings' => $settings])->save();
    }

    public static function unsubscribeUrl(Tenant $tenant): string
    {
        return URL::signedRoute('email.unsubscribe', $tenant);
    }

    /** Titolari con una email vera (niente account ospite né staff di Hub Core). */
    public static function recipients(Tenant $tenant): Collection
    {
        return $tenant->users()->get()->filter(fn (User $u) => $u->email
            && ! $u->isSuperAdmin()
            && ! str_ends_with(strtolower($u->email), '@guest.hub-core.local'))->values();
    }

    /**
     * Fino a 3 consigli su misura: cosa il cliente non usa ancora.
     *
     * @return array<int, array{0: string, 1: string, 2: string}> [titolo, testo, link]
     */
    public static function tips(Tenant $tenant): array
    {
        $site = class_exists(\M35\HubSitebuilder\Models\GeneratedSite::class) ? $tenant->generatedSite : null;
        $online = $site && $site->isPublished();
        $tips = [];

        if (! $online) {
            $tips[] = ['🌐 Il tuo sito con l\'IA a 1 €', 'Scrivi due righe su cosa fai: l\'IA prepara il sito, pronto per il telefono, con i tasti per chiamare e WhatsApp.', route('admin.sitebuilder.show', $tenant)];
        }

        $recentPromo = Promo::where('tenant_id', $tenant->id)->where('created_at', '>=', now()->subDays(30))->exists();
        if (! $recentPromo) {
            $tips[] = ['📣 Pubblica una promo', $online ? 'Appena la pubblichi compare da sola anche nel tuo sito, nella sezione «Offerte del momento».' : 'Volantino, pagina e link da mandare su WhatsApp, pronti in un minuto.', route('admin.promos.index', $tenant)];
        }

        if ($online && ! SiteTranslations::active($site)) {
            $tips[] = ['🌍 Il tuo sito in altre lingue', 'Inglese, tedesco, francese e spagnolo: 1 € a lingua, traduce l\'IA e si aggiorna da sola.', route('admin.sitebuilder.show', $tenant)];
        }

        if ($online && ! (SiteAddons::active($site, 'seo') && SiteAddons::active($site, 'index'))) {
            $tips[] = ['⚡ Fatti trovare su Google e dall\'IA', 'SEO e parole chiave, scheda per ChatGPT e Gemini, segnalazione ai motori di ricerca: 1 € a opzione.', route('admin.sitebuilder.show', $tenant)];
        }

        $tips[] = ['📢 Gli annunci sono gratis', 'Casa, auto, oggetti, lavoro: pubblicali gratis'.($online ? ' e compaiono da soli nel tuo sito.' : '.'), route('admin.classifieds.index', $tenant)];

        return array_slice($tips, 0, 3);
    }
}
