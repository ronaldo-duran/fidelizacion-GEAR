<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente;

use App\Enums\CanalRegistro;
use App\Enums\TipoDocumento;
use App\Models\Cliente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Abre el registro desde el QR de la caja. La firma y el vencimiento los
 * revisa el middleware `signed` (un enlace vencido o alterado responde 403);
 * aquí solo se pasan el tipo y el número al formulario.
 */
class RegistroCajaController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $tipo = TipoDocumento::tryFrom($request->string('tipo')->toString());
        $documento = Cliente::normalizeDocumento($request->string('documento')->toString());

        if ($tipo === null || $documento === '') {
            abort(403);
        }

        return redirect()->route('cliente.registro')->with('registro.prefill', [
            'tipo_documento' => $tipo->value,
            'documento' => $documento,
            'canal' => CanalRegistro::CajaQr->value,
        ]);
    }
}
