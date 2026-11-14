<?php

declare(strict_types=1);

namespace App\Livewire\Cliente;

use App\Models\Cliente;
use App\Puntos\HistorialCliente;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Historial de puntos del cliente (E8-05, pantalla 1q). Solo lee el libro:
 * todo lo arma `HistorialCliente`.
 */
#[Layout('components.layouts.cliente')]
#[Title('Historial')]
class Historial extends Component
{
    public function render(HistorialCliente $historial): View
    {
        $cliente = auth('cliente')->user();

        abort_unless($cliente instanceof Cliente, 403);

        return view('livewire.cliente.historial', ['historial' => $historial->para($cliente)]);
    }
}
