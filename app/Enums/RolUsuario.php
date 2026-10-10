<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Roles de los usuarios del sistema (no del cliente final, que vive en otro
 * guard, ver E8-02). El alcance por comercio lo determinan las columnas
 * `comercio_id` y `sede_id` del usuario, no el rol.
 */
enum RolUsuario: string implements HasLabel
{
    case AdministradorGrupo = 'administrador_grupo';
    case AdministradorComercio = 'administrador_comercio';
    case Cajero = 'cajero';

    /**
     * Roles que pueden entrar al panel administrativo `/admin`.
     *
     * @return array<int, self>
     */
    public static function conAccesoAlPanel(): array
    {
        return [self::AdministradorGrupo, self::AdministradorComercio];
    }

    public function puedeAccederAlPanel(): bool
    {
        return in_array($this, self::conAccesoAlPanel(), true);
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::AdministradorGrupo => 'Administrador del grupo',
            self::AdministradorComercio => 'Administrador de comercio',
            self::Cajero => 'Cajero',
        };
    }
}
