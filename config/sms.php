<?php

declare(strict_types=1);

/*
| SMS sending (E8-06). The driver is chosen by configuration so switching
| provider, or moving to WhatsApp later, does not touch the code.
|
| - array: keeps the messages in memory (tests); refuses to run in production.
| - log: writes the message to the development log; only in `local`.
| - proveedor: the Colombian provider the client chooses.
*/

return [
    'driver' => env('SMS_DRIVER', 'array'),

    'remitente' => env('SMS_REMITENTE', 'ClubAR'),

    // TODO(cliente): proveedor elegido (Inalambria, smsmasivo o Háblame) y sus credenciales.
    'proveedor' => [
        'url' => env('SMS_PROVEEDOR_URL'),
        'token' => env('SMS_PROVEEDOR_TOKEN'),
    ],
];
