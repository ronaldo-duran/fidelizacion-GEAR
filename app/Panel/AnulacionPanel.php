<?php

declare(strict_types=1);

namespace App\Panel;

use App\Enums\EstadoTransaccion;
use App\Enums\TipoMovimiento;
use App\Models\MovimientoPuntos;
use App\Models\Transaccion;
use App\Models\User;
use App\Puntos\PlazoReversa;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * Cuándo el panel ofrece "Anular" en una compra (E10-06, regla 9): no está
 * anulada, la política de E4-01 lo permite y, si dio puntos, sigue en el plazo
 * de reversa. Solo decide si la acción aparece; el rechazo real sigue en
 * `AnnulCompra`. Una compra pendiente no tiene puntos ni plazo (decisión 6).
 */
final class AnulacionPanel
{
    private const ACREDITADA_AT = 'acreditada_en_libro_at';

    /**
     * Agrega a cada compra cuándo se acreditaron sus puntos, en la misma
     * consulta de la lista: así decidir la acción no consulta por fila.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function conAcreditacion(Builder $query): Builder
    {
        return $query->withMin([
            'movimientos as ' . self::ACREDITADA_AT => static function (Builder $movimientos): void {
                $movimientos->getQuery()->where('tipo', TipoMovimiento::Acumulacion->value);
            },
        ], 'created_at');
    }

    public static function puedeAnular(User $user, Transaccion $transaccion): bool
    {
        $anulada = $transaccion->estado === EstadoTransaccion::Anulada;

        if ($anulada || ! Gate::forUser($user)->allows('annul', $transaccion)) {
            return false;
        }

        $acreditada = self::acreditadaAt($transaccion);

        return $acreditada === null || ! PlazoReversa::vencido($acreditada);
    }

    private static function acreditadaAt(Transaccion $transaccion): ?CarbonImmutable
    {
        $valor = array_key_exists(self::ACREDITADA_AT, $transaccion->getAttributes())
            ? $transaccion->getAttribute(self::ACREDITADA_AT)
            : MovimientoPuntos::query()->toBase()
                ->where('transaccion_id', $transaccion->id)
                ->where('tipo', TipoMovimiento::Acumulacion->value)
                ->min('created_at');

        return is_string($valor) ? CarbonImmutable::parse($valor) : null;
    }
}
