<?php

declare(strict_types=1);

/*
| Envío de SMS (E8-06). El driver se elige por configuración, así cambiar de
| proveedor, o pasar a WhatsApp más adelante, no toca el código.
|
| - array: guarda los mensajes en memoria (pruebas); no corre en producción.
| - log: escribe el mensaje en el log de desarrollo; solo en `local`.
| - proveedor: el proveedor colombiano que elija el cliente.
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
