<?php

declare(strict_types=1);

/*
| Levels granted by qualifying events (client decisions of 2026-10-10,
| issue #9). Mirrored in tests/Fixtures/niveles-escenarios.json; a test
| asserts they match. Level thresholds and protection live in the `niveles`
| rule of the rule engine (E3-01).
*/

return [
    'event_rules' => [
        'compra_inmueble' => [
            'nivel' => 'diamante',
            'default_validity_months' => 12,
        ],
        'matricula_colegio' => [
            'levels_by_count' => [
                ['min_enrollments' => 1, 'nivel' => 'oro'],
                ['min_enrollments' => 3, 'nivel' => 'diamante'],
            ],
        ],
    ],
];
