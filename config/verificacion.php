<?php

declare(strict_types=1);

/*
| Códigos de un solo uso por SMS (E8-06). Reflejados en
| tests/Fixtures/otp-escenarios.json; una prueba verifica que coincidan.
*/

return [
    'digits' => 6,
    'ttl_minutes' => 5,
    'max_attempts' => 5,
    'resend_cooldown_seconds' => 60,
    'max_per_phone_per_hour' => 3,
    'max_per_ip_per_hour' => 10,
    // Tope de todos los SMS de un día, sea quien sea el que los pida: limita el costo si alguien
    // reparte las peticiones entre muchos celulares e IP (auditoría 2026-11, hallazgo 3).
    // TODO(cliente): supuesto del fixture (sending.max_per_day_global), pendiente de confirmar.
    'max_per_day_global' => 1000,
    'message' => 'Tu código del Club Aponte Rivera es {code}. Vence en {minutes} minutos. No lo compartas.',
];
