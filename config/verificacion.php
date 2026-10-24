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
    'message' => 'Tu código del Club Aponte Rivera es {code}. Vence en {minutes} minutos. No lo compartas.',
];
