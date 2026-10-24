<?php

declare(strict_types=1);

/*
| Signed rotating QR (E4-04, hard rule 3.12). Mirrored in
| tests/Fixtures/qr-escenarios.json; a test asserts they match.
*/

return [
    'ttl_seconds' => 30,
    'refresh_seconds' => 20,
    'clock_skew_seconds' => 5,
    'identify_prefix' => 'AR',
    'max_failed_short_codes_per_minute' => 5,
];
