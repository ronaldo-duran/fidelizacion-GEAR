<?php

declare(strict_types=1);

namespace App\Panel;

use App\Models\Cliente;
use App\Models\MovimientoPuntos;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Qué ve cada rol en el panel (E10-06, regla 10). El administrador del grupo
 * ve todo. El administrador de comercio ve los clientes con al menos una
 * compra en su comercio (en cualquier estado), y solo los movimientos y las
 * transacciones de su comercio. Lo usan los recursos y las políticas.
 */
final class AlcancePanel
{
    private const EN_ALCANCE = 'compra_en_comercio';

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function clientes(Builder $query, User $user): Builder
    {
        if ($user->esAdministradorDelGrupo()) {
            return $query;
        }

        $comercioId = $user->comercio_id ?? 0;
        $deSuComercio = static function (Builder $compras) use ($comercioId): void {
            $compras->getQuery()->where('comercio_id', $comercioId);
        };

        // `compra_en_comercio` deja el alcance ya resuelto en cada fila: la
        // política no vuelve a consultar por cada cliente de la lista.
        return $query->whereHas('transacciones', $deSuComercio)
            ->withExists(['transacciones as ' . self::EN_ALCANCE => $deSuComercio]);
    }

    /**
     * Sirve para los movimientos y las transacciones: las dos tablas tienen
     * `comercio_id`. Un ajuste manual no tiene comercio, así que el
     * administrador de comercio no lo ve.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function delComercio(Builder $query, User $user): Builder
    {
        if (! $user->esAdministradorDelGrupo()) {
            $query->getQuery()->where($query->getModel()->getTable() . '.comercio_id', $user->comercio_id ?? 0);
        }

        return $query;
    }

    public static function veCliente(User $user, Cliente $cliente): bool
    {
        if ($user->esAdministradorDelGrupo()) {
            return true;
        }

        if (array_key_exists(self::EN_ALCANCE, $cliente->getAttributes())) {
            return (bool) $cliente->getAttribute(self::EN_ALCANCE);
        }

        return self::clientes(Cliente::query()->whereKey($cliente->id), $user)->toBase()->exists();
    }

    public static function veMovimiento(User $user, MovimientoPuntos $movimiento): bool
    {
        return $user->esAdministradorDelGrupo() || $movimiento->comercio_id === $user->comercio_id;
    }

    /**
     * Solo los dos roles de administrador entran a estas pantallas.
     */
    public static function esAdministrador(User $user): bool
    {
        return $user->activo && ($user->esAdministradorDelGrupo() || $user->esAdministradorDeComercio());
    }
}
