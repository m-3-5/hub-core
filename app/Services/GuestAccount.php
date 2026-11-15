<?php

namespace App\Services;

use App\Models\Promo;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Chi ha iniziato da ospite si registra UNA volta sola, al momento di pubblicare:
 * email oppure cellulare, più una password (oppure Google). Niente carta.
 * Le promo passano sempre dal controllo dell'IA prima di andare online (se il controllo non risponde, non si pubblica).
 */
class GuestAccount
{
    public const GUEST_EMAIL_DOMAIN = '@guest.hub-core.local';

    /** «nome@esempio.it» o «333 123 4567» → ['email', 'nome@esempio.it'] / ['phone', '+393331234567']; null se non va bene. */
    public static function parseContact(string $raw): ?array
    {
        $raw = trim($raw);

        if (str_contains($raw, '@')) {
            $email = Str::lower($raw);

            return filter_var($email, FILTER_VALIDATE_EMAIL) && ! str_ends_with($email, self::GUEST_EMAIL_DOMAIN) ? ['email', $email] : null;
        }

        return ($phone = self::normalizePhone($raw)) ? ['phone', $phone] : null;
    }

    /** Numero in formato unico (+39…): spazi, trattini e punti tolti; 00 → +; senza prefisso = Italia. */
    public static function normalizePhone(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '' || preg_match('/[^0-9+\s\-.\/()]/', $raw)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $raw);
        if (str_starts_with($raw, '+')) {
            $phone = '+'.$digits;
        } elseif (str_starts_with($digits, '00')) {
            $phone = '+'.substr($digits, 2);
        } else {
            $phone = '+39'.$digits;
        }

        return preg_match('/^\+\d{9,15}$/', $phone) ? $phone : null;
    }

    public static function isGuestUser(User $user): bool
    {
        return str_ends_with(Str::lower($user->email), self::GUEST_EMAIL_DOMAIN);
    }

    /** Il contatto è già di un altro account? */
    public function contactTaken(array $contact, ?User $except = null): bool
    {
        [$type, $value] = $contact;
        $query = $type === 'email' ? User::whereRaw('LOWER(email) = ?', [$value]) : User::where('phone', $value);

        return $query->when($except, fn ($q) => $q->whereKeyNot($except->getKey()))->exists();
    }

    /** L'ospite diventa un account vero: email o cellulare + password, attività confermata. */
    public function claim(Tenant $tenant, User $user, array $contact, ?string $password): void
    {
        [$type, $value] = $contact;

        $user->forceFill(array_filter([
            $type => $value,
            'password' => $password,
            'email_verified_at' => $type === 'email' && $password === null ? now() : null, // da Google: email già verificata
        ], fn ($v) => $v !== null))->save();

        $tenant->update([
            'guest_verified_at' => now(),
            'guest_email_token' => null,
            'guest_email_token_expires_at' => null,
        ]);
    }

    /**
     * Pubblica una promo dopo la registrazione, solo se l'IA la ritiene adatta.
     *
     * @return array{published: bool, message: string}
     */
    public function publishPromo(Tenant $tenant, Promo $promo): array
    {
        if (! $promo->isDraft()) {
            return ['published' => true, 'message' => 'Fatto! Sei registrato.'];
        }

        $check = app(ContentModerator::class)->checkPromo($promo);

        if (! $check['allowed']) {
            return ['published' => false, 'message' => $check['unavailable']
                ? 'Registrazione fatta. Il controllo automatico non risponde: riprova a pubblicare tra qualche minuto.'
                : 'Registrazione fatta, ma non posso pubblicare questa promo: '.$check['reason'].' Modificala e riprova.'];
        }

        $promo->markPublished();
        app(WordPressWebhookDispatcher::class)->promoPublished($tenant, $promo->fresh());

        return ['published' => true, 'message' => 'Fatto! La tua promo è online.'];
    }
}
