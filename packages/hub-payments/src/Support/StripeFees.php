<?php

namespace M35\HubPayments\Support;

/**
 * Trattenute di Stripe sui pagamenti con carta, spiegate al venditore (valori indicativi, cambiabili da .env:
 * le tariffe vere le decide Stripe e possono variare per tipo di carta o paese).
 */
class StripeFees
{
    public static function percent(): float
    {
        return (float) config('hub-payments.fees.card_percent', 1.5);
    }

    public static function fixedCents(): int
    {
        return (int) config('hub-payments.fees.card_fixed_cents', 25);
    }

    /** Trattenuta stimata su un incasso, in centesimi. */
    public static function estimateCents(int $amountCents): int
    {
        return (int) round($amountCents * self::percent() / 100) + self::fixedCents();
    }

    public static function format(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.');
    }

    /** Frase breve con la tariffa indicativa, es. «1,5% + 0,25 € a pagamento». */
    public static function rateLabel(): string
    {
        return rtrim(rtrim(number_format(self::percent(), 2, ',', ''), '0'), ',').'% + '.self::format(self::fixedCents()).' € a pagamento';
    }

    /** Esempio concreto: su 100 € trattengono X, ricevi Y. */
    public static function example(int $amountCents = 10000): array
    {
        $fee = self::estimateCents($amountCents);

        return [
            'amount' => self::format($amountCents),
            'fee' => self::format($fee),
            'net' => self::format($amountCents - $fee),
        ];
    }
}
