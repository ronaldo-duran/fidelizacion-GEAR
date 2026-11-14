<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\TipoDocumento;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

/**
 * Base de las peticiones de la API pública (E9-01). La autorización ya la
 * hicieron los middleware (credencial, alcance y tope); aquí solo se valida
 * la forma de los datos. Los mensajes van en español de Colombia.
 */
abstract class ApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * El usuario de integración de la credencial que hace la petición.
     */
    public function credencial(): User
    {
        $user = $this->user();

        return $user instanceof User ? $user : throw new LogicException('La API siempre tiene credencial.');
    }

    /**
     * Un entero JSON, nunca un texto con dígitos ni un número con decimales
     * (regla dura 3.9): `"85000"` y `85000.0` no sirven.
     *
     * @return Closure(string, mixed, Closure(string): mixed): void
     */
    protected static function enteroJson(): Closure
    {
        return static function (string $campo, mixed $valor, Closure $fail): void {
            if (! is_int($valor)) {
                $fail("El campo {$campo} debe ser un número entero, sin comillas ni decimales.");
            }
        };
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected static function documentoRules(string $requerido = 'required'): array
    {
        $nulo = $requerido === 'required' ? [] : ['nullable'];

        return [
            'tipo_documento' => [$requerido, ...$nulo, Rule::enum(TipoDocumento::class)],
            'documento' => [$requerido, ...$nulo, 'string', 'max:30'],
        ];
    }

    public function tipoDocumento(): ?TipoDocumento
    {
        return $this->enum('tipo_documento', TipoDocumento::class);
    }

    public function documento(): ?string
    {
        $documento = $this->input('documento');

        return is_string($documento) ? $documento : null;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'Falta este dato.',
            'required_without' => 'Falta este dato.',
            'string' => 'Debe ser un texto.',
            'integer' => 'Debe ser un número entero.',
            'min' => 'Debe ser mayor que cero.',
            'max' => 'Es demasiado grande.',
            'enum' => 'No es un valor permitido.',
        ];
    }
}
