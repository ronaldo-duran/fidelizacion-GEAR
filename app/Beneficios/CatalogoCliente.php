<?php

declare(strict_types=1);

namespace App\Beneficios;

use App\Enums\EstadoBeneficio;
use App\Enums\TipoBeneficio;
use App\Enums\VentanaBeneficio;
use App\Models\Beneficio;
use App\Models\Cliente;
use App\Support\FormatoColombiano;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * El catálogo de beneficios del cliente (E8-04, reglas 3 a 6 y 11). Solo lee
 * el estado que calcula `BeneficiosParaCliente` (E5-01) y le pone sus textos:
 * ninguna vista ni componente vuelve a calcular nada.
 */
class CatalogoCliente
{
    private readonly BeneficiosParaCliente $beneficios;

    public function __construct(BeneficiosParaCliente $beneficios)
    {
        $this->beneficios = $beneficios;
    }

    /**
     * Las tres secciones, filtradas por la categoría elegida, y las fichas de
     * las categorías que tienen al menos un beneficio para el cliente.
     */
    public function para(Cliente $cliente, ?string $categoria = null, ?CarbonImmutable $dia = null): CatalogoDelCliente
    {
        $dia = ($dia ?? CarbonImmutable::now())->startOfDay();
        $todos = $this->beneficios->para($cliente, $dia);
        self::cargarCategorias($todos);
        $fichas = self::fichas($todos);
        $categoria = $categoria !== null && array_key_exists($categoria, $fichas) ? $categoria : null;
        $filtrados = $categoria === null
            ? $todos
            : array_values(array_filter(
                $todos,
                static fn (BeneficioParaCliente $item): bool => self::categoriaDe($item) === $categoria,
            ));

        $secciones = ['disponibles' => [], 'por_ahora_no' => [], 'bloqueados' => []];

        foreach (BeneficiosParaCliente::ordenar($filtrados) as $item) {
            $seccion = match ($item->estado) {
                EstadoBeneficio::Disponible => 'disponibles',
                EstadoBeneficio::Bloqueado => 'bloqueados',
                default => 'por_ahora_no',
            };
            $secciones[$seccion][] = new FilaCatalogo($item, self::meta($item, $dia));
        }

        // Los bloqueados: primero el nivel más cercano; el orden por comercio se conserva.
        usort($secciones['bloqueados'], static fn (FilaCatalogo $uno, FilaCatalogo $otro): int => ($uno->item
            ->nivelRequerido?->rank() ?? 0) <=> ($otro->item->nivelRequerido?->rank() ?? 0));

        return new CatalogoDelCliente(
            $fichas,
            $categoria,
            $secciones['disponibles'],
            $secciones['por_ahora_no'],
            $secciones['bloqueados'],
        );
    }

    /**
     * Los disponibles de hoy sin el beneficio que se está viendo, hasta el
     * máximo de `config/catalogo.php` (regla 11).
     *
     * @return list<FilaCatalogo>
     */
    public function otrosParaHoy(Cliente $cliente, int $sinBeneficioId, ?CarbonImmutable $dia = null): array
    {
        $dia = ($dia ?? CarbonImmutable::now())->startOfDay();
        $max = config('catalogo.otros_para_hoy_max');
        $otros = array_values(array_filter(
            $this->beneficios->disponibles($cliente, $dia),
            static fn (BeneficioParaCliente $item): bool => $item->beneficio->id !== $sinBeneficioId,
        ));

        return array_map(
            static fn (BeneficioParaCliente $item): FilaCatalogo => new FilaCatalogo($item, self::meta($item, $dia)),
            array_slice($otros, 0, is_int($max) ? $max : count($otros)),
        );
    }

    /**
     * El texto que va bajo el nombre del beneficio, según su estado.
     */
    public static function meta(BeneficioParaCliente $item, CarbonImmutable $dia): string
    {
        $beneficio = $item->beneficio;

        return match ($item->estado) {
            EstadoBeneficio::Disponible => self::valor($beneficio->tipo, (int) $item->valor) . ' de descuento'
                . ($item->siguiente === null
                    ? ''
                    : " · Con {$item->siguiente['nivel']->getLabel()}, " . self::valor(
                        $beneficio->tipo,
                        $item->siguiente['valor'],
                    )),
            EstadoBeneficio::Bloqueado => 'Te faltan ' . FormatoColombiano::puntos((int) $item->puntosFaltantes),
            EstadoBeneficio::SinCupos => 'Se acabaron los cupos',
            EstadoBeneficio::Usado => self::usado($beneficio, $item->vuelve, $dia),
            EstadoBeneficio::NoDisponibleHoy => $item->motivo === BeneficioParaCliente::SIN_FECHA_NACIMIENTO
                ? 'Necesitamos tu fecha de nacimiento'
                : 'Disponible el ' . self::fecha($item->proximaFecha, $dia),
        };
    }

    /**
     * "Ya lo usaste este mes", "este año" o, si el límite es de por vida, "Ya lo usaste".
     */
    public static function yaLoUsaste(Beneficio $beneficio): string
    {
        return match ($beneficio->max_por_cliente_ventana) {
            VentanaBeneficio::Mes => 'Ya lo usaste este mes',
            VentanaBeneficio::Anio => 'Ya lo usaste este año',
            default => 'Ya lo usaste',
        };
    }

    /**
     * "15 de marzo", con el año solo si no es el de hoy.
     */
    public static function fecha(?CarbonImmutable $fecha, CarbonImmutable $dia): string
    {
        if ($fecha === null) {
            return '';
        }

        return $fecha->year === $dia->year ? FormatoColombiano::fecha($fecha) : FormatoColombiano::fechaConAnio($fecha);
    }

    private static function usado(Beneficio $beneficio, ?CarbonImmutable $vuelve, CarbonImmutable $dia): string
    {
        return self::yaLoUsaste($beneficio) . ($vuelve === null ? '' : ' · Vuelve el ' . self::fecha($vuelve, $dia));
    }

    private static function valor(TipoBeneficio $tipo, int $valor): string
    {
        return $tipo === TipoBeneficio::Porcentaje ? "{$valor} %" : FormatoColombiano::pesos($valor);
    }

    /**
     * @param  list<BeneficioParaCliente>  $items
     * @return array<string, string> slug => nombre, por nombre
     */
    private static function fichas(array $items): array
    {
        $fichas = [];

        foreach ($items as $item) {
            $categoria = $item->beneficio->comercio->categoria ?? null;

            if ($categoria !== null) {
                $fichas[$categoria->slug] = $categoria->nombre;
            }
        }

        uasort($fichas, static fn (string $uno, string $otro): int => strcmp(
            mb_strtolower($uno),
            mb_strtolower($otro),
        ));

        return $fichas;
    }

    /**
     * Las categorías de los comercios, en una sola consulta: solo el catálogo
     * las necesita, así la consulta de E5-01 no crece para la caja ni el inicio.
     *
     * @param  list<BeneficioParaCliente>  $items
     */
    private static function cargarCategorias(array $items): void
    {
        $comercios = Collection::make();

        foreach ($items as $item) {
            if ($item->beneficio->comercio !== null) {
                $comercios->push($item->beneficio->comercio);
            }
        }

        $comercios->loadMissing('categoria');
    }

    private static function categoriaDe(BeneficioParaCliente $item): ?string
    {
        return $item->beneficio->comercio->categoria->slug ?? null;
    }
}
