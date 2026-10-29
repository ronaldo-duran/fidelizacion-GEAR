<?php

declare(strict_types=1);

namespace App\Beneficios;

use App\Enums\Nivel;
use App\Models\Beneficio;
use App\Models\Cliente;
use App\Models\Redencion;
use App\Niveles\NivelEfectivo;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;

/**
 * Los beneficios vigentes y activos con su estado para un cliente en un día
 * de America/Bogota (E5-01, regla 9). El orden de los estados es: bloqueado,
 * sin cupos, usado, no disponible hoy y disponible.
 *
 * Sin N+1: una consulta de beneficios, una de redenciones agrupadas para los
 * cupos, una de las redenciones del cliente y una de la regla `niveles`, sin
 * importar cuántos beneficios haya. Las fechas las pone la aplicación (regla
 * dura 3.13).
 */
class BeneficiosParaCliente
{
    private readonly NivelEfectivo $niveles;

    public function __construct(NivelEfectivo $niveles)
    {
        $this->niveles = $niveles;
    }

    /**
     * @return list<BeneficioParaCliente>
     */
    public function para(Cliente $cliente, ?CarbonImmutable $dia = null): array
    {
        $dia = ($dia ?? CarbonImmutable::now())->startOfDay();
        $beneficios = $this->vigentes($dia);
        $usosGlobales = $this->usosGlobales($beneficios);
        $usosDelCliente = $this->usosDelCliente($cliente, $beneficios);
        $umbrales = $this->umbrales();
        $estados = [];

        foreach ($beneficios as $beneficio) {
            // Un nivel sin valor por encima del mínimo no recibe el beneficio: no se lista.
            $noLoRecibe = $beneficio->valorPara($cliente->nivel_efectivo) === null
                && ! $beneficio->nivelMinimo()->isAbove($cliente->nivel_efectivo);

            if ($noLoRecibe) {
                continue;
            }

            $estados[] = $this->estado(
                $beneficio,
                $cliente,
                $dia,
                $usosGlobales[$beneficio->id] ?? 0,
                $usosDelCliente[$beneficio->id] ?? [],
                $umbrales,
            );
        }

        return $estados;
    }

    /**
     * @param  list<CarbonImmutable>  $usos  redenciones del cliente en ese beneficio
     * @param  array<string, int>  $umbrales
     */
    private function estado(
        Beneficio $beneficio,
        Cliente $cliente,
        CarbonImmutable $dia,
        int $usosGlobales,
        array $usos,
        array $umbrales,
    ): BeneficioParaCliente {
        $nivel = $cliente->nivel_efectivo;
        $minimo = $beneficio->nivelMinimo();
        $valor = $beneficio->valorPara($nivel);

        if ($valor === null) {
            return BeneficioParaCliente::bloqueado(
                $beneficio,
                $minimo,
                self::faltan($umbrales, $minimo, $cliente),
            );
        }

        if ($beneficio->cupo_global !== null && $usosGlobales >= $beneficio->cupo_global) {
            return BeneficioParaCliente::sinCupos($beneficio);
        }

        $ventana = $beneficio->max_por_cliente_ventana;

        $agotado = $ventana !== null
            && $this->usosEnVentana($usos, $ventana->inicio($dia)) >= $beneficio->max_por_cliente_cantidad;

        if ($ventana !== null && $agotado) {
            return BeneficioParaCliente::usado($beneficio, $ventana->vuelve($dia));
        }

        $condiciones = $beneficio->condiciones;
        $nacimiento = $cliente->fecha_nacimiento;

        if (! CondicionesBeneficio::cumple($condiciones, $nacimiento, $dia)) {
            return CondicionesBeneficio::faltaFechaDeNacimiento($condiciones, $nacimiento)
                ? BeneficioParaCliente::noDisponibleHoy($beneficio, null, BeneficioParaCliente::SIN_FECHA_NACIMIENTO)
                : BeneficioParaCliente::noDisponibleHoy(
                    $beneficio,
                    CondicionesBeneficio::proximaFecha($condiciones, $nacimiento, $dia),
                    null,
                );
        }

        return BeneficioParaCliente::disponible(
            $beneficio,
            $valor,
            $this->siguiente($beneficio, $cliente, $valor, $umbrales),
        );
    }

    /**
     * El nivel inmediatamente superior, si da más ("Con Oro, 40 %").
     *
     * @param  array<string, int>  $umbrales
     * @return array{nivel: Nivel, valor: int, puntos_faltantes: int}|null
     */
    private function siguiente(Beneficio $beneficio, Cliente $cliente, int $valor, array $umbrales): ?array
    {
        $nivel = Nivel::cases()[$cliente->nivel_efectivo->rank() + 1] ?? null;
        $valorSiguiente = $nivel === null ? null : $beneficio->valorPara($nivel);

        if ($nivel === null || $valorSiguiente === null || $valorSiguiente <= $valor) {
            return null;
        }

        return [
            'nivel' => $nivel,
            'valor' => $valorSiguiente,
            'puntos_faltantes' => self::faltan($umbrales, $nivel, $cliente),
        ];
    }

    /**
     * @param  list<CarbonImmutable>  $usos
     */
    private function usosEnVentana(array $usos, ?CarbonImmutable $inicio): int
    {
        return count(array_filter(
            $usos,
            static fn (CarbonImmutable $uso): bool => $inicio === null || $uso->gte($inicio),
        ));
    }

    /**
     * @param  array<string, int>  $umbrales
     */
    private static function faltan(array $umbrales, Nivel $nivel, Cliente $cliente): int
    {
        return max(0, ($umbrales[$nivel->value] ?? 0) - $cliente->saldo_puntos);
    }

    /**
     * @return Collection<int, Beneficio>
     */
    private function vigentes(CarbonImmutable $dia): Collection
    {
        $query = Beneficio::query()->with('comercio');
        $query->getQuery()
            ->where('activo', true)
            ->where('vigente_desde', '<=', $dia->toDateString())
            ->where(static function (Builder $vigencia) use ($dia): void {
                $vigencia->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', $dia->toDateString());
            })
            ->orderBy('id');

        return $query->get();
    }

    /**
     * Redenciones de todos los clientes, por beneficio, solo para los que tienen cupo.
     *
     * @param  Collection<int, Beneficio>  $beneficios
     * @return array<int, int>
     */
    private function usosGlobales(Collection $beneficios): array
    {
        $ids = $beneficios->filter(static fn (Beneficio $beneficio): bool => $beneficio->cupo_global !== null)
            ->modelKeys();

        if ($ids === []) {
            return [];
        }

        $usos = [];
        $filas = Redencion::query()->toBase()
            ->whereIn('beneficio_id', $ids)
            ->groupBy('beneficio_id')
            ->selectRaw('beneficio_id, count(*) as total')
            ->get();

        foreach ($filas as $fila) {
            if (is_numeric($fila->beneficio_id) && is_numeric($fila->total)) {
                $usos[(int) $fila->beneficio_id] = (int) $fila->total;
            }
        }

        return $usos;
    }

    /**
     * Las fechas de las redenciones del cliente, por beneficio, solo para los que tienen límite.
     *
     * @param  Collection<int, Beneficio>  $beneficios
     * @return array<int, list<CarbonImmutable>>
     */
    private function usosDelCliente(Cliente $cliente, Collection $beneficios): array
    {
        $ids = $beneficios
            ->filter(static fn (Beneficio $beneficio): bool => $beneficio->max_por_cliente_ventana !== null)
            ->modelKeys();

        if ($ids === []) {
            return [];
        }

        $usos = [];
        $query = Redencion::query()->where('cliente_id', $cliente->id);
        $query->getQuery()->whereIn('beneficio_id', $ids);

        foreach ($query->get(['beneficio_id', 'created_at']) as $redencion) {
            $usos[$redencion->beneficio_id][] = $redencion->created_at;
        }

        return $usos;
    }

    /**
     * El umbral de puntos de cada nivel, de la regla `niveles` vigente.
     *
     * @return array<string, int>
     */
    private function umbrales(): array
    {
        $definicion = $this->niveles->definicionNiveles();
        $umbrales = [];

        foreach (is_array($definicion['umbrales'] ?? null) ? $definicion['umbrales'] : [] as $umbral) {
            if (is_array($umbral) && is_string($umbral['nivel'] ?? null) && is_int($umbral['umbral_puntos'] ?? null)) {
                $umbrales[$umbral['nivel']] = $umbral['umbral_puntos'];
            }
        }

        return $umbrales;
    }
}
