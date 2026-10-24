<?php

declare(strict_types=1);

/*
| One-time SMS codes (E8-06). Mirrored in tests/Fixtures/otp-escenarios.json;
| a test asserts they match.
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
