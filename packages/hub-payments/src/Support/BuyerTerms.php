<?php

namespace M35\HubPayments\Support;

/**
 * Condizioni di acquisto con pagamento protetto: il compratore le accetta (casella obbligatoria) prima di pagare.
 * Si registrano versione, data e indirizzo IP sull'ordine. Se il testo cambia in modo rilevante, alzare VERSION.
 */
class BuyerTerms
{
    public const VERSION = '2026-10-15';
}
