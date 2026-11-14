<?php

declare(strict_types=1);

namespace App\Actions\Clientes;

use App\Models\Cliente;
use App\Models\PreferenciaAviso;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Enciende o apaga un aviso por correo (E8-05, regla 9). Solo los eventos con
 * interruptor; sin correo no hay nada que apagar. El upsert sobre la
 * restricción única deja una sola fila por cliente y evento aunque lleguen
 * dos cambios a la vez.
 */
class SetPreferenciaAviso
{
    /**
     * @throws ValidationException
     */
    public function __invoke(Cliente $cliente, string $evento, bool $correo): void
    {
        if (! array_key_exists($evento, PreferenciaAviso::interruptores()) || trim((string) $cliente->email) === '') {
            throw ValidationException::withMessages(['avisos' => 'No pudimos guardar el cambio. Intenta de nuevo.']);
        }

        PreferenciaAviso::query()->upsert(
            [[
                'cliente_id' => $cliente->id,
                'evento' => $evento,
                'correo' => $correo,
                'updated_at' => CarbonImmutable::now(),
            ]],
            ['cliente_id', 'evento'],
            ['correo', 'updated_at'],
        );
    }
}
