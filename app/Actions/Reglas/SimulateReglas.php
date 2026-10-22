<?php

declare(strict_types=1);

namespace App\Actions\Reglas;

use App\Enums\DefinicionSource;
use App\Enums\TipoRegla;
use App\Models\Regla;
use App\Models\User;
use App\Reglas\CompraData;
use App\Reglas\PuntosCalculator;
use App\Reglas\PuntosDesglose;
use App\Reglas\ReglaAplicada;
use App\Reglas\ReglaDefinitionValidator;
use App\Reglas\SimulacionLinea;
use App\Reglas\SimulacionResultado;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Compares, purchase by purchase, the points under the rules in force today
 * against the points with an unsaved draft replacing its rule. Read only:
 * it runs inside a transaction that is always rolled back, as a second
 * barrier behind the code itself.
 *
 * The sample purchases count as one customer's, in the given order, so day
 * and month caps add up the earlier purchases of the same simulation.
 */
class SimulateReglas
{
    private readonly ReglaDefinitionValidator $validator;

    private readonly PuntosCalculator $calculator;

    public function __construct(ReglaDefinitionValidator $validator, PuntosCalculator $calculator)
    {
        $this->validator = $validator;
        $this->calculator = $calculator;
    }

    /**
     * @param  array<string, mixed>  $definicion  the draft
     * @param  list<CompraData>  $compras  sample purchases
     *
     * @throws ValidationException
     */
    public function __invoke(
        User $user,
        string $codigo,
        TipoRegla $tipo,
        array $definicion,
        array $compras,
        DefinicionSource $source = DefinicionSource::Form,
    ): SimulacionResultado {
        Gate::forUser($user)->authorize('viewAny', Regla::class);

        if ($source === DefinicionSource::JsonEditor) {
            Gate::forUser($user)->authorize('editJson', Regla::class);
        }

        if ($tipo === TipoRegla::Niveles) {
            throw ValidationException::withMessages([
                'definicion' => 'El simulador solo prueba reglas de acumulación y bonificación.',
            ]);
        }

        DB::beginTransaction();

        try {
            $this->validator->validate($tipo, $definicion);

            $draft = ReglaAplicada::draft(mb_strtolower(trim($codigo)), $tipo, $definicion);
            $vigentes = $this->reglasVigentes();
            $conBorrador = $this->replace($vigentes, $draft);

            return $this->compare($compras, $vigentes, $conBorrador);
        } finally {
            DB::rollBack();
        }
    }

    /**
     * @return list<ReglaAplicada>
     */
    private function reglasVigentes(): array
    {
        $query = Regla::inForceAt(CarbonImmutable::now());
        $query->getQuery()
            ->whereIn('tipo', [TipoRegla::Acumulacion->value, TipoRegla::Bonificacion->value])
            ->orderBy('codigo');

        return array_values($query->get()
            ->map(static fn (Regla $regla): ReglaAplicada => ReglaAplicada::fromModel($regla))
            ->all());
    }

    /**
     * The rule set with the draft in place of the rule of the same code.
     *
     * @param  list<ReglaAplicada>  $vigentes
     * @return list<ReglaAplicada>
     *
     * @throws ValidationException
     */
    private function replace(array $vigentes, ReglaAplicada $draft): array
    {
        $reglas = [];

        foreach ($vigentes as $regla) {
            if ($regla->codigo === $draft->codigo && $regla->tipo !== $draft->tipo) {
                throw ValidationException::withMessages([
                    'definicion' => "La regla «{$draft->codigo}» es de tipo {$regla->tipo->getLabel()}.",
                ]);
            }

            if ($regla->codigo === $draft->codigo) {
                continue;
            }

            if ($regla->tipo === TipoRegla::Acumulacion && $draft->tipo === TipoRegla::Acumulacion) {
                throw ValidationException::withMessages([
                    'definicion' => "Ya rige la regla de acumulación «{$regla->codigo}»: simula sobre ella.",
                ]);
            }

            $reglas[] = $regla;
        }

        $reglas[] = $draft;

        return $reglas;
    }

    /**
     * @param  list<CompraData>  $compras
     * @param  list<ReglaAplicada>  $vigentes
     * @param  list<ReglaAplicada>  $conBorrador
     */
    private function compare(array $compras, array $vigentes, array $conBorrador): SimulacionResultado
    {
        $puntosVigentes = $this->run($compras, $vigentes);
        $puntosBorrador = $this->run($compras, $conBorrador);
        $lineas = [];

        foreach ($compras as $index => $compra) {
            $lineas[] = new SimulacionLinea($compra, $puntosVigentes[$index], $puntosBorrador[$index]);
        }

        return new SimulacionResultado($lineas);
    }

    /**
     * @param  list<CompraData>  $compras
     * @param  list<ReglaAplicada>  $reglas
     * @return list<PuntosDesglose>
     */
    private function run(array $compras, array $reglas): array
    {
        $porDia = [];
        $porMes = [];
        $desgloses = [];

        foreach ($compras as $compra) {
            $desglose = $this->calculator->calculate(
                $compra,
                $reglas,
                $porDia[$compra->dayKey()] ?? 0,
                $porMes[$compra->monthKey()] ?? 0,
            );

            $porDia[$compra->dayKey()] = ($porDia[$compra->dayKey()] ?? 0) + $desglose->puntos;
            $porMes[$compra->monthKey()] = ($porMes[$compra->monthKey()] ?? 0) + $desglose->puntos;
            $desgloses[] = $desglose;
        }

        return $desgloses;
    }
}
