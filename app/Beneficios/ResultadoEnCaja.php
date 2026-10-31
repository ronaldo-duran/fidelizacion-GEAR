<?php

declare(strict_types=1);

namespace App\Beneficios;

use App\Enums\ResultadoBeneficio;
use App\Enums\TipoBeneficio;
use App\Models\Beneficio;
use App\Models\Cliente;
use App\Models\Redencion;
use App\Support\FormatoColombiano;
use Carbon\CarbonImmutable;

/**
 * Guarda un `ChequeoBeneficio` como datos simples para la pantalla de la caja
 * (Livewire solo guarda ids y textos entre peticiones) y los vuelve a cargar
 * para pintar VÁLIDO, YA USADO o NO VÁLIDO (E5-02).
 */
final class ResultadoEnCaja
{
    /**
     * @return array{
     *     pantalla: string,
     *     resultado: string,
     *     beneficio_id: int|null,
     *     cliente_id: int|null,
     *     credencial_id: int|null,
     *     uso_id: int|null,
     *     valor: int|null,
     *     vuelve: string|null,
     *     proxima_fecha: string|null
     * }
     */
    public static function guardar(ChequeoBeneficio $chequeo): array
    {
        return [
            'pantalla' => $chequeo->pantalla(),
            'resultado' => $chequeo->resultado->value,
            'beneficio_id' => $chequeo->beneficio?->id,
            'cliente_id' => $chequeo->cliente?->id,
            'credencial_id' => $chequeo->credencialId,
            'uso_id' => $chequeo->ultimoUso?->id,
            'valor' => $chequeo->estado?->valor,
            'vuelve' => $chequeo->vuelve?->toDateString(),
            'proxima_fecha' => $chequeo->proximaFecha?->toDateString(),
        ];
    }

    /**
     * Lo que pinta la vista del resultado guardado.
     *
     * @param  array<string, mixed>  $guardado
     * @return array<string, mixed>
     */
    public static function vista(array $guardado): array
    {
        $beneficio = Beneficio::query()->find(self::entero($guardado, 'beneficio_id'));
        $uso = Redencion::query()->with(['comercio', 'sede'])->find(self::entero($guardado, 'uso_id'));
        $resultado = is_string($guardado['resultado'] ?? null)
            ? ResultadoBeneficio::tryFrom($guardado['resultado'])
            : null;
        $valor = self::entero($guardado, 'valor');
        $porcentaje = $beneficio?->tipo === TipoBeneficio::Porcentaje;

        return [
            'beneficio' => $beneficio,
            'cliente' => Cliente::query()->find(self::entero($guardado, 'cliente_id')),
            'valorTexto' => $beneficio === null || $valor === null ? null : self::valorTexto($beneficio->tipo, $valor),
            'usoTexto' => $uso === null ? null : self::usoTexto($uso),
            'vuelveTexto' => self::fecha($guardado['vuelve'] ?? null),
            'proximaTexto' => self::fecha($guardado['proxima_fecha'] ?? null),
            'mensaje' => $resultado?->mensaje(),
            'pideMonto' => $porcentaje,
            'pideSoporte' => $beneficio?->requiere_soporte === true,
            'porcentaje' => $porcentaje ? $valor : null,
        ];
    }

    public static function valorTexto(TipoBeneficio $tipo, int $valor): string
    {
        $texto = $tipo === TipoBeneficio::Porcentaje ? "{$valor} %" : FormatoColombiano::pesos($valor);

        return "{$texto} de descuento";
    }

    /**
     * @param  array<string, mixed>  $guardado
     */
    private static function entero(array $guardado, string $clave): ?int
    {
        $valor = $guardado[$clave] ?? null;

        return is_int($valor) ? $valor : null;
    }

    /**
     * "Se usó el 12 de septiembre, 7:42 p. m., en El Rancho de Javi · Sede principal."
     */
    private static function usoTexto(Redencion $uso): string
    {
        $lugar = $uso->comercio->razon_social . ($uso->sede === null ? '' : ' · ' . $uso->sede->nombre);

        return 'Se usó el ' . FormatoColombiano::fecha($uso->created_at) . ', '
            . FormatoColombiano::hora($uso->created_at) . ", en {$lugar}.";
    }

    private static function fecha(mixed $dia): ?string
    {
        return is_string($dia) ? FormatoColombiano::fecha(CarbonImmutable::parse($dia)) : null;
    }
}
