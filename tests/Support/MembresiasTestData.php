<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\EstadoPago;
use App\Models\Cliente;
use App\Models\PagoMembresia;
use App\Models\PlanMembresia;
use App\Pagos\WompiPasarela;
use Illuminate\Support\Str;

/**
 * Datos de prueba de las membresías y los pagos (E6-01).
 */
final class MembresiasTestData
{
    public const SECRETO_EVENTOS = 'test_events_secreto_de_prueba';

    public const SECRETO_INTEGRIDAD = 'test_integrity_secreto_de_prueba';

    private const ESTADOS_WOMPI = ['aprobado' => 'APPROVED', 'rechazado' => 'DECLINED', 'anulado' => 'VOIDED'];

    /**
     * @return array<string, mixed>
     */
    public static function fixture(): array
    {
        $path = __DIR__ . '/../Fixtures/membresias-escenarios.json';

        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    public static function configurarWompi(): void
    {
        config([
            'pagos.wompi.llave_publica' => 'pub_test_llave',
            'pagos.wompi.secreto_integridad' => self::SECRETO_INTEGRIDAD,
            'pagos.wompi.secreto_eventos' => self::SECRETO_EVENTOS,
        ]);
    }

    public static function plan(int $duracionMeses = 1): PlanMembresia
    {
        return PlanMembresia::query()->where('duracion_meses', $duracionMeses)->firstOrFail();
    }

    public static function pago(
        Cliente $cliente,
        PlanMembresia $plan,
        EstadoPago $estado = EstadoPago::Pendiente,
    ): PagoMembresia {
        $pago = PagoMembresia::query()->create([
            'cliente_id' => $cliente->id,
            'plan_id' => $plan->id,
            'referencia' => 'M' . Str::upper((string) Str::ulid()),
            'pasarela' => 'wompi',
            'nivel' => $plan->nivel,
            'duracion_meses' => $plan->duracion_meses,
            'monto_cop' => $plan->precio_cop,
        ]);

        if ($estado !== EstadoPago::Pendiente) {
            $pago->forceFill(['estado' => $estado, 'id_externo' => 'previo-' . $pago->id])->save();
        }

        return $pago;
    }

    /**
     * Un evento `transaction.updated` de Wompi, firmado con el secreto de
     * prueba (o con una firma falsa).
     *
     * @return array<string, mixed>
     */
    public static function evento(
        string $referencia,
        string $estado,
        int $montoCop,
        string $moneda = 'COP',
        bool $firmaValida = true,
        string $idExterno = '12345-1700000000-10001',
    ): array {
        $evento = [
            'event' => 'transaction.updated',
            'data' => [
                'transaction' => [
                    'id' => $idExterno,
                    'amount_in_cents' => $montoCop * 100,
                    'reference' => $referencia,
                    'currency' => $moneda,
                    'payment_method_type' => 'CARD',
                    'status' => self::ESTADOS_WOMPI[$estado] ?? $estado,
                ],
            ],
            'environment' => 'test',
            'signature' => [
                'properties' => ['transaction.id', 'transaction.status', 'transaction.amount_in_cents'],
                'checksum' => '',
            ],
            'timestamp' => 1793750400,
            'sent_at' => '2026-11-04T15:00:00.000Z',
        ];
        $evento['signature']['checksum'] = $firmaValida
            ? strtoupper((string) WompiPasarela::checksum($evento, self::SECRETO_EVENTOS))
            : str_repeat('A', 64);

        return $evento;
    }
}
