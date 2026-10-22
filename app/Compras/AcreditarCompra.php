<?php

declare(strict_types=1);

namespace App\Compras;

use App\Actions\Puntos\RecordAcumulacion;
use App\Enums\TipoMovimiento;
use App\Models\Cliente;
use App\Models\MovimientoPuntos;
use App\Models\Regla;
use App\Models\Transaccion;
use App\Reglas\CompraData;
use App\Reglas\PuntosCalculator;
use App\Reglas\PuntosDesglose;
use App\Reglas\ReglaAplicada;
use App\Reglas\ReglasEnFecha;
use Carbon\CarbonImmutable;

/**
 * Grants a purchase's points to a customer whose row the caller already
 * locked, inside the caller's transaction. The rules are the ones in force
 * on the purchase date (hard rule 3.3) and the day and month caps count the
 * customer's real accruals of purchases of that day and month, in
 * America/Bogota.
 *
 * With a bonus there are two accrual movements, each with the snapshot of
 * its rule; a cap trims the bonus points first. Points expire counting from
 * the purchase date. They only enter through RecordAcumulacion (E3-02).
 */
class AcreditarCompra
{
    private readonly PuntosCalculator $calculator;

    private readonly ReglasEnFecha $reglas;

    private readonly RecordAcumulacion $recordAcumulacion;

    public function __construct(
        PuntosCalculator $calculator,
        ReglasEnFecha $reglas,
        RecordAcumulacion $recordAcumulacion,
    ) {
        $this->calculator = $calculator;
        $this->reglas = $reglas;
        $this->recordAcumulacion = $recordAcumulacion;
    }

    public function __invoke(Cliente $cliente, Transaccion $transaccion, string $categoriaSlug): PuntosDesglose
    {
        $fecha = $transaccion->created_at;
        $compra = new CompraData(
            $transaccion->monto,
            $categoriaSlug,
            $transaccion->comercio_id,
            $transaccion->sede_id,
            $fecha,
        );

        $desglose = $this->calculator->calculate(
            $compra,
            $this->reglas->inForceAt($fecha),
            $this->earned($cliente->id, $fecha->startOfDay(), $fecha->endOfDay()),
            $this->earned($cliente->id, $fecha->startOfMonth(), $fecha->endOfMonth()),
        );

        $this->record($cliente, $transaccion, $desglose->acumulacion, $desglose->basePuntos(), $desglose);
        $this->record($cliente, $transaccion, $desglose->bonificacion, $desglose->extraPuntos(), $desglose);

        return $desglose;
    }

    private function record(
        Cliente $cliente,
        Transaccion $transaccion,
        ?ReglaAplicada $regla,
        int $puntos,
        PuntosDesglose $desglose,
    ): void {
        if ($regla === null || $regla->id === null || $puntos <= 0) {
            return;
        }

        ($this->recordAcumulacion)(
            $cliente->id,
            $transaccion->comercio_id,
            $transaccion->id,
            $puntos,
            Regla::query()->findOrFail($regla->id),
            $transaccion->created_at->addMonths($this->vigenciaMeses($desglose)),
        );
    }

    private function vigenciaMeses(PuntosDesglose $desglose): int
    {
        $meses = $desglose->acumulacion->definicion['vigencia_puntos_meses'] ?? null;

        return is_int($meses) && $meses > 0 ? $meses : 12;
    }

    /**
     * Accrual points the customer earned with purchases made in that period.
     */
    private function earned(int $clienteId, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $compras = Transaccion::query()->toBase()
            ->select('id')
            ->where('cliente_id', $clienteId)
            ->whereBetween('created_at', [$from, $to]);

        $sum = MovimientoPuntos::query()->toBase()
            ->where('cliente_id', $clienteId)
            ->where('tipo', TipoMovimiento::Acumulacion->value)
            ->whereIn('transaccion_id', $compras)
            ->sum('puntos');

        return is_numeric($sum) ? (int) $sum : 0;
    }
}
