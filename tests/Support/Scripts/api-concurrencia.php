<?php

/*
 * Un proceso de la prueba de concurrencia de la API (E9-01): arranca la app
 * contra la base de prueba, espera el instante de salida que le pasan para
 * que los diez procesos choquen, y registra la compra, la anula o entrega el
 * beneficio con las Actions reales. Imprime una línea JSON con el resultado:
 * 201 si hizo la operación y 200 si fue una repetición.
 *
 * Uso: php api-concurrencia.php {compra|anulacion|redencion} {user_id} {payload_json} {salida_unix_ms}
 */

declare(strict_types=1);

use App\Actions\Api\AnnulCompraApi;
use App\Actions\Api\RedeemBeneficioApi;
use App\Actions\Compras\RegisterCompra;
use App\Api\EntregaApi;
use App\Compras\NuevaCompra;
use App\Enums\CanalCompra;
use App\Enums\TipoDocumento;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

$app = require dirname(__DIR__, 3) . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $modo, $userId, $payload, $salida] = $argv;
$datos = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
$user = User::query()->findOrFail((int) $userId);

usleep(max(0, (int) $salida - (int) floor(microtime(true) * 1000)) * 1000);

try {
    $repetido = match ($modo) {
        'anulacion' => app(AnnulCompraApi::class)($user, $datos['referencia_externa'], $datos['motivo'])->repetido,
        'redencion' => app(RedeemBeneficioApi::class)($user, new EntregaApi(
            null,
            TipoDocumento::from($datos['tipo_documento']),
            $datos['documento'],
            $datos['beneficio_id'],
            null,
            null,
            $datos['idempotency_key'],
        ))->repetido,
        default => app(RegisterCompra::class)($user, new NuevaCompra(
            (int) $user->comercio_id,
            $user->sede_id,
            TipoDocumento::from($datos['tipo_documento']),
            $datos['documento'],
            $datos['monto'],
            $datos['referencia_externa'],
            CanalCompra::Api,
            $datos['idempotency_key'],
        ))->repetida,
    };

    echo json_encode(['estado' => $repetido ? 200 : 201]), PHP_EOL;
} catch (Throwable $error) {
    echo json_encode(['estado' => 500, 'error' => $error::class . ': ' . $error->getMessage()]), PHP_EOL;
}
