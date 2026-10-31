<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Beneficios\SaveBeneficio;
use App\Models\Beneficio;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Comercio;
use App\Models\Redencion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\BeneficiosSeeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Lee tests/Fixtures/beneficios-catalogo.json y beneficios-escenarios.json
 * (E5-01) y arma lo que piden: comercios por NIT, el catálogo sembrado, los
 * beneficios extra, clientes y redenciones previas.
 */
final class BeneficiosTestData
{
    /**
     * @return array<string, mixed>
     */
    public static function catalogo(): array
    {
        return self::read('beneficios-catalogo.json');
    }

    /**
     * @return array<string, mixed>
     */
    public static function escenarios(): array
    {
        return self::read('beneficios-escenarios.json');
    }

    /**
     * @return array<string, mixed>
     */
    public static function fixtureCase(string $criterion): array
    {
        foreach (self::escenarios()['cases'] as $case) {
            if ($case['_e5_01_criterion'] === $criterion) {
                return $case;
            }
        }

        throw new RuntimeException("Fixture case not found: {$criterion}");
    }

    /**
     * Los comercios del catálogo, el administrador del seeder, la regla
     * `niveles`, el catálogo sembrado el 2026-09-01 y los beneficios extra del
     * fixture de escenarios.
     */
    public static function seed(): User
    {
        $categoria = Categoria::query()->firstOrCreate(['slug' => 'varios'], ['nombre' => 'Varios']);
        $nits = array_merge(
            array_column(self::catalogo()['beneficios'], 'comercio_nit'),
            array_column(self::escenarios()['extra_benefits'], 'comercio_nit'),
        );

        foreach (array_unique($nits) as $nit) {
            Comercio::factory()->create(['nit' => $nit, 'categoria_id' => $categoria->id]);
        }

        $admin = User::factory()->administradorDelGrupo()->create(['email' => 'admin@example.com']);
        NivelesTestData::nivelesRegla();

        ComprasTestData::at(CarbonImmutable::parse('2026-09-01'), static function () use ($admin): void {
            app(BeneficiosSeeder::class)->run();

            foreach (self::escenarios()['extra_benefits'] as $extra) {
                app(SaveBeneficio::class)($admin, [
                    'comercio_id' => self::comercioId($extra['comercio_nit']),
                    'nombre' => $extra['nombre'],
                    'tipo' => $extra['tipo'],
                    'valores_por_nivel' => $extra['valores_por_nivel'],
                    'condiciones' => $extra['condiciones'],
                    'max_por_cliente_ventana' => $extra['max_por_cliente']['ventana'] ?? null,
                    'max_por_cliente_cantidad' => $extra['max_por_cliente']['cantidad'] ?? null,
                    'cupo_global' => $extra['cupo_global'] ?? null,
                    'vigente_desde' => '2026-09-01',
                    'vigente_hasta' => $extra['vigente_hasta'] ?? null,
                    'activo' => true,
                ]);
            }
        });

        return $admin;
    }

    public static function comercioId(string $nit): int
    {
        return Comercio::query()->where('nit', $nit)->sole()->id;
    }

    public static function beneficio(string $nombre): Beneficio
    {
        return Beneficio::query()->where('nombre', $nombre)->sole();
    }

    /**
     * @param  array<string, mixed>  $datos  nivel_efectivo, saldo_puntos y fecha_nacimiento
     */
    public static function cliente(array $datos): Cliente
    {
        $cliente = Cliente::query()->create([
            'tipo_documento' => 'CC',
            'documento' => (string) fake()->unique()->numerify('10########'),
            'fecha_nacimiento' => $datos['fecha_nacimiento'] ?? null,
        ]);
        $cliente->forceFill([
            'nivel_efectivo' => $datos['nivel_efectivo'],
            'saldo_puntos' => $datos['saldo_puntos'],
        ])->save();

        return $cliente->refresh();
    }

    public static function redencion(Beneficio $beneficio, Cliente $cliente, string $fecha, User $validador): Redencion
    {
        return Redencion::query()->forceCreate([
            'cliente_id' => $cliente->id,
            'beneficio_id' => $beneficio->id,
            'comercio_id' => $beneficio->comercio_id,
            'valor_otorgado_cop' => 10000,
            'validado_por' => $validador->id,
            'referencia' => (string) Str::uuid(),
            'created_at' => CarbonImmutable::parse($fecha . ' 12:00:00'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function read(string $file): array
    {
        $fixture = json_decode((string) file_get_contents(__DIR__ . "/../Fixtures/{$file}"), true);

        return is_array($fixture) ? $fixture : [];
    }
}
