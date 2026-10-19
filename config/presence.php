<?php

/**
 * Dove siamo presenti (mostrato nella landing «Siti web e app» e in «Dove siamo»).
 * Per aggiungere o togliere un punto basta modificare questo elenco: nessun altro file.
 * `query` è ciò che si cerca su Google Maps (il pulsante apre la scheda vera del luogo).
 * La posizione su Google non si aggiorna da sola: serve un'API di Google a pagamento, qui si usa il collegamento a Maps.
 */
return [
    'points' => [
        ['name' => 'Corigliano-Rossano', 'detail' => 'Fabrizio', 'province' => 'CS', 'role' => 'Sede operativa', 'query' => 'Fabrizio, Corigliano-Rossano', 'here' => true],
        ['name' => 'Castrovillari', 'detail' => null, 'province' => 'CS', 'role' => 'Punto di riferimento', 'query' => 'Castrovillari', 'here' => false],
        ['name' => 'Castiglione delle Stiviere', 'detail' => null, 'province' => 'MN', 'role' => 'Con la nostra socia', 'query' => 'Castiglione delle Stiviere', 'here' => false],
        ['name' => 'Senise', 'detail' => null, 'province' => 'PZ', 'role' => 'Sede legale', 'query' => 'Senise, Potenza', 'here' => false],
    ],
];
