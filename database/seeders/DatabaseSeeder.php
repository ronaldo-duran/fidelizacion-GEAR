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

        // Usuario para entrar al panel. Los roles y permisos llegan con E2-01.
        User::factory()->create([
            'name' => 'Administrador',
            'email' => 'admin@example.com',
        ]);
    }
}
