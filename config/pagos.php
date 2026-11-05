<?php

declare(strict_types=1);

use App\Pagos\WompiPasarela;

/*
| Pagos en línea de las membresías (E6-01). El cliente eligió Wompi (issue
| #1, decisión 9). Cada pasarela es un adaptador del contrato
| App\Pagos\PasarelaPagos; el webhook llega a /webhooks/pagos/{pasarela}.
|
| Los planes iniciales replican tests/Fixtures/membresias-escenarios.json;
| una prueba verifica que coincidan. Después de la migración se administran
| desde el panel.
*/

return [
    'pasarela' => env('PAGOS_PASARELA', 'wompi'),

    'pasarelas' => [
        'wompi' => WompiPasarela::class,
    ],

    'wompi' => [
        'checkout_url' => env('WOMPI_CHECKOUT_URL', 'https://checkout.wompi.co/p/'),
        'llave_publica' => env('WOMPI_LLAVE_PUBLICA', ''),
        'secreto_integridad' => env('WOMPI_SECRETO_INTEGRIDAD', ''),
        'secreto_eventos' => env('WOMPI_SECRETO_EVENTOS', ''),
    ],

    'planes_iniciales' => [
        ['nombre' => 'Diamante mensual', 'nivel' => 'diamante', 'duracion_meses' => 1, 'precio_cop' => 49900],
        ['nombre' => 'Diamante anual', 'nivel' => 'diamante', 'duracion_meses' => 12, 'precio_cop' => 499000],
    ],
];
