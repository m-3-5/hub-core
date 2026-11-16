<?php

namespace M35\HubPayments\Support;

use App\Models\Tenant;

/**
 * Bonifico: l'attività salva intestatario e IBAN, il cliente li vede sulla pagina del prodotto/servizio
 * e paga lui con la sua banca. Nessun incasso passa da noi.
 */
class TenantBankTransfer
{
    /** @return array{holder: string, iban: string}|null */
    public static function details(Tenant $tenant): ?array
    {
        $bank = $tenant->settings['bank'] ?? null;

        if (! is_array($bank) || empty($bank['iban']) || empty($bank['holder'])) {
            return null;
        }

        return ['holder' => (string) $bank['holder'], 'iban' => (string) $bank['iban']];
    }

    public static function isSet(Tenant $tenant): bool
    {
        return self::details($tenant) !== null;
    }

    public static function store(Tenant $tenant, string $holder, string $iban): void
    {
        $settings = $tenant->settings ?? [];
        $settings['bank'] = ['holder' => trim($holder), 'iban' => self::normalize($iban)];

        $tenant->forceFill(['settings' => $settings])->save();
    }

    public static function forget(Tenant $tenant): void
    {
        $settings = $tenant->settings ?? [];
        unset($settings['bank']);

        $tenant->forceFill(['settings' => $settings])->save();
    }

    public static function normalize(string $iban): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $iban));
    }

    /** Controllo vero dell'IBAN (lunghezza e cifre di controllo, modulo 97). */
    public static function valid(string $iban): bool
    {
        $iban = self::normalize($iban);

        if (! preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $iban)) {
            return false;
        }
        if (str_starts_with($iban, 'IT') && strlen($iban) !== 27) {
            return false;
        }

        $moved = substr($iban, 4).substr($iban, 0, 4);
        $digits = '';
        foreach (str_split($moved) as $ch) {
            $digits .= ctype_alpha($ch) ? (string) (ord($ch) - 55) : $ch;
        }

        $rest = 0;
        foreach (str_split($digits, 7) as $chunk) {
            $rest = (int) (($rest.$chunk) % 97);
        }

        return $rest === 1;
    }

    /** «IT60 X054 2811 1010 0000 0123 456»: più facile da copiare a occhio. */
    public static function pretty(string $iban): string
    {
        return trim(chunk_split(self::normalize($iban), 4, ' '));
    }
}
