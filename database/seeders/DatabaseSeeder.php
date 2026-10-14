<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Reglas\CreateRegla;
use App\Enums\TipoRegla;
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
        $this->seedReglas();
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

        User::factory()->administradorDelGrupo()->jsonReglaEditor()->create([
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

    /**
     * Base engine rules with the values the client confirmed on 2026-10-10:
     * 1 point per $1.000 in every category, points
     * last 12 months, thresholds 500/1.500/3.000, no protection and notices
     * 30, 7 and 1 day before. Same values as tests/Fixtures/reglas-validas.json.
     */
    private function seedReglas(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->sole();
        $createRegla = app(CreateRegla::class);

        $tasas = Categoria::query()->get()
            ->map(static fn (Categoria $categoria): array => [
                'categoria_slug' => $categoria->slug,
                'puntos_por_unidad' => 1,
            ])
            ->all();

        $createRegla($admin, 'acumulacion-base', TipoRegla::Acumulacion, [
            'moneda_por_punto' => 1000,
            'tasas_por_categoria' => $tasas,
            'topes' => ['puntos_por_transaccion' => null, 'puntos_por_dia' => null, 'puntos_por_mes' => null],
            'vigencia_puntos_meses' => 12,
        ]);

        $createRegla($admin, 'niveles', TipoRegla::Niveles, [
            'umbrales' => [
                ['nivel' => 'bronce', 'umbral_puntos' => 0],
                ['nivel' => 'plata', 'umbral_puntos' => 500],
                ['nivel' => 'oro', 'umbral_puntos' => 1500],
                ['nivel' => 'diamante', 'umbral_puntos' => 3000],
            ],
            'proteccion_meses' => 0,
            'avisos_dias_antes' => [30, 7, 1],
        ]);
    }
}
