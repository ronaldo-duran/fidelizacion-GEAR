<?php

declare(strict_types=1);

namespace App\Beneficios;

/**
 * Lo que pinta el catálogo del cliente (E8-04, regla 3): las fichas de
 * categoría, la elegida y las tres secciones. Una sección vacía no se
 * muestra.
 */
final class CatalogoDelCliente
{
    /** @var array<string, string> slug => nombre */
    public readonly array $fichas;

    public readonly ?string $categoria;

    /** @var list<FilaCatalogo> */
    public readonly array $disponibles;

    /** @var list<FilaCatalogo> */
    public readonly array $porAhoraNo;

    /** @var list<FilaCatalogo> */
    public readonly array $bloqueados;

    /**
     * @param  array<string, string>  $fichas
     * @param  list<FilaCatalogo>  $disponibles
     * @param  list<FilaCatalogo>  $porAhoraNo
     * @param  list<FilaCatalogo>  $bloqueados
     */
    public function __construct(
        array $fichas,
        ?string $categoria,
        array $disponibles,
        array $porAhoraNo,
        array $bloqueados,
    ) {
        $this->fichas = $fichas;
        $this->categoria = $categoria;
        $this->disponibles = $disponibles;
        $this->porAhoraNo = $porAhoraNo;
        $this->bloqueados = $bloqueados;
    }

    public function vacio(): bool
    {
        return $this->disponibles === [] && $this->porAhoraNo === [] && $this->bloqueados === [];
    }
}
