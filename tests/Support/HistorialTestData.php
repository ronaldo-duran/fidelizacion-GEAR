<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\TipoDocumento;
use App\Models\Cliente;
use App\Models\Comercio;
use App\Models\MovimientoPuntos;
use App\Models\Regla;
use App\Models\Transaccion;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Test data for the customer history and profile (E8-05): the customer and
 * the ledger come from tests/Fixtures/historial-perfil-escenarios.json. The
 * movements are written straight into the ledger with their dates, and the
 * balance is their sum, as the ledger actions leave it.
 */
final class HistorialTestData
{
    /**
     * @return array<string, mixed>
     */
    public static function fixture(): array
    {
        $json = file_get_contents(dirname(__DIR__) . '/Fixtures/historial-perfil-escenarios.json');

        return json_decode((string) $json, true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * The fixture customer, with the given movements (all by default).
     *
     * @param  list<array<string, mixed>>|null  $movimientos
     */
    public static function cliente(?array $movimientos = null, ?string $documento = null): Cliente
    {
        $fixture = self::fixture();
        $datos = $fixture['customer'];
        $cliente = Cliente::query()->create([
            'tipo_documento' => TipoDocumento::from($datos['tipo_documento']),
            'documento' => $documento ?? $datos['documento'],
            'celular' => $datos['celular'],
            'email' => $datos['email'],
        ]);
        $cliente->forceFill([
            'nombre_completo' => $datos['nombre_completo'],
            'nivel_por_puntos' => $datos['nivel_por_puntos'],
            'nivel_efectivo' => $datos['nivel_efectivo'],
            'nivel_efectivo_desde' => CarbonImmutable::parse($datos['nivel_efectivo_desde']),
        ])->save();

        $saldo = self::ledger($cliente, $movimientos ?? $fixture['movements']);
        $cliente->forceFill(['saldo_puntos' => $saldo])->save();

        return $cliente->refresh();
    }

    /**
     * The accrual rule that sets the 12-month window, in force since before the oldest movement.
     */
    public static function acumulacionRegla(): Regla
    {
        $hoy = CarbonImmutable::now();
        CarbonImmutable::setTestNow('2025-01-01 00:00:00');
        $regla = LedgerTestData::acumulacionRegla();
        CarbonImmutable::setTestNow($hoy);

        return $regla;
    }

    /**
     * @param  list<array<string, mixed>>  $movimientos
     */
    private static function ledger(Cliente $cliente, array $movimientos): int
    {
        $regla = Regla::query()->where('codigo', 'acumulacion-base')->firstOrFail();
        $admin = User::factory()->administradorDelGrupo()->create();
        $meses = self::fixture()['history']['validity_months'];
        $creados = [];

        foreach ($movimientos as $movimiento) {
            $at = CarbonImmutable::parse($movimiento['at']);
            $comercio = self::comercio($movimiento['comercio'] ?? null);
            $origen = $creados[$movimiento['reverses'] ?? ''] ?? null;
            $transaccionId = $origen?->transaccion_id ?? (isset($movimiento['monto'])
                ? Transaccion::factory()->create(['comercio_id' => $comercio?->id, 'monto' => $movimiento['monto']])->id
                : null);
            $conRegla = in_array($movimiento['tipo'], ['acumulacion', 'reversa', 'promocion'], true);
            $conUsuario = in_array($movimiento['tipo'], ['reversa', 'ajuste_manual'], true);

            $creados[$movimiento['key']] = MovimientoPuntos::query()->forceCreate([
                'cliente_id' => $cliente->id,
                'comercio_id' => $comercio?->id,
                'transaccion_id' => $transaccionId,
                'tipo' => $movimiento['tipo'],
                'puntos' => $movimiento['puntos'],
                'regla_id' => $conRegla ? $regla->id : null,
                'regla_version' => $conRegla ? $regla->version : null,
                'vigencia_hasta' => $movimiento['puntos'] > 0 ? $at->startOfDay()->addMonths($meses) : null,
                'motivo' => $movimiento['motivo']
                    ?? ($conUsuario || $movimiento['tipo'] === 'caducidad' ? 'Prueba' : null),
                'usuario_id' => $conUsuario ? $admin->id : null,
                'created_at' => $at,
            ]);
        }

        return array_sum(array_map(static fn (MovimientoPuntos $creado): int => $creado->puntos, $creados));
    }

    private static function comercio(?string $nombre): ?Comercio
    {
        if ($nombre === null) {
            return null;
        }

        return Comercio::query()->where('razon_social', $nombre)->first()
            ?? Comercio::factory()->create(['razon_social' => $nombre]);
    }
}
