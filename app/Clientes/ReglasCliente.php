<?php

declare(strict_types=1);

namespace App\Clientes;

use App\Enums\TipoDocumento;
use Illuminate\Validation\Rule;

/**
 * Las reglas de validación de los datos del cliente (E8-02), compartidas por
 * el registro y la recuperación del PIN. Los patrones, los dígitos del PIN y
 * la edad mínima salen de `config/registro.php`. El documento y el celular se
 * validan ya normalizados.
 */
final class ReglasCliente
{
    /**
     * @return list<mixed>
     */
    public static function tipoDocumento(): array
    {
        return ['required', Rule::enum(TipoDocumento::class)];
    }

    /**
     * @return list<string>
     */
    public static function documento(?TipoDocumento $tipo): array
    {
        $pattern = $tipo === null ? null : config("registro.documento_patterns.{$tipo->value}");

        return ['required', 'string', 'regex:/' . (is_string($pattern) ? $pattern : '^$') . '/'];
    }

    /**
     * @return list<string>
     */
    public static function celular(): array
    {
        $pattern = config('registro.celular_pattern');

        return ['required', 'string', 'regex:/' . (is_string($pattern) ? $pattern : '^$') . '/'];
    }

    /**
     * Los dígitos de la configuración, sin PIN triviales: todos iguales (1111)
     * ni consecutivos hacia arriba o hacia abajo (1234, 4321).
     *
     * @return list<mixed>
     */
    public static function pin(): array
    {
        $digits = self::pinDigits();

        return ['required', 'string', "digits:{$digits}", Rule::notIn(self::pinesTriviales($digits))];
    }

    public static function pinDigits(): int
    {
        $digits = config('registro.pin_digits');

        return is_int($digits) ? $digits : 0;
    }

    /**
     * @return array<string, string>
     */
    public static function messages(?TipoDocumento $tipo): array
    {
        $digits = self::pinDigits();

        return [
            'tipoDocumento.required' => 'Elige el tipo de documento.',
            'tipoDocumento.enum' => 'Elige el tipo de documento.',
            'documento.required' => 'Escribe el número del documento.',
            'documento.regex' => $tipo === TipoDocumento::CC
                ? 'La cédula lleva solo números, de 6 a 10.'
                : 'Revisa el número del documento.',
            'celular.required' => 'Escribe tu celular.',
            'celular.regex' => 'Escribe un celular de 10 dígitos que empiece por 3.',
            'pin.required' => 'Elige un PIN.',
            'pin.digits' => "El PIN lleva {$digits} números.",
            'pin.not_in' => 'Elige un PIN más difícil: sin números repetidos ni seguidos.',
            'pinConfirmation.same' => 'Los dos PIN no coinciden.',
        ];
    }

    /**
     * @return list<string>
     */
    private static function pinesTriviales(int $digits): array
    {
        $triviales = [];

        for ($inicio = 0; $inicio <= 9; $inicio++) {
            $triviales[] = str_repeat((string) $inicio, $digits);
        }

        for ($inicio = 0; $inicio + $digits <= 10; $inicio++) {
            $arriba = implode('', range($inicio, $inicio + $digits - 1));
            $triviales[] = $arriba;
            $triviales[] = strrev($arriba);
        }

        return $triviales;
    }
}
