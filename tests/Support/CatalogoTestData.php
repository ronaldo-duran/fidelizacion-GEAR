<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Beneficio;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Comercio;
use App\Models\Redencion;
use App\Models\Sede;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Test data for the customer catalog and benefit use (E8-04): the catalog,
 * the Plata customer and the earlier uses of tests/Fixtures/catalogo-pwa-escenarios.json.
 */
final class CatalogoTestData
{
    /** Category names as the seeder writes them (database/seeders/DatabaseSeeder.php). */
    private const CATEGORIAS = [
        'restaurante' => 'Restaurante',
        'heladeria' => 'Heladería',
        'cerveceria' => 'Cervecería',
        'colegio' => 'Colegio',
        'inmobiliaria' => 'Inmobiliaria',
        'peluqueria' => 'Peluquería',
        'alquiler-recreacional' => 'Alquiler recreacional',
    ];

    public Cliente $cliente;

    /** @var array<string, Beneficio> keyed by fixture key */
    public array $beneficios = [];

    /**
     * @return array<string, mixed>
     */
    public static function fixture(): array
    {
        $path = dirname(__DIR__) . '/Fixtures/catalogo-pwa-escenarios.json';

        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    public static function hoy(): CarbonImmutable
    {
        return CarbonImmutable::parse(self::fixture()['today']);
    }

    /**
     * The niveles rule, the customer and the catalog with its earlier uses.
     * Call it with the clock already on the fixture day.
     */
    public static function seed(bool $conFechaNacimiento = true): self
    {
        $data = app(self::class);
        $fixture = self::fixture();
        NivelesTestData::nivelesRegla();

        $datos = $fixture['customer'];
        $data->cliente = NivelesTestData::cliente([
            'saldo_puntos' => $datos['saldo_puntos'],
            'nivel_por_puntos' => $datos['nivel_por_puntos'],
        ]);
        $data->cliente->forceFill([
            'nombre_completo' => $datos['nombre_completo'],
            'fecha_nacimiento' => $conFechaNacimiento ? $datos['fecha_nacimiento'] : null,
        ])->save();
        $data->cliente->refresh();

        foreach ($fixture['benefits'] as $beneficio) {
            $data->beneficios[$beneficio['key']] = self::beneficio($beneficio);
        }

        foreach ($fixture['benefits'] as $beneficio) {
            self::usos($data, $beneficio);
        }

        return $data;
    }

    public function id(string $key): int
    {
        return $this->beneficios[$key]->id;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private static function beneficio(array $datos): Beneficio
    {
        $categoria = Categoria::query()->firstOrCreate(
            ['slug' => $datos['categoria_slug']],
            ['nombre' => self::CATEGORIAS[$datos['categoria_slug']]],
        );
        $comercio = Comercio::query()->where('razon_social', $datos['comercio'])->first()
            ?? Comercio::factory()->create(['razon_social' => $datos['comercio'], 'categoria_id' => $categoria->id]);

        return Beneficio::query()->forceCreate([
            'comercio_id' => $comercio->id,
            'nombre' => $datos['nombre'],
            'condiciones_texto' => $datos['condiciones_texto'] ?? null,
            'tipo' => $datos['tipo'],
            'valores_por_nivel' => $datos['valores_por_nivel'],
            'condiciones' => $datos['condiciones'] ?? [],
            'max_por_cliente_ventana' => $datos['max_por_cliente']['ventana'] ?? null,
            'max_por_cliente_cantidad' => $datos['max_por_cliente']['cantidad'] ?? null,
            'cupo_global' => $datos['cupo_global'] ?? null,
            'requiere_soporte' => $datos['requiere_soporte'] ?? false,
            'vigente_desde' => '2026-01-01',
            'activo' => true,
        ]);
    }

    /**
     * The earlier redemptions of the fixture: one by the customer (with its
     * branch) and one by another customer that takes the last global slot.
     *
     * @param  array<string, mixed>  $datos
     */
    private static function usos(self $data, array $datos): void
    {
        $beneficio = $data->beneficios[$datos['key']];

        if (isset($datos['used_by_customer'])) {
            $sede = Sede::factory()->create([
                'comercio_id' => $beneficio->comercio_id,
                'nombre' => $datos['used_by_customer']['sede'],
            ]);
            self::redencion($beneficio, $data->cliente, $sede, $datos['used_by_customer']['at']);
        }

        if ($datos['used_by_another_customer'] ?? false) {
            $otro = NivelesTestData::cliente(['nivel_por_puntos' => 'plata']);
            self::redencion($beneficio, $otro, null, self::fixture()['today']);
        }
    }

    private static function redencion(Beneficio $beneficio, Cliente $cliente, ?Sede $sede, string $at): void
    {
        Redencion::query()->forceCreate([
            'cliente_id' => $cliente->id,
            'beneficio_id' => $beneficio->id,
            'comercio_id' => $beneficio->comercio_id,
            'sede_id' => $sede?->id,
            'valor_otorgado_cop' => 10000,
            'validado_por' => User::factory()->cajero($beneficio->comercio_id, $sede?->id)->create()->id,
            'referencia' => (string) Str::uuid(),
            'created_at' => CarbonImmutable::parse($at),
        ]);
    }
}
