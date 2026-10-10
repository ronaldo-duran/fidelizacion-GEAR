<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RolUsuario;
use App\Observers\AuditoriaObserver;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property string $name
 * @property string $email
 * @property RolUsuario $rol
 * @property int|null $comercio_id
 * @property int|null $sede_id
 * @property bool $activo
 */
#[Fillable(['name', 'email', 'password', 'rol', 'comercio_id', 'sede_id', 'activo'])]
#[Hidden(['password', 'remember_token'])]
#[ObservedBy(AuditoriaObserver::class)]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;

    /**
     * El panel `/admin` solo admite a los roles con acceso y únicamente si la
     * cuenta está activa. Un cajero o un usuario desactivado quedan fuera.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin'
            && $this->activo
            && $this->rol->puedeAccederAlPanel();
    }

    public function esAdministradorDelGrupo(): bool
    {
        return $this->rol === RolUsuario::AdministradorGrupo;
    }

    /**
     * El correo se guarda en minúsculas y sin espacios: SQL Server compara
     * sin distinguir mayúsculas, así que normalizarlo evita duplicados que
     * solo difieren en mayúsculas (regla 3.13).
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: static fn (string $value): string => mb_strtolower(trim($value)),
        );
    }

    /**
     * El administrador del grupo ve todo; los demás solo su propio comercio.
     */
    public function alcanzaComercio(int $comercioId): bool
    {
        return $this->esAdministradorDelGrupo() || $this->comercio_id === $comercioId;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'rol' => RolUsuario::class,
            'activo' => 'boolean',
        ];
    }
}
