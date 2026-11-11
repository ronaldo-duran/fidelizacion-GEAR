<?php

declare(strict_types=1);

namespace App\Puntos;

use App\Enums\TipoMovimiento;
use App\Enums\TipoRegla;
use App\Models\Cliente;
use App\Models\MovimientoPuntos;
use App\Models\Regla;
use App\Support\FormatoColombiano;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * El historial de puntos del cliente (E8-05, reglas 1 a 6). Solo lee el libro
 * (E3-02): la cifra de arriba, el aviso de los puntos que van a dejar de
 * contar y los movimientos de la vigencia agrupados por mes. Hace un número
 * fijo de consultas: la regla, los movimientos y sus comercios y compras.
 */
class HistorialCliente
{
    // TODO(cliente): nombre de trabajo del programa (§9), el mismo del logo.
    public const PROGRAMA = 'Club Aponte Rivera';

    private const MENOS = '−';

    private readonly LoteCalculator $lotes;

    public function __construct(LoteCalculator $lotes)
    {
        $this->lotes = $lotes;
    }

    public function para(Cliente $cliente): HistorialDelCliente
    {
        $hoy = CarbonImmutable::now()->startOfDay();
        $meses = self::vigenciaMeses($hoy);
        $movimientos = self::movimientos($cliente->id);
        $desde = $meses === null ? null : $hoy->subMonths($meses);

        $filas = [];

        foreach ($movimientos->reverse() as $movimiento) {
            $fecha = $movimiento->created_at ?? $hoy;

            if ($desde === null || $fecha->gte($desde)) {
                $filas[self::mesDe($fecha, $hoy)][] = self::fila($movimiento, $fecha);
            }
        }

        return new HistorialDelCliente(
            $meses === null ? 'Tus puntos' : "Puntos de los últimos {$meses} meses",
            $cliente->saldo_puntos,
            $this->avisoVencimiento($movimientos, $hoy),
            $filas,
        );
    }

    /**
     * "180 puntos dejan de contar el 25 de noviembre. Son de compras de
     * noviembre de 2025.": los puntos que quedan en los lotes que vencen
     * primero, si vencen dentro de la ventana (regla 3).
     *
     * @param  Collection<int, MovimientoPuntos>  $movimientos
     * @return array{0: string, 1: string}|null la frase destacada y el detalle
     */
    private function avisoVencimiento(Collection $movimientos, CarbonImmutable $hoy): ?array
    {
        $dias = config('perfil.aviso_vencimiento_dias');
        $limite = $hoy->addDays(is_int($dias) ? $dias : 0);
        $quedan = $this->lotes->remaining($movimientos);
        $proximos = $movimientos
            ->filter(static fn (MovimientoPuntos $lote): bool => ($quedan[$lote->id] ?? 0) > 0
                && $lote->vigencia_hasta !== null
                && $lote->vigencia_hasta->betweenIncluded($hoy, $limite))
            ->sortBy(static fn (MovimientoPuntos $lote): string => $lote->vigencia_hasta?->toDateString() ?? '');
        $primero = $proximos->first();

        if ($primero?->vigencia_hasta === null) {
            return null;
        }

        $vence = $primero->vigencia_hasta;
        $dia = $vence->toDateString();
        $lotes = $proximos->filter(
            static fn (MovimientoPuntos $lote): bool => $lote->vigencia_hasta?->toDateString() === $dia,
        );
        $puntos = $lotes->sum(static fn (MovimientoPuntos $lote): int => $quedan[$lote->id] ?? 0);
        $origen = $primero->created_at ?? $hoy;
        $compras = $lotes->every(
            static fn (MovimientoPuntos $lote): bool => $lote->tipo === TipoMovimiento::Acumulacion,
        );

        return [
            number_format($puntos, 0, ',', '.') . ' puntos dejan de contar el '
                . FormatoColombiano::fecha($vence) . '.',
            ($compras ? 'Son de compras de ' : 'Los sumaste en ')
                . FormatoColombiano::mes($origen) . ' de ' . $origen->format('Y') . '.',
        ];
    }

    private static function fila(MovimientoPuntos $movimiento, CarbonImmutable $fecha): FilaHistorial
    {
        $comercio = $movimiento->comercio->razon_social ?? null;
        [$titulo, $tipo] = match ($movimiento->tipo) {
            TipoMovimiento::Acumulacion => [
                $comercio ?? '',
                'Compra ' . FormatoColombiano::pesos($movimiento->transaccion->monto ?? 0),
            ],
            TipoMovimiento::Reversa => [$comercio ?? '', 'Devolución'],
            TipoMovimiento::Caducidad => ['Puntos vencidos', 'Dejaron de contar'],
            TipoMovimiento::AjusteManual => ['Ajuste del Club', 'Ajuste'],
            TipoMovimiento::Promocion => [$comercio ?? self::PROGRAMA, 'Bono'],
        };
        $suma = $movimiento->puntos > 0;
        $puntos = number_format(abs($movimiento->puntos), 0, ',', '.');

        return new FilaHistorial(
            $titulo,
            FormatoColombiano::fechaCorta($fecha) . ' · ' . $tipo,
            ($suma ? '+' : self::MENOS) . $puntos,
            $suma,
        );
    }

    /**
     * "Octubre" en el año en curso; "Noviembre de 2025" en otro.
     */
    private static function mesDe(CarbonImmutable $fecha, CarbonImmutable $hoy): string
    {
        $mes = mb_convert_case(FormatoColombiano::mes($fecha), MB_CASE_TITLE);

        return $fecha->year === $hoy->year ? $mes : "{$mes} de {$fecha->year}";
    }

    /**
     * Todos los movimientos, en el orden en que se crearon: los lotes de antes
     * de la vigencia hacen falta para saber cuánto queda en cada uno.
     *
     * @return Collection<int, MovimientoPuntos>
     */
    private static function movimientos(int $clienteId): Collection
    {
        $query = MovimientoPuntos::query()
            ->where('cliente_id', $clienteId)
            ->with(['comercio:id,razon_social', 'transaccion:id,monto']);
        $query->getQuery()->orderBy('created_at')->orderBy('id');

        return $query->get();
    }

    private static function vigenciaMeses(CarbonImmutable $hoy): ?int
    {
        $regla = Regla::inForceAt($hoy->endOfDay())->where('tipo', TipoRegla::Acumulacion->value)->first();
        $meses = $regla?->definicion['vigencia_puntos_meses'] ?? null;

        return is_int($meses) ? $meses : null;
    }
}
