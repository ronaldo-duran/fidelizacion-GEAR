<?php

declare(strict_types=1);

namespace App\Actions\Clientes;

use App\Actions\Verificacion\VerifyCode;
use App\Enums\PropositoVerificacion;
use App\Enums\TipoDocumento;
use App\Models\Cliente;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * Segundo paso de "Olvidé mi PIN" (E8-02): con el código del SMS correcto,
 * cambia el PIN. Cambiar el hash cierra las otras sesiones del cliente (el
 * middleware `auth.session` compara el hash guardado en cada sesión) y se
 * renueva el token de "recordarme", así ningún otro dispositivo sigue
 * adentro.
 */
class ResetPin
{
    private readonly VerifyCode $verifyCode;

    public function __construct(VerifyCode $verifyCode)
    {
        $this->verifyCode = $verifyCode;
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(
        TipoDocumento $tipo,
        string $documento,
        string $codigo,
        #[SensitiveParameter]
        string $pinNuevo,
    ): Cliente {
        $cliente = Cliente::query()
            ->where('tipo_documento', $tipo->value)
            ->where('documento', Cliente::normalizeDocumento($documento))
            ->where('celular_verificado_at', '!=', null)
            ->first();

        $cambiado = $cliente !== null && is_string($cliente->celular) && DB::transaction(
            function () use ($cliente, $codigo, $pinNuevo): bool {
                if (! ($this->verifyCode)(PropositoVerificacion::RecuperarPin, (string) $cliente->celular, $codigo)) {
                    return false;
                }

                $cliente->forceFill(['pin_hash' => Hash::make($pinNuevo)]);
                $cliente->setRememberToken(Str::random(60));
                $cliente->save();

                return true;
            },
        );

        if (! $cambiado || $cliente === null) {
            throw ValidationException::withMessages(['codigo' => CompleteRegistro::CODIGO_INVALIDO]);
        }

        return $cliente;
    }
}
