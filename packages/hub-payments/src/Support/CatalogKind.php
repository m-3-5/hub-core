<?php

namespace M35\HubPayments\Support;

/**
 * Differenze tra le voci vendibili del pannello: stesso flusso (Stripe prodotto + prezzo + Payment Link),
 * cambiano solo tipo, rotte e testi. Servizi e prodotti condividono la tabella payable_services.
 */
class CatalogKind
{
    /**
     * @return array{type: string, route: string, singular: string, plural: string, new: string, quota: bool}
     */
    public static function for(string $type): array
    {
        return match ($type) {
            'product' => [
                'type' => 'product',
                'route' => 'products',
                'singular' => 'prodotto',
                'plural' => 'Prodotti',
                'new' => 'Nuovo prodotto',
                'quota' => false,
            ],
            default => [
                'type' => 'service',
                'route' => 'services',
                'singular' => 'servizio',
                'plural' => 'Servizi a pagamento',
                'new' => 'Nuovo servizio',
                'quota' => true,
            ],
        };
    }
}
