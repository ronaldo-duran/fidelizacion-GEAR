<?php

declare(strict_types=1);

namespace App\Actions\Clientes;

use App\Models\Cliente;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * El cliente agrega su fecha de nacimiento desde el perfil (E8-05, regla 8 y
 * decisión 3). Solo si no la tenía: cambiarla permitiría mover el cumpleaños
 * para repetir un beneficio, así que una corrección la hace el panel. La
 * condición va en el UPDATE, así dos envíos a la vez no la cambian.
 */
class SetFechaNacimiento
{
    public const YA_REGISTRADA = 'Ya registraste tu fecha de nacimiento. Para corregirla, comunícate con el Club.';

    /**
     * @throws ValidationException
     */
    public function __invoke(Cliente $cliente, string $fecha): void
    {
        Validator::make(
            ['fechaNacimiento' => $fecha],
            ['fechaNacimiento' => [
                'required',
                'date_format:Y-m-d',
                'after:1900-01-01',
                'before:' . CarbonImmutable::today()->toDateString(),
            ]],
            [
                'fechaNacimiento.required' => 'Escribe tu fecha de nacimiento.',
                'fechaNacimiento.*' => 'Revisa la fecha de nacimiento.',
            ],
        )->validate();

        $guardada = Cliente::query()->toBase()
            ->where('id', $cliente->id)
            ->whereNull('fecha_nacimiento')
            ->update(['fecha_nacimiento' => $fecha, 'updated_at' => CarbonImmutable::now()]);

        if ($guardada === 0) {
            throw ValidationException::withMessages(['fechaNacimiento' => self::YA_REGISTRADA]);
        }

        $cliente->refresh();
    }
}
