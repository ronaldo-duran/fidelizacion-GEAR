<?php

declare(strict_types=1);

namespace App\Actions\Clientes;

use App\Models\Cliente;
use App\Models\SupresionCorreo;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * El cliente agrega o cambia su correo desde el perfil (E8-05, regla 8). Se
 * guarda en minúsculas y sin espacios (regla dura 3.13).
 */
class UpdateCorreoCliente
{
    /**
     * @throws ValidationException
     */
    public function __invoke(Cliente $cliente, string $email): void
    {
        $email = SupresionCorreo::normalizeEmail($email);

        Validator::make(
            ['email' => $email],
            ['email' => ['required', 'email', 'max:255']],
            ['email.required' => 'Escribe tu correo.', 'email.*' => 'Revisa el correo.'],
        )->validate();

        $cliente->forceFill(['email' => $email])->save();
    }
}
