<?php

declare(strict_types=1);

namespace App\Actions\Nocturno;

use App\Enums\Nivel;
use App\Models\AvisoDescenso;
use App\Models\Cliente;
use App\Models\MovimientoPuntos;
use App\Niveles\NivelCalculator;
use App\Niveles\NivelEfectivo;
use App\Puntos\LoteCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Registers the demotion notices a customer is owed: for each number of days
 * in `avisos_dias_antes` of the `niveles` rule, if points expire on exactly
 * that day and losing them would lower the effective level, counting the
 * membership or event that will still be valid then. No drop, no notice.
 *
 * A notice already registered is skipped (the customer row is locked, and a
 * unique constraint backs it up), so running twice never duplicates it. The
 * notices of a missed night are not sent late.
 */
class RegisterAvisos
{
    private readonly NivelEfectivo $niveles;

    private readonly NivelCalculator $calculator;

    private readonly LoteCalculator $lotes;

    public function __construct(NivelEfectivo $niveles, NivelCalculator $calculator, LoteCalculator $lotes)
    {
        $this->niveles = $niveles;
        $this->calculator = $calculator;
        $this->lotes = $lotes;
    }

    /**
     * @return list<AvisoDescenso> the notices registered
     */
    public function __invoke(int $clienteId, CarbonImmutable $corte): array
    {
        return DB::transaction(function () use ($clienteId, $corte): array {
            $cliente = $this->niveles->lockCliente($clienteId);
            $definicion = $this->niveles->definicionNiveles();
            $expirations = $this->expirations($clienteId);
            $avisos = [];

            foreach (self::diasAntes($definicion) as $dias) {
                $fecha = $corte->startOfDay()->addDays($dias);
                $aviso = $this->aviso($cliente, $definicion, $expirations, $fecha, $dias);

                if ($aviso !== null) {
                    $avisos[] = $aviso;
                }
            }

            return $avisos;
        });
    }

    /**
     * @param  array<array-key, mixed>  $definicion
     * @param  array<string, int>  $expirations  remaining points per expiry date
     */
    private function aviso(
        Cliente $cliente,
        array $definicion,
        array $expirations,
        CarbonImmutable $fecha,
        int $dias,
    ): ?AvisoDescenso {
        $vencen = $expirations[$fecha->toDateString()] ?? 0;

        if ($vencen <= 0 || $this->alreadyRegistered($cliente->id, $fecha, $dias)) {
            return null;
        }

        $hastaEseDia = array_sum(array_filter(
            $expirations,
            static fn (string $day): bool => $day <= $fecha->toDateString(),
            ARRAY_FILTER_USE_KEY,
        ));
        $resultante = $this->nivelOn($cliente, $cliente->saldo_puntos - $hastaEseDia, $definicion, $fecha);

        if (! $cliente->nivel_efectivo->isAbove($resultante)) {
            return null;
        }

        return AvisoDescenso::query()->create([
            'cliente_id' => $cliente->id,
            'fecha_vencimiento' => $fecha,
            'dias_antes' => $dias,
            'puntos_que_vencen' => $vencen,
            'nivel_actual' => $cliente->nivel_efectivo,
            'nivel_resultante' => $resultante,
        ]);
    }

    /**
     * The level the customer would have on that day with that balance.
     *
     * @param  array<array-key, mixed>  $definicion
     */
    private function nivelOn(Cliente $cliente, int $saldo, array $definicion, CarbonImmutable $day): Nivel
    {
        $then = clone $cliente;
        $then->saldo_puntos = max(0, $saldo);

        return $this->calculator->fuentes($then, $definicion, $day)->nivel();
    }

    /**
     * Remaining points of the open lots, summed by expiry date.
     *
     * @return array<string, int>
     */
    private function expirations(int $clienteId): array
    {
        $movimientos = MovimientoPuntos::query()->where('cliente_id', $clienteId)->oldest('id')->get();
        $remaining = $this->lotes->remaining($movimientos);
        $byDate = [];

        foreach ($movimientos as $lote) {
            $left = $remaining[$lote->id] ?? 0;

            if ($left > 0 && $lote->vigencia_hasta !== null) {
                $day = $lote->vigencia_hasta->toDateString();
                $byDate[$day] = ($byDate[$day] ?? 0) + $left;
            }
        }

        return $byDate;
    }

    private function alreadyRegistered(int $clienteId, CarbonImmutable $fecha, int $dias): bool
    {
        return AvisoDescenso::query()
            ->where('cliente_id', $clienteId)
            ->where('fecha_vencimiento', $fecha->toDateString())
            ->where('dias_antes', $dias)
            ->toBase()
            ->exists();
    }

    /**
     * Notice days of the `niveles` rule.
     *
     * @param  array<array-key, mixed>  $definicion
     * @return list<int>
     */
    public static function diasAntes(array $definicion): array
    {
        $dias = is_array($definicion['avisos_dias_antes'] ?? null) ? $definicion['avisos_dias_antes'] : [];

        return array_values(array_filter($dias, static fn (mixed $dia): bool => is_int($dia) && $dia > 0));
    }
}
