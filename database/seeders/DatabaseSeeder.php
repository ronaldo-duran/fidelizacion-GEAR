<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Promociones\SetPromocionLimites;
use App\Actions\Reglas\CreateRegla;
use App\Enums\TipoRegla;
use App\Models\Categoria;
use App\Models\Comercio;
use App\Models\Sede;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

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
        $this->call(BeneficiosSeeder::class);

        if (config('muestra.sembrar') !== true) {
            $this->seedReglas();

            return;
        }

        // Datos de muestra: las reglas base rigen desde el mes anterior, como si el
        // programa llevara ese tiempo andando, para que la historia de compras sume puntos.
        // Al terminar vuelve el reloj de antes (el de una prueba con `travelTo`, o ninguno).
        $relojPrevio = CarbonImmutable::hasTestNow() ? CarbonImmutable::now() : null;
        DatosDeMuestraSeeder::reloj(DatosDeMuestraSeeder::inicio());

        try {
            $this->seedReglas();
        } finally {
            DatosDeMuestraSeeder::reloj($relojPrevio);
        }

        $this->call(DatosDeMuestraSeeder::class);
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

        $clave = self::claveSembrada();

        User::factory()->administradorDelGrupo()->jsonReglaEditor()->create([
            'name' => 'Administrador del grupo',
            'email' => 'admin@example.com',
            'password' => $clave,
        ]);
        User::factory()->administradorDeComercio($comercio->id)->apruebaMisiones()->create([
            'name' => 'Administrador de comercio',
            'email' => 'comercio@clubaponterivera.co',
            'password' => $clave,
        ]);
        User::factory()->cajero($comercio->id, $sede->id)->create([
            'name' => 'Cajero',
            'email' => 'cajero@clubaponterivera.co',
            'password' => $clave,
        ]);
    }

    /**
     * La contraseña de los usuarios sembrados (auditoría 2026-11, hallazgo 2).
     * `password` solo en local y en las pruebas; en un servidor de staging o
     * de demostración hay que definir SEMILLA_CLAVE, o el seeder no corre.
     */
    public static function claveSembrada(): string
    {
        $clave = config('muestra.clave_usuarios');

        if (is_string($clave) && mb_strlen($clave) >= 10) {
            return $clave;
        }

        if (app()->environment(['local', 'testing']) && ($clave === null || $clave === '')) {
            return 'password';
        }

        throw new RuntimeException(
            'Define SEMILLA_CLAVE (mínimo 10 caracteres) antes de sembrar usuarios fuera de local.',
        );
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

        $this->seedMision($admin);
    }

    /**
     * Una misión de El Rancho de Javi con los valores que dio el cliente (10
     * puntos por seguir en Instagram, tests/Fixtures/promociones-escenarios.json).
     * La usa la prueba E2E de la PWA; la aprueba el administrador de comercio.
     */
    private function seedMision(User $admin): void
    {
        $comercio = Comercio::query()->where('nit', '800100100')->sole();

        app(SetPromocionLimites::class)($admin, $comercio->id, true, 50);
        app(CreateRegla::class)($admin, 'instagram-rancho', TipoRegla::Mision, [
            'comercio_id' => $comercio->id,
            'titulo' => 'Síguenos en Instagram',
            'descripcion' => 'Envía el pantallazo de que sigues a El Rancho de Javi en Instagram.',
            'puntos' => 10,
            'max_por_cliente' => 1,
            'requiere_soporte' => true,
        ]);
    }
}
