<?php

declare(strict_types=1);

/*
| Registro y acceso del cliente (E8-02). Reflejado en
| tests/Fixtures/registro-escenarios.json; una prueba verifica que coincidan.
*/

return [
    'documento_patterns' => [
        'CC' => '^[0-9]{6,10}$',
        'CE' => '^[0-9A-Z]{4,12}$',
        'PPT' => '^[0-9A-Z]{4,15}$',
        'PA' => '^[0-9A-Z]{5,15}$',
        'DEX' => '^[0-9A-Z]{4,20}$',
    ],
    'celular_pattern' => '^3[0-9]{9}$',
    'pin_digits' => 4,
    'edad_minima_anios' => 18,
    'nombre_min_length' => 3,
    'nombre_max_length' => 120,

    'login_max_attempts' => 5,
    'login_lockout_minutes' => 15,
    'login_max_attempts_per_ip' => 20,
    'registro_pendiente_minutes' => 30,
    'enlace_caja_minutes' => 30,

    // TODO(cliente): versión del texto de la autorización; la define E10-03 con el área legal.
    'autorizacion_version' => 'borrador-2026-10',
];
