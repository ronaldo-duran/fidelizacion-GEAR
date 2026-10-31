<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Compras\RegisterCompra;
use App\Actions\Niveles\ReevaluateNivelEfectivo;
use App\Compras\NuevaCompra;
use App\Enums\CanalCompra;
use App\Enums\Nivel;
use App\Enums\TipoDocumento;
use App\Enums\TipoEventoCalificado;
use App\Enums\TipoRegla;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Comercio;
use App\Models\EventoCalificado;
use App\Models\Regla;
use App\Models\Sede;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Lee tests/Fixtures/panel-clientes-escenarios.json (E10-06) y arma los dos
 * comercios por NIT, la regla de acumulación base, la de niveles y los
 * clientes con sus compras registradas por el libro.
 */
final class PanelClientesTestData
{
    private const CATEGORIAS = ['800100100' => 'restaurante', '800200200' => 'cerveceria'];

    /**
     * @return array<string, mixed>
     */
    public static function fixture(): array
    {
        $path = __DIR__ . '/../Fixtures/panel-clientes-escenarios.json';
        $fixture = json_decode((string) file_get_contents($path), true);

        return is_array($fixture) ? $fixture : [];
    }

    /**
     * Una sección del fixture como dataset de Pest, con un nombre por caso.
     *
     * @param  callable(array<string, mixed>): string  $nombre
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function dataset(string $seccion, callable $nombre): array
    {
        $casos = [];

        foreach (self::fixture()[$seccion] as $i => $caso) {
            $casos[($i + 1) . '. ' . $nombre($caso)] = [$caso];
        }

        return $casos;
    }

    /**
     * Los comercios, las reglas y los clientes del fixture.
     *
     * @return array<string, Cliente> los clientes por su clave
     */
    public static function seed(User $admin): array
    {
        foreach (self::CATEGORIAS as $nit => $slug) {
            $categoria = Categoria::query()->firstOrCreate(['slug' => $slug], ['nombre' => ucfirst($slug)]);
            $comercio = Comercio::factory()->create(['nit' => $nit, 'categoria_id' => $categoria->id]);
            Sede::factory()->create(['comercio_id' => $comercio->id, 'activa' => true]);
        }

        Regla::query()->create([
            'codigo' => 'acumulacion-base',
            'tipo' => TipoRegla::Acumulacion,
            'version' => 1,
            'vigente_desde' => CarbonImmutable::now()->subYear(),
            'activa' => true,
            'definicion' => [
                'moneda_por_punto' => 1000,
                'tasas_por_categoria' => array_map(
                    static fn (string $slug): array => ['categoria_slug' => $slug, 'puntos_por_unidad' => 1],
                    array_values(self::CATEGORIAS),
                ),
                'vigencia_puntos_meses' => 12,
            ],
        ]);
        NivelesTestData::nivelesRegla();

        $clientes = [];

        foreach (self::fixture()['customers'] as $datos) {
            $clientes[$datos['key']] = self::cliente($datos, $admin);
        }

        return $clientes;
    }

    public static function comercio(string $nit): Comercio
    {
        return Comercio::query()->where('nit', $nit)->sole();
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private static function cliente(array $datos, User $admin): Cliente
    {
        $cliente = Cliente::query()->create([
            'tipo_documento' => TipoDocumento::from($datos['tipo_documento']),
            'documento' => $datos['documento'],
        ]);
        $cliente->forceFill([
            'nombre_completo' => $datos['nombre_completo'],
            'nivel_por_membresia' => isset($datos['nivel_por_membresia'])
                ? Nivel::from($datos['nivel_por_membresia'])
                : null,
            'membresia_vigente_hasta' => $datos['membresia_vigente_hasta'] ?? null,
        ])->save();

        if (isset($datos['nivel_por_evento'])) {
            self::evento($cliente, $datos['nivel_por_evento'], $datos['evento_vigente_hasta'], $admin);
        }

        foreach ($datos['purchases'] as $compra) {
            $comercio = self::comercio($compra['comercio_nit']);
            $sede = Sede::query()->where('comercio_id', $comercio->id)->sole();

            ComprasTestData::at(
                CarbonImmutable::now()->subDays($compra['dias_atras']),
                static fn (): mixed => app(RegisterCompra::class)($admin, new NuevaCompra(
                    $comercio->id,
                    $sede->id,
                    $cliente->tipo_documento,
                    $cliente->documento,
                    $compra['monto'],
                    'ref-' . $datos['key'] . '-' . $compra['comercio_nit'],
                    CanalCompra::Cajero,
                )),
            );
        }

        // Compró con la membresía vigente; la evaluación nocturna de hoy la deja en su nivel actual.
        app(ReevaluateNivelEfectivo::class)($cliente->id);

        return $cliente->refresh();
    }

    /**
     * El nivel por evento sale de `eventos_calificados` con las reglas de
     * config/niveles.php: una matrícula da Oro y un inmueble, Diamante.
     */
    private static function evento(Cliente $cliente, string $nivel, string $hasta, User $admin): void
    {
        EventoCalificado::query()->create([
            'cliente_id' => $cliente->id,
            'comercio_id' => self::comercio('800100100')->id,
            'tipo' => $nivel === 'oro' ? TipoEventoCalificado::MatriculaColegio : TipoEventoCalificado::CompraInmueble,
            'vigente_desde' => CarbonImmutable::today()->subMonths(6),
            'vigente_hasta' => CarbonImmutable::parse($hasta),
            'soporte_path' => 'soportes/prueba.pdf',
            'registrado_por' => $admin->id,
        ]);
    }
}
