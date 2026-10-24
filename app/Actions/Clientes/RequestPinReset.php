<?php

declare(strict_types=1);

namespace App\Actions\Clientes;

use App\Actions\Verificacion\SendVerificationCode;
use App\Enums\PropositoVerificacion;
use App\Enums\TipoDocumento;
use App\Models\Cliente;
use Illuminate\Validation\ValidationException;

/**
 * Primer paso de "Olvidé mi PIN" (E8-02): manda un código por SMS al celular
 * registrado del cliente (E8-06). Responde igual exista o no el documento, y
 * también cuando un límite de envío frena el SMS, porque ese rechazo solo le
 * pasa a un documento que existe y lo delataría.
 */
class RequestPinReset
{
    public const RESPUESTA = 'Si los datos coinciden, te enviamos un código al celular registrado.';

    private readonly SendVerificationCode $sendCode;

    public function __construct(SendVerificationCode $sendCode)
    {
        $this->sendCode = $sendCode;
    }

    public function __invoke(TipoDocumento $tipo, string $documento, ?string $ip = null): string
    {
        $celular = Cliente::query()
            ->where('tipo_documento', $tipo->value)
            ->where('documento', Cliente::normalizeDocumento($documento))
            ->where('celular_verificado_at', '!=', null)
            ->value('celular');

        if (! is_string($celular)) {
            return self::RESPUESTA;
        }

        try {
            ($this->sendCode)(PropositoVerificacion::RecuperarPin, $celular, $ip);
        } catch (ValidationException) {
            // Límite de envío: se responde igual para no delatar que el documento existe.
            return self::RESPUESTA;
        }

        return self::RESPUESTA;
    }
}
