<?php

declare(strict_types=1);

namespace App\Reglas;

use App\Models\ComercioPromocionLimite;
use Illuminate\Validation\ValidationException;

/**
 * Una misión no puede salirse de lo que el grupo le habilita a su comercio
 * (E3-06, regla 6): misiones permitidas y un máximo de puntos por misión.
 * Vale para quien la guarde, también el administrador del grupo.
 */
class LimitesMision
{
    /**
     * Va dentro de la transacción del guardado: bloquea los límites del
     * comercio para que un cambio simultáneo del grupo no se cuele.
     *
     * @param  array<array-key, mixed>  $definicion  ya validada contra el esquema
     *
     * @throws ValidationException
     */
    public function assertPermite(array $definicion): void
    {
        $comercioId = $definicion['comercio_id'] ?? null;
        $puntos = $definicion['puntos'] ?? null;

        $query = ComercioPromocionLimite::query()->where('comercio_id', is_int($comercioId) ? $comercioId : 0);
        $query->getQuery()->lockForUpdate();
        $limite = $query->first();

        if ($limite === null || ! $limite->permite_misiones) {
            throw ValidationException::withMessages([
                'definicion' => 'Este comercio no tiene misiones habilitadas por el grupo.',
            ]);
        }

        if (is_int($puntos) && $puntos > $limite->puntos_max_por_mision) {
            throw ValidationException::withMessages([
                'definicion' => "Una misión de este comercio puede dar hasta {$limite->puntos_max_por_mision} puntos.",
            ]);
        }
    }
}
