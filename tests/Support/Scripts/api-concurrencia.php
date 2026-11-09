<?php

/*
 * Un proceso de la prueba de concurrencia de la API (E9-01): arranca la app
 * contra la base de prueba, espera el instante de salida que le pasan para
 * que los diez procesos choquen, y registra la compra con la Action real.
 * Imprime una línea JSON con el resultado.
 *
 * Uso: php api-concurrencia.php compra {user_id} {payload_json} {salida_unix_ms}
 */

declare(strict_types=1);

use App\Actions\Compras\RegisterCompra;
use App\Compras\NuevaCompra;
use App\Enums\CanalCompra;
use App\Enums\TipoDocumento;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

$app = require dirname(__DIR__, 3) . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, , $userId, $payload, $salida] = $argv;
$datos = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
$user = User::query()->findOrFail((int) $userId);

usleep(max(0, (int) $salida - (int) floor(microtime(true) * 1000)) * 1000);

try {
    $registro = app(RegisterCompra::class)($user, new NuevaCompra(
        (int) $user->comercio_id,
        $user->sede_id,
        TipoDocumento::from($datos['tipo_documento']),
        $datos['documento'],
        $datos['monto'],
        $datos['referencia_externa'],
        CanalCompra::Api,
        $datos['idempotency_key'],
    ));

    echo json_encode(['estado' => $registro->repetida ? 200 : 201]), PHP_EOL;
} catch (Throwable $error) {
    echo json_encode(['estado' => 500, 'error' => $error::class . ': ' . $error->getMessage()]), PHP_EOL;
}
