<?php

declare(strict_types=1);

/*
| Pantalla del cajero (E4-02). Reflejado en tests/Fixtures/cajero-escenarios.json;
| una prueba verifica que coincidan.
*/

return [
    'max_amount_digits' => 9,
    'auto_return_seconds' => 5,
    'annul_hours' => 72,
    'annul_reason' => 'Anulada desde la caja',

    'login_max_attempts' => 5,
    'login_lockout_minutes' => 15,
    'login_max_attempts_per_ip' => 20,

    'search_max_per_minute' => 20,

    // Entrega de beneficios (E5-02), reflejado en tests/Fixtures/redencion-escenarios.json.
    'redeem_confirm_window_seconds' => 180,
    'support_max_length' => 100,
];
