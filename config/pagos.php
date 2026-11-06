<?php

declare(strict_types=1);

use App\Pagos\SimuladaPasarela;
use App\Pagos\WompiPasarela;

/*
| Pagos en línea de las membresías (E6-01). El cliente eligió Wompi (issue
| #1, decisión 9). Cada pasarela es un adaptador del contrato
| App\Pagos\PasarelaPagos; el webhook llega a /webhooks/pagos/{pasarela}.
|
| Los planes iniciales replican tests/Fixtures/membresias-escenarios.json;
| una prueba verifica que coincidan. Después de la migración se administran
| desde el panel.
|
| La pasarela simulada existe solo para local y las pruebas E2E: aprueba o
| rechaza un pago desde una pantalla propia, sin dinero real. Nunca se
| habilita en producción (SimuladaPasarela lo verifica otra vez).
*/

$simulada = (bool) env('PAGOS_SIMULADA', false);

return [
    'pasarela' => env('PAGOS_PASARELA', 'wompi'),

    'simulada' => $simulada,

    'pasarelas' => array_filter([
        'wompi' => WompiPasarela::class,
        'simulada' => $simulada ? SimuladaPasarela::class : null,
    ]),

    'wompi' => [
        'checkout_url' => env('WOMPI_CHECKOUT_URL', 'https://checkout.wompi.co/p/'),
        'llave_publica' => env('WOMPI_LLAVE_PUBLICA', ''),
        'secreto_integridad' => env('WOMPI_SECRETO_INTEGRIDAD', ''),
        'secreto_eventos' => env('WOMPI_SECRETO_EVENTOS', ''),
    ],

    // Un pago pendiente del mismo plan se reutiliza durante estos minutos (doble
    // toque, reintento o volver atrás desde la pasarela) en lugar de abrir otro.
    'reusar_pendiente_minutos' => 30,

    // Días antes del fin de la membresía en que se avisa de la renovación (E6-01, regla 10).
    // TODO(cliente): supuesto del fixture (renewal.notice_days_before), pendiente de confirmar.
    'avisos_renovacion_dias' => [7, 1],

    'planes_iniciales' => [
        ['nombre' => 'Diamante mensual', 'nivel' => 'diamante', 'duracion_meses' => 1, 'precio_cop' => 49900],
        ['nombre' => 'Diamante anual', 'nivel' => 'diamante', 'duracion_meses' => 12, 'precio_cop' => 499000],
    ],
];
