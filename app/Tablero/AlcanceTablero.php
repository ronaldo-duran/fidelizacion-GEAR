<?php

declare(strict_types=1);

namespace App\Tablero;

use App\Models\User;

/**
 * Qué cifras ve quien abre el tablero: todo el grupo, o solo un comercio
 * (el administrador de comercio nunca ve cifras de otro, E2-01).
 */
final readonly class AlcanceTablero
{
    public ?int $comercioId;

    private function __construct(?int $comercioId)
    {
        $this->comercioId = $comercioId;
    }

    public static function grupo(): self
    {
        return new self(null);
    }

    public static function comercio(int $comercioId): self
    {
        return new self($comercioId);
    }

    /**
     * El administrador del grupo ve el grupo; cualquier otro, su comercio
     * (o ninguno, si no tiene).
     */
    public static function para(User $user): self
    {
        return $user->esAdministradorDelGrupo() ? self::grupo() : self::comercio($user->comercio_id ?? 0);
    }

    public function esGrupo(): bool
    {
        return $this->comercioId === null;
    }
}
