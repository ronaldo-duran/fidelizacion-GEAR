<?php

declare(strict_types=1);

namespace App\Panel;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * La búsqueda de clientes del panel (E10-06, regla 2). El número de documento
 * se normaliza como se guarda (CLAUDE.md 3.10) y el tipo va en su propio
 * filtro. El nombre se compara en minúsculas con `LOWER(...) LIKE ?`, que es
 * SQL estándar (regla dura 3.13, sin `ILIKE`). El celular, solo si el término
 * son dígitos.
 */
final class BusquedaClientes
{
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function aplicar(Builder $query, string $termino): Builder
    {
        $termino = trim($termino);

        if ($termino === '') {
            return $query;
        }

        $documento = Cliente::normalizeDocumento($termino);
        $nombre = mb_strtolower($termino);
        $celular = preg_match('/^[\d\s+()-]+$/', $termino) === 1 ? (string) preg_replace('/\D/', '', $termino) : '';

        $query->getQuery()->where(static function (QueryBuilder $busqueda) use ($documento, $nombre, $celular): void {
            $busqueda->where('documento', 'like', "%{$documento}%")
                ->orWhereRaw('LOWER(nombre_completo) LIKE ?', ["%{$nombre}%"]);

            if ($celular !== '') {
                $busqueda->orWhere('celular', 'like', "%{$celular}%");
            }
        });

        return $query;
    }
}
