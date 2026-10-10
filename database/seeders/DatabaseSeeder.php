<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Datos base para local, staging y pruebas E2E. Nunca corre en producción.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        User::factory()->administradorDelGrupo()->create([
            'name' => 'Administrador del grupo',
            'email' => 'admin@example.com',
        ]);

        User::factory()->administradorDeComercio(1)->create([
            'name' => 'Administrador de comercio',
            'email' => 'comercio@clubaponterivera.co',
        ]);

        User::factory()->cajero(1, 1)->create([
            'name' => 'Cajero',
            'email' => 'cajero@clubaponterivera.co',
        ]);
    }
}
