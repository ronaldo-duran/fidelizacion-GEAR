<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Roles de los usuarios del sistema (no del cliente final, que vive en otro
 * guard, ver E8-02). El alcance por comercio lo determinan las columnas
 * `comercio_id` y `sede_id` del usuario, no el rol.
 *
 * `Integracion` es el usuario detrás de una credencial de la API pública
 * (E9-01): no entra al panel ni a la caja y se gestiona en su propio recurso.
 */
enum RolUsuario: string implements HasLabel
{
    case AdministradorGrupo = 'administrador_grupo';
    case AdministradorComercio = 'administrador_comercio';
    case Cajero = 'cajero';
    case Integracion = 'integracion';

    /**
     * Roles que pueden entrar al panel administrativo `/admin`.
     *
     * @return array<int, self>
     */
    public static function conAccesoAlPanel(): array
    {
        return [self::AdministradorGrupo, self::AdministradorComercio];
    }

    /**
     * Los roles que se asignan en el recurso de usuarios: el de integración
     * nace con su credencial.
     *
     * @return array<string, string> valor => etiqueta
     */
    public static function asignables(): array
    {
        $roles = array_filter(self::cases(), static fn (self $rol): bool => $rol !== self::Integracion);

        return array_column(array_map(
            static fn (self $rol): array => [$rol->value, $rol->getLabel()],
            $roles,
        ), 1, 0);
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
            self::Integracion => 'Integración (API)',
        };
    }
}
