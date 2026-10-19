<?php

namespace M35\HubSitebuilder\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use M35\HubSitebuilder\Models\ArchivedSite;
use M35\HubSitebuilder\Models\GeneratedSite;
use M35\HubSitebuilder\Notifications\SiteExpiringNotification;
use M35\HubSitebuilder\Notifications\SiteRemovedNotification;
use Throwable;

/**
 * Vita dei siti creati con Max. Chi non paga più o non usa il sito riceve un avviso; passato il tempo di tolleranza
 * il sito viene tolto dal pubblico e conservato nell'archivio dell'amministratore.
 */
class SiteLifecycle
{
    /**
     * Perché un sito va avvisato/rimosso, oppure null se va tutto bene.
     *
     * @return array{key: string, label: string, payment: bool, immediate: bool}|null
     */
    public function evaluate(GeneratedSite $site): ?array
    {
        $tenant = $site->tenant;

        if (! $tenant) {
            return ['key' => 'senza_attivita', 'label' => 'L\'attività non esiste più', 'payment' => false, 'immediate' => true];
        }

        if (($tenant->settings['keep_site'] ?? false) === true) {
            return null;
        }

        if ($tenant->isGuestPending()) {
            $days = (int) config('hub.site_lifecycle.unverified_days', 30);

            return $site->created_at && $site->created_at->lt(now()->subDays($days))
                ? ['key' => 'mai_verificato', 'label' => 'l\'email non è mai stata confermata', 'payment' => false, 'immediate' => true]
                : null;
        }

        if ($tenant->subscription_status === 'active') {
            return null;
        }

        if ($tenant->trialExpired()) {
            return ['key' => 'trial_scaduto', 'label' => 'la prova gratuita è finita senza abbonamento', 'payment' => true, 'immediate' => false];
        }

        if (in_array($tenant->subscription_status, ['past_due', 'canceled'], true)) {
            return ['key' => 'pagamento', 'label' => 'l\'abbonamento non è pagato o è stato annullato', 'payment' => true, 'immediate' => false];
        }

        $days = (int) config('hub.site_lifecycle.inactive_days', 120);
        $last = $site->last_edited_at ?? $site->published_at ?? $site->created_at;

        if ($last && $last->lt(now()->subDays($days))) {
            return ['key' => 'inattivo', 'label' => 'il sito non viene modificato da oltre '.$days.' giorni', 'payment' => false, 'immediate' => false];
        }

        return null;
    }

    /**
     * @return array{warned: int, archived: int, recovered: int, waiting: int, lines: array<int, string>}
     */
    public function run(bool $apply): array
    {
        $report = ['warned' => 0, 'archived' => 0, 'recovered' => 0, 'waiting' => 0, 'lines' => []];
        $grace = (int) config('hub.site_lifecycle.grace_days', 14);

        foreach (GeneratedSite::query()->with('tenant.users')->get() as $site) {
            $name = $site->tenant?->name ?? ('sito #'.$site->id);
            $why = $this->evaluate($site);

            if ($why === null) {
                if ($site->warned_at) {
                    $report['recovered']++;
                    $report['lines'][] = "{$name}: tutto a posto, avviso annullato";

                    if ($apply) {
                        $site->forceFill(['warned_at' => null, 'delete_after' => null])->save();
                    }
                }

                continue;
            }

            if ($why['immediate'] || ($site->delete_after && $site->delete_after->isPast())) {
                $report['archived']++;
                $report['lines'][] = "{$name}: ARCHIVIATO ({$why['label']})";

                if ($apply) {
                    $this->archive($site, $why, notify: ! $why['immediate']);
                }

                continue;
            }

            if (! $site->warned_at) {
                $report['warned']++;
                $report['lines'][] = "{$name}: avviso inviato, rimozione tra {$grace} giorni ({$why['label']})";

                if ($apply) {
                    $deleteAfter = now()->addDays($grace);
                    $site->forceFill(['warned_at' => now(), 'delete_after' => $deleteAfter])->save();
                    $this->send($site, new SiteExpiringNotification($site->tenant, $why['label'], $deleteAfter, $why['payment']));
                }

                continue;
            }

            $report['waiting']++;
            $report['lines'][] = "{$name}: in attesa fino al ".$site->delete_after?->format('d/m/Y');
        }

        return $report;
    }

    /** @param  array{key: string, label: string, payment: bool, immediate: bool}  $why */
    public function archive(GeneratedSite $site, array $why, bool $notify = true): ArchivedSite
    {
        $tenant = $site->tenant;
        $owners = $tenant ? SiteOwners::for($tenant) : collect();

        $archived = ArchivedSite::create([
            'tenant_id' => $tenant?->id,
            'tenant_name' => $tenant?->name ?? 'Attività eliminata',
            'tenant_slug' => $tenant?->slug ?? '-',
            'owner_email' => $owners->first()?->email,
            'answers' => $site->answers,
            'reason' => $why['key'],
            'reason_label' => $why['label'],
            'site_created_at' => $site->created_at,
            'archived_at' => now(),
        ]);

        foreach (array_filter([$site->hero_svg_path, $site->hero_video_path]) as $path) {
            Storage::disk('public')->delete($path);
        }

        $site->delete();

        if ($notify && $tenant) {
            $this->send($site, new SiteRemovedNotification($tenant->name, $why['label']), $owners);
        }

        Log::info('Sito archiviato', ['tenant' => $archived->tenant_slug, 'reason' => $why['key']]);

        return $archived;
    }

    private function send(GeneratedSite $site, object $notification, $owners = null): void
    {
        $owners ??= $site->tenant ? SiteOwners::for($site->tenant) : collect();

        if ($owners->isEmpty()) {
            return;
        }

        try {
            Notification::send($owners, $notification);
        } catch (Throwable $e) {
            Log::warning('Email sito non inviata', ['site' => $site->id, 'message' => $e->getMessage()]);
        }
    }
}
