<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente;

use App\Actions\Membresias\SimulatePago;
use App\Http\Requests\Cliente\PagoSimuladoRequest;
use App\Models\Cliente;
use App\Models\PagoMembresia;
use App\Pagos\SimuladaPasarela;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * La pantalla de la pasarela simulada (solo local y E2E): muestra el pago y
 * lo aprueba o rechaza como lo haría la pasarela real.
 */
class PagoSimuladoController
{
    public function show(Request $request, string $referencia): View
    {
        return view('pagos.simulada', ['pago' => $this->pago($request, $referencia)]);
    }

    public function store(PagoSimuladoRequest $request, string $referencia, SimulatePago $simulate): RedirectResponse
    {
        $pago = $this->pago($request, $referencia);
        $simulate($pago, $request->aprobar());

        $regreso = $request->regreso();

        return $regreso !== null
            ? redirect()->to($regreso)
            : redirect()->route('cliente.membresia.pago', $pago->referencia);
    }

    private function pago(Request $request, string $referencia): PagoMembresia
    {
        abort_unless(SimuladaPasarela::habilitada(), 404);

        $cliente = $request->user('cliente');
        abort_unless($cliente instanceof Cliente, 403);

        return PagoMembresia::query()
            ->where('referencia', $referencia)
            ->where('cliente_id', $cliente->id)
            ->where('pasarela', 'simulada')
            ->firstOrFail();
    }
}
