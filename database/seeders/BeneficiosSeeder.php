<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Beneficios\SaveBeneficio;
use App\Models\Comercio;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * El catálogo inicial de beneficios confirmado por el cliente el 2026-10-10
 * (E5-01). Es el mismo de tests/Fixtures/beneficios-catalogo.json; una prueba
 * verifica que coincidan. Se guarda con `SaveBeneficio`, que valida cada uno.
 */
class BeneficiosSeeder extends Seeder
{
    /**
     * Nombre, NIT del comercio, valores por nivel (porcentajes), condiciones,
     * límite por cliente [ventana, cantidad], cupo global y soporte.
     *
     * @var list<array{
     *     0: string,
     *     1: string,
     *     2: array<string, int>,
     *     3: array<string, mixed>,
     *     4: array{0: string, 1: int}|null,
     *     5: int|null,
     *     6: bool,
     * }>
     */
    private const CATALOGO = [
        [
            'Descuento de cumpleaños en Peluquería Monarca',
            '800500500',
            ['bronce' => 30, 'plata' => 50, 'oro' => 70, 'diamante' => 100],
            ['es_cumpleanos' => true],
            ['anio', 1],
            null,
            false,
        ],
        [
            'Martes de cerveza de línea básica en Cervecería BBC',
            '800200200',
            ['bronce' => 10, 'plata' => 20, 'oro' => 40, 'diamante' => 50],
            ['dia_semana' => ['martes']],
            null,
            null,
            false,
        ],
        [
            'Kilo de carne en El Rancho de Javi',
            '800100100',
            ['bronce' => 10, 'plata' => 20, 'oro' => 40, 'diamante' => 50],
            [],
            ['vida', 1],
            50,
            false,
        ],
        [
            'Descuento en cuota inicial o administración con Constructora Inmobiliaria Laura Rivera',
            '800400400',
            ['bronce' => 1, 'plata' => 2, 'oro' => 3, 'diamante' => 5],
            [],
            ['anio', 1],
            // TODO(cliente): confirmar si hay cupo global y su valor.
            null,
            true,
        ],
        [
            'Descuento de alquiler de fin de semana en Hubble',
            '800600600',
            ['bronce' => 10, 'plata' => 15, 'oro' => 20, 'diamante' => 30],
            ['fin_de_semana' => true],
            ['mes', 1],
            // TODO(cliente): cupo global "por temporada", sin número ni fechas de temporada.
            null,
            false,
        ],
    ];

    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->sole();
        $save = app(SaveBeneficio::class);

        foreach (self::CATALOGO as [$nombre, $nit, $valores, $condiciones, $limite, $cupo, $soporte]) {
            $save($admin, [
                'comercio_id' => Comercio::query()->where('nit', $nit)->sole()->id,
                'nombre' => $nombre,
                'tipo' => 'porcentaje',
                'valores_por_nivel' => $valores,
                'condiciones' => $condiciones,
                'max_por_cliente_ventana' => $limite[0] ?? null,
                'max_por_cliente_cantidad' => $limite[1] ?? null,
                'cupo_global' => $cupo,
                'requiere_soporte' => $soporte,
                'vigente_desde' => CarbonImmutable::now()->toDateString(),
                'activo' => true,
            ]);
        }
    }
}
