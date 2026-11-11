<?php

declare(strict_types=1);

namespace App\Livewire\Cliente;

use App\Models\Cliente;
use App\Niveles\ResumenNivel;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Inicio del cliente (E8-03, pantalla 1f sin QR). El bloque del nivel sale
 * en la primera respuesta para que un ascenso se vea al instante (regla dura
 * 3.6); la lista "Para usar hoy" se carga aparte (ParaUsarHoy).
 */
#[Layout('components.layouts.cliente')]
#[Title('Inicio')]
class Inicio extends Component
{
    public function render(ResumenNivel $resumen): View
    {
        $cliente = auth('cliente')->user();

        abort_unless($cliente instanceof Cliente, 403);

        return view('livewire.cliente.inicio', ['resumen' => $resumen->para($cliente)]);
    }
}
