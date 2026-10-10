<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'rol' => RolUsuario::Cajero,
            'comercio_id' => null,
            'sede_id' => null,
            'activo' => true,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state([
            'email_verified_at' => null,
        ]);
    }

    public function administradorDelGrupo(): static
    {
        return $this->state(['rol' => RolUsuario::AdministradorGrupo]);
    }

    public function administradorDeComercio(int $comercioId): static
    {
        return $this->state([
            'rol' => RolUsuario::AdministradorComercio,
            'comercio_id' => $comercioId,
        ]);
    }

    public function cajero(int $comercioId, ?int $sedeId = null): static
    {
        return $this->state([
            'rol' => RolUsuario::Cajero,
            'comercio_id' => $comercioId,
            'sede_id' => $sedeId,
        ]);
    }

    public function inactivo(): static
    {
        return $this->state(['activo' => false]);
    }
}
