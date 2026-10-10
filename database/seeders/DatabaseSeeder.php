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
        $this->sembrarComercios();
        $this->sembrarUsuarios();
    }

    /**
     * Categorías del grupo. Los slugs coinciden con los fixtures
     * (tests/Fixtures/reglas-validas.json y beneficios-catalogo.json).
     */
    private function sembrarCategorias(): void
    {
        $categorias = [
            'restaurante' => 'Restaurante',
            'heladeria' => 'Heladería',
            'cerveceria' => 'Cervecería',
            'colegio' => 'Colegio',
            'inmobiliaria' => 'Inmobiliaria',
            'peluqueria' => 'Peluquería',
            'alquiler-recreacional' => 'Alquiler recreacional',
        ];

        foreach ($categorias as $slug => $nombre) {
            Categoria::query()->firstOrCreate(['slug' => $slug], ['nombre' => $nombre]);
        }
    }

    /**
     * Los 15 comercios del grupo. Los NIT son inventados y provisionales: el
     * catálogo real lo administra el cliente por base de datos más adelante
     * (issue #1). Se conservan los NIT 800100100–800400400 porque de ellos
     * cuelgan los usuarios de prueba y los fixtures del diseño.
     *
     * @var list<array{0: string, 1: string, 2: string}>
     */
    private const COMERCIOS = [
        ['El Rancho de Javi', '800100100', 'restaurante'],
        ['Cervecería BBC', '800200200', 'cerveceria'],
        ['Colegio Semillitas del Futuro', '800300300', 'colegio'],
        ['Constructora Inmobiliaria Laura Rivera', '800400400', 'inmobiliaria'],
        ['Peluquería Monarca', '800500500', 'peluqueria'],
        ['Hubble Alquileres Recreacionales', '800600600', 'alquiler-recreacional'],
        ['Heladería Los Alpes', '800700700', 'heladeria'],
        ['Heladería Polo Norte', '800800800', 'heladeria'],
        ['Restaurante La Parrilla del Río', '800900900', 'restaurante'],
        ['Restaurante Sabor Caribe', '801001001', 'restaurante'],
        ['Cervecería El Barril', '801101101', 'cerveceria'],
        ['Barbería Don Carlos', '801201201', 'peluqueria'],
        ['Inmobiliaria Rivera Propiedades', '801301301', 'inmobiliaria'],
        ['Colegio Bilingüe Los Andes', '801401401', 'colegio'],
        ['Hubble Cabañas del Lago', '801501501', 'alquiler-recreacional'],
    ];

    private function sembrarComercios(): void
    {
        foreach (self::COMERCIOS as [$razonSocial, $nit, $slug]) {
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
     * del primer comercio sembrado.
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
