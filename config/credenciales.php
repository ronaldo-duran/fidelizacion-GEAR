<?php

declare(strict_types=1);

/*
| QR firmado y rotativo (E4-04, regla dura 3.12). Reflejado en
| tests/Fixtures/qr-escenarios.json; una prueba verifica que coincidan.
*/

return [
    'ttl_seconds' => 30,
    'refresh_seconds' => 20,
    'clock_skew_seconds' => 5,
    'identify_prefix' => 'AR',
    'max_failed_short_codes_per_minute' => 5,
];
