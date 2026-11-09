<?php

declare(strict_types=1);

namespace App\Livewire\Cliente;

use App\Models\Cliente;
use App\Niveles\PantallaNiveles;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Pantalla de niveles (E8-03, pantalla 1r): las cuatro tarjetas y las tres
 * vías de Diamante. Los datos los arma `PantallaNiveles`.
 */
#[Layout('components.layouts.cliente')]
#[Title('Niveles')]
class Niveles extends Component
{
    public function render(PantallaNiveles $pantalla): View
    {
        $cliente = auth('cliente')->user();

        abort_unless($cliente instanceof Cliente, 403);

        return view('livewire.cliente.niveles', [
            'tarjetas' => $pantalla->tarjetas($cliente),
            'mesesInmueble' => $pantalla->mesesPorInmueble(),
            'planDesde' => $pantalla->planDesde(),
            'conMembresia' => $pantalla->tieneMembresiaVigente($cliente),
        ]);
    }
}
