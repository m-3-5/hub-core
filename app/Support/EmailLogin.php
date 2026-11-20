<?php

namespace App\Support;

use App\Http\Controllers\RegistrationController;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\EmailLoginLinkNotification;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

/**
 * Accesso con un link via email, senza password (privati che pubblicano un annuncio o riscattano un buono).
 * Il link vale un'ora; l'email viaggia cifrata. Chi non ha ancora un account lo riceve al clic: un'attività di tipo «privato».
 */
class EmailLogin
{
    public const MINUTES = 60;

    public static function url(string $email, string $next): string
    {
        return URL::temporarySignedRoute('email-login.go', now()->addMinutes(self::MINUTES), [
            't' => Crypt::encryptString(json_encode(['e' => Str::lower(trim($email)), 'n' => self::safeNext($next)])),
        ]);
    }

    /** @return array{email: string, next: string}|null */
    public static function read(string $token): ?array
    {
        try {
            $data = json_decode(Crypt::decryptString($token), true);
        } catch (Throwable) {
            return null;
        }

        return is_array($data) && filter_var($data['e'] ?? null, FILTER_VALIDATE_EMAIL)
            ? ['email' => $data['e'], 'next' => self::safeNext($data['n'] ?? null)]
            : null;
    }

    /** Solo indirizzi di questo sito («/qualcosa»), mai verso altri siti. */
    public static function safeNext(?string $next): string
    {
        $next = (string) $next;

        return str_starts_with($next, '/') && ! str_starts_with($next, '//') && ! str_contains($next, '\\') ? $next : '/app';
    }

    /** Manda il link. $what = cosa succede dopo il clic (testo dell'email). */
    public static function send(string $email, string $next, string $what = 'entrare in Hub Core'): void
    {
        try {
            Notification::route('mail', Str::lower(trim($email)))->notify(new EmailLoginLinkNotification(self::url($email, $next), $what));
        } catch (Throwable $e) {
            Log::warning('Link di accesso via email non inviato', ['message' => $e->getMessage()]);
        }
    }

    /** L'utente con questa email; se manca, nasce un account «privato» (attività + titolare). */
    public static function userFor(string $email, ?string $name = null): User
    {
        $email = Str::lower(trim($email));

        if ($user = User::whereRaw('LOWER(email) = ?', [$email])->first()) {
            return $user;
        }

        $name = trim((string) $name) ?: Str::ucfirst(Str::before($email, '@'));
        [, $user] = RegistrationController::createAccount('privato', $name, $name, $email, null, null, 'annunci');

        return $user;
    }

    /** L'attività con cui un utente pubblica i suoi annunci (o riscatta i buoni): quella da privato, se c'è. */
    public static function tenantFor(User $user): ?Tenant
    {
        $tenants = $user->tenants()->orderBy('tenants.id')->get();

        return $tenants->firstWhere('type', 'privato') ?? $tenants->first();
    }

    public static function rememberAfterLogin(string $path): void
    {
        session(['after_login' => self::safeNext($path)]);
    }

    public static function pullAfterLogin(): ?string
    {
        $path = session()->pull('after_login');

        return $path ? self::safeNext($path) : null;
    }
}
