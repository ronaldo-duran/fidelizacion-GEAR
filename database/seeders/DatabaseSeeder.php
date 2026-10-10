<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Comercio;
use App\Models\Sede;
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

        $this->sembrarCategorias();
        $this->sembrarComerciosDeMuestra();
        $this->sembrarUsuarios();
    }

    /**
     * Las cinco categorías base. Los slugs coinciden con los fixtures de
     * reglas (tests/Fixtures/reglas-validas.json).
     */
    private function sembrarCategorias(): void
    {
        $categorias = [
            'restaurante' => 'Restaurante',
            'heladeria' => 'Heladería',
            'cerveceria' => 'Cervecería',
            'colegio' => 'Colegio',
            'inmobiliaria' => 'Inmobiliaria',
        ];

        foreach ($categorias as $slug => $nombre) {
            Categoria::query()->firstOrCreate(['slug' => $slug], ['nombre' => $nombre]);
        }
    }

    /**
     * Muestra de 3 o 4 comercios de ejemplo del diseño. Los 15 reales se
     * cargan desde el panel cuando el cliente entregue los datos (issue #1).
     */
    private function sembrarComerciosDeMuestra(): void
    {
        $muestra = [
            ['El Rancho de Javi', '800100100', 'restaurante'],
            ['Cervecería BBC', '800200200', 'cerveceria'],
            ['Colegio Semillitas del Futuro', '800300300', 'colegio'],
            ['Inmobiliaria Laura Rivera', '800400400', 'inmobiliaria'],
        ];

        foreach ($muestra as [$razonSocial, $nit, $slug]) {
            $categoria = Categoria::query()->where('slug', $slug)->sole();

            $comercio = Comercio::query()->firstOrCreate(
                ['nit' => $nit],
                ['razon_social' => $razonSocial, 'categoria_id' => $categoria->id],
            );

            Sede::query()->firstOrCreate(
                ['comercio_id' => $comercio->id, 'nombre' => 'Sede principal'],
                ['direccion' => 'Por confirmar'],
            );
        }
    }

    /**
     * Un usuario de cada rol para probar el acceso al panel (E2-01), colgados
     * del primer comercio de muestra.
     */
    private function sembrarUsuarios(): void
    {
        $comercio = Comercio::query()->where('nit', '800100100')->sole();
        $sede = Sede::query()
            ->where('comercio_id', $comercio->id)
            ->where('nombre', 'Sede principal')
            ->sole();

        User::factory()->administradorDelGrupo()->create([
            'name' => 'Administrador del grupo',
            'email' => 'admin@example.com',
        ]);

        User::factory()->administradorDeComercio($comercio->id)->create([
            'name' => 'Administrador de comercio',
            'email' => 'comercio@clubaponterivera.co',
        ]);

        User::factory()->cajero($comercio->id, $sede->id)->create([
            'name' => 'Cajero',
            'email' => 'cajero@clubaponterivera.co',
        ]);
    }
}
