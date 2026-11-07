<?php

declare(strict_types=1);

namespace App\Livewire\Cliente;

use App\Models\Cliente;
use App\Models\PagoMembresia;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Estado de un pago de membresía del propio cliente (E6-01). Volver de la
 * pasarela no activa nada: esta pantalla solo muestra lo que el webhook ya
 * dejó en la base de datos y se actualiza sola mientras el pago está
 * pendiente.
 */
#[Layout('components.layouts.cliente')]
#[Title('Tu pago')]
class EstadoPago extends Component
{
    public string $referencia;

    /** El pago de esta petición: mount y render lo leen una sola vez. */
    private ?PagoMembresia $pago = null;

    public function mount(string $referencia): void
    {
        $this->referencia = $referencia;
        $this->pago();
    }

    public function render(): View
    {
        $pago = $this->pago();

        return view('livewire.cliente.estado-pago', ['pago' => $pago]);
    }

    private function pago(): PagoMembresia
    {
        if ($this->pago !== null) {
            return $this->pago;
        }

        $query = PagoMembresia::query()->with(['plan', 'membresia']);
        $query->getQuery()->where('referencia', $this->referencia)->where('cliente_id', $this->cliente()->id);

        return $this->pago = $query->firstOrFail();
    }

    private function cliente(): Cliente
    {
        $cliente = auth('cliente')->user();

        abort_unless($cliente instanceof Cliente, 403);

        return $cliente;
    }
}
