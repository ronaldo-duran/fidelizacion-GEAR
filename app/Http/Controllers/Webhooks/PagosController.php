<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Actions\Membresias\ApplyNotificacionPago;
use App\Actions\Membresias\RecordFirmaInvalida;
use App\Pagos\FirmaInvalida;
use App\Pagos\Pasarelas;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Webhook de la pasarela de pagos (E6-01). Sin sesión ni CSRF. Responde 401
 * a una firma inválida y 200 a todo lo demás, para que la pasarela no
 * reintente algo que no va a cambiar.
 */
class PagosController
{
    public function __invoke(
        Request $request,
        string $pasarela,
        Pasarelas $pasarelas,
        ApplyNotificacionPago $apply,
        RecordFirmaInvalida $recordFirmaInvalida,
    ): Response {
        $adaptador = $pasarelas->para($pasarela);
        abort_if($adaptador === null, 404);

        try {
            $notificacion = $adaptador->notificacion($request);
        } catch (FirmaInvalida $error) {
            $recordFirmaInvalida($pasarela, $error);

            return response()->noContent(401);
        }

        $apply($pasarela, $notificacion);

        return response()->noContent(200);
    }
}
