<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Buoni credito vinti ai giochi di piramide35.com, riscattati qui come credito euro.
 *
 * Formato (lo genera il server di piramide35): M35G-{centesimi}-{AAMMGG scadenza}-{6 caratteri A-Z0-9}-{firma}
 * firma = primi 10 caratteri esadecimali MAIUSCOLI di hash_hmac('sha256', "M35G|{centesimi}|{AAMMGG}|{casuali}", GIOCO_BUONI_SEGRETO)
 * Esempio: M35G-100-261231-K7Q2ZP-9F3A1C2B4D
 *
 * Ogni codice si usa una volta sola (tabella game_vouchers, codice unico); al massimo N buoni al mese per utente/attività.
 */
class GameVoucher
{
    public const PREFIX = 'M35G';

    public static function enabled(): bool
    {
        return (string) config('services.game_vouchers.secret') !== '';
    }

    public static function normalize(string $code): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($code)));
    }

    public static function signature(int $cents, string $yymmdd, string $random): string
    {
        return strtoupper(substr(hash_hmac('sha256', self::PREFIX."|{$cents}|{$yymmdd}|{$random}", (string) config('services.game_vouchers.secret')), 0, 10));
    }

    /** Per i test e per provare: crea un codice valido con il segreto di questo sito. */
    public static function make(int $cents, Carbon $expires, string $random): string
    {
        $date = $expires->format('ymd');

        return implode('-', [self::PREFIX, $cents, $date, $random, self::signature($cents, $date, $random)]);
    }

    /**
     * Controlla il codice (senza usarlo).
     *
     * @return array{ok: true, code: string, cents: int, expires_on: Carbon}|array{ok: false, error: string}
     */
    public static function check(string $raw): array
    {
        if (! self::enabled()) {
            return ['ok' => false, 'error' => 'I buoni non sono ancora attivi.'];
        }

        $code = self::normalize($raw);

        if (! preg_match('/^M35G-([1-9]\d{0,5})-(\d{6})-([A-Z0-9]{6})-([0-9A-F]{10})$/', $code, $m)) {
            return ['ok' => false, 'error' => 'Codice non valido: controlla di averlo scritto tutto.'];
        }

        [, $cents, $date, $random, $sig] = $m;
        $cents = (int) $cents;

        if (! hash_equals(self::signature($cents, $date, $random), $sig)) {
            return ['ok' => false, 'error' => 'Codice non valido: controlla di averlo scritto tutto.'];
        }

        try {
            $expires = Carbon::createFromFormat('!ymd', $date, config('app.timezone'));
        } catch (\Throwable) {
            $expires = null;
        }
        if (! $expires || $expires->format('ymd') !== $date) {
            return ['ok' => false, 'error' => 'Codice non valido.'];
        }
        if ($expires->copy()->endOfDay()->isPast()) {
            return ['ok' => false, 'error' => 'Questo buono è scaduto il '.$expires->format('d/m/Y').'.'];
        }
        if ($cents > (int) config('services.game_vouchers.max_cents', 500)) {
            return ['ok' => false, 'error' => 'Codice non valido.'];
        }

        return ['ok' => true, 'code' => $code, 'cents' => $cents, 'expires_on' => $expires];
    }

    public static function monthlyLimit(): int
    {
        return max(0, (int) config('services.game_vouchers.monthly_limit', 3));
    }

    /** Buoni già usati questo mese (dall'utente o dalla sua attività). */
    public static function usedThisMonth(Tenant $tenant, ?User $user): int
    {
        return DB::table('game_vouchers')
            ->where('redeemed_at', '>=', now()->startOfMonth())
            ->where(fn ($q) => $q->where('tenant_id', $tenant->id)->when($user, fn ($q) => $q->orWhere('user_id', $user->id)))
            ->count();
    }

    /**
     * Riscatta: il credito va all'attività con un movimento «+ 1 € buono gioco piramide35».
     *
     * @return array{ok: true, cents: int}|array{ok: false, error: string}
     */
    public static function redeem(Tenant $tenant, ?User $user, string $raw): array
    {
        $check = self::check($raw);
        if (! $check['ok']) {
            return $check;
        }

        if (DB::table('game_vouchers')->where('code', $check['code'])->exists()) {
            return ['ok' => false, 'error' => 'Questo buono è già stato usato.'];
        }

        if (self::usedThisMonth($tenant, $user) >= self::monthlyLimit()) {
            return ['ok' => false, 'error' => 'Hai già usato '.self::monthlyLimit().' buoni questo mese: il prossimo dal 1° del mese.'];
        }

        try {
            DB::transaction(function () use ($tenant, $user, $check) {
                // il codice unico blocca anche due riscatti nello stesso istante
                DB::table('game_vouchers')->insert([
                    'code' => $check['code'],
                    'cents' => $check['cents'],
                    'expires_on' => $check['expires_on']->toDateString(),
                    'tenant_id' => $tenant->id,
                    'user_id' => $user?->id,
                    'redeemed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                PayPerUse::addVoucher($tenant, $check['cents'], $check['code']);
            });
        } catch (QueryException) {
            return ['ok' => false, 'error' => 'Questo buono è già stato usato.'];
        }

        return ['ok' => true, 'cents' => $check['cents']];
    }
}
