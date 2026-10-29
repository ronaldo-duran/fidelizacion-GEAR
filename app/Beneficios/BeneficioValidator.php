<?php

declare(strict_types=1);

namespace App\Beneficios;

use App\Enums\Nivel;
use App\Enums\TipoBeneficio;
use App\Enums\VentanaBeneficio;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Valida un beneficio antes de guardarlo (E5-01):
 *
 * 1. Los campos, con las reglas de Laravel.
 * 2. Las condiciones contra su esquema cerrado (`CondicionesSchema`, regla
 *    dura 3.8): solo claves de la lista blanca, nada de expresiones libres.
 * 3. Los valores por nivel: enteros, porcentaje de 0 a 100 o pesos enteros
 *    (regla dura 3.9). Un nivel sin valor no lo recibe.
 *
 * Devuelve los datos normalizados, listos para guardar.
 */
class BeneficioValidator
{
    private readonly CondicionesSchema $schema;

    public function __construct(CondicionesSchema $schema)
    {
        $this->schema = $schema;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(array $datos): array
    {
        $datos['valores_por_nivel'] = self::valores($datos['valores_por_nivel'] ?? []);
        $datos['condiciones'] = self::condiciones($datos['condiciones'] ?? []);

        $validados = self::normalizar(Validator::make($datos, self::rules(), self::messages())->validate());
        $errores = [
            ...$this->valoresErrors($validados),
            ...$this->limiteErrors($validados),
        ];
        $condiciones = $this->schema->errors(is_array($validados['condiciones']) ? $validados['condiciones'] : []);

        if ($condiciones !== []) {
            $errores['condiciones'] = $condiciones;
        }

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }

        return $validados;
    }

    /**
     * @return array<string, list<mixed>>
     */
    private static function rules(): array
    {
        return [
            'comercio_id' => ['required', 'integer', Rule::exists('comercios', 'id')],
            'nombre' => ['required', 'string', 'max:160'],
            'condiciones_texto' => ['nullable', 'string', 'max:500'],
            'tipo' => ['required', Rule::enum(TipoBeneficio::class)],
            'valores_por_nivel' => ['required', 'array', 'min:1'],
            'valores_por_nivel.*' => ['integer'],
            'condiciones' => ['present', 'array'],
            'max_por_cliente_ventana' => ['nullable', Rule::enum(VentanaBeneficio::class)],
            'max_por_cliente_cantidad' => ['nullable', 'required_with:max_por_cliente_ventana', 'integer', 'min:1'],
            'cupo_global' => ['nullable', 'integer', 'min:1'],
            'requiere_soporte' => ['boolean'],
            'vigente_desde' => ['required', 'date'],
            'vigente_hasta' => ['nullable', 'date', 'after_or_equal:vigente_desde'],
            'activo' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function messages(): array
    {
        return [
            'comercio_id.required' => 'Elige el comercio.',
            'comercio_id.exists' => 'Elige el comercio.',
            'nombre.required' => 'Escribe el nombre del beneficio.',
            'tipo.required' => 'Elige el tipo de beneficio.',
            'valores_por_nivel.required' => 'Dale un valor al menos a un nivel.',
            'valores_por_nivel.min' => 'Dale un valor al menos a un nivel.',
            'valores_por_nivel.*.integer' => 'Los valores son números enteros.',
            'max_por_cliente_cantidad.required_with' => 'Escribe cuántas veces puede usarlo cada cliente.',
            'max_por_cliente_cantidad.min' => 'Cada cliente lo usa al menos una vez.',
            'cupo_global.min' => 'El cupo global es de al menos una redención.',
            'vigente_desde.required' => 'Escribe desde cuándo rige.',
            'vigente_hasta.after_or_equal' => 'La vigencia termina después de empezar.',
        ];
    }

    /**
     * Una condición apagada o vacía no es condición: se quita antes de validar.
     *
     * @return array<array-key, mixed>
     */
    private static function condiciones(mixed $condiciones): array
    {
        return array_filter(
            is_array($condiciones) ? $condiciones : [],
            static fn (mixed $valor): bool => $valor !== false && $valor !== null && $valor !== [],
        );
    }

    /**
     * Los valores llegan del formulario como texto; un nivel vacío no lo recibe.
     *
     * @return array<string, mixed>
     */
    private static function valores(mixed $valores): array
    {
        $limpios = [];

        foreach (is_array($valores) ? $valores : [] as $nivel => $valor) {
            if ($valor === null || $valor === '') {
                continue;
            }

            $limpios[(string) $nivel] = is_string($valor) && ctype_digit($valor) ? (int) $valor : $valor;
        }

        return $limpios;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, list<string>>
     */
    private function valoresErrors(array $datos): array
    {
        $valores = is_array($datos['valores_por_nivel'] ?? null) ? $datos['valores_por_nivel'] : [];
        $tipo = TipoBeneficio::tryFrom(is_string($datos['tipo'] ?? null) ? $datos['tipo'] : '');
        $errores = [];

        foreach ($valores as $nivel => $valor) {
            $error = self::errorDeValor((string) $nivel, $valor, $tipo);

            if ($error !== null) {
                $errores[] = $error;
            }
        }

        return $errores === [] ? [] : ['valores_por_nivel' => array_values(array_unique($errores))];
    }

    private static function errorDeValor(string $nivel, mixed $valor, ?TipoBeneficio $tipo): ?string
    {
        if (Nivel::tryFrom($nivel) === null) {
            return "«{$nivel}» no es un nivel del programa.";
        }

        if (! is_int($valor)) {
            return 'Los valores son números enteros.';
        }

        return match (true) {
            $tipo === TipoBeneficio::Porcentaje && ($valor < 0 || $valor > 100) => 'El porcentaje va de 0 a 100.',
            $tipo === TipoBeneficio::MontoFijo && $valor < 1 => 'El monto fijo es de al menos $1.',
            default => null,
        };
    }

    /**
     * Los datos ya validados, con sus tipos: enteros, booleanos y fechas como texto.
     *
     * @param  array<array-key, mixed>  $validados
     * @return array<string, mixed>
     */
    private static function normalizar(array $validados): array
    {
        $normalizados = [];

        foreach ($validados as $campo => $valor) {
            $normalizados[(string) $campo] = match ($campo) {
                'comercio_id', 'max_por_cliente_cantidad', 'cupo_global' => self::entero($valor),
                'requiere_soporte', 'activo' => (bool) $valor,
                'condiciones', 'valores_por_nivel' => is_array($valor) ? $valor : [],
                default => $valor,
            };
        }

        return $normalizados;
    }

    private static function entero(mixed $valor): ?int
    {
        return is_int($valor) || (is_string($valor) && is_numeric($valor)) ? (int) $valor : null;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, list<string>>
     */
    private function limiteErrors(array $datos): array
    {
        $sinVentana = ($datos['max_por_cliente_ventana'] ?? null) === null;
        $conCantidad = ($datos['max_por_cliente_cantidad'] ?? null) !== null;

        return $sinVentana && $conCantidad
            ? ['max_por_cliente_ventana' => ['Elige la ventana del límite por cliente (mes, año o de por vida).']]
            : [];
    }
}
