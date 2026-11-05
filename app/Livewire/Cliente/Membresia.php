<?php

declare(strict_types=1);

namespace App\Livewire\Cliente;

use App\Actions\Membresias\StartPagoMembresia;
use App\Enums\Nivel;
use App\Models\Cliente;
use App\Models\PlanMembresia;
use App\Niveles\NivelCalculator;
use App\Niveles\NivelEfectivo;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Compra o renovación de la membresía (E6-01, pantalla 1s). Elegir el plan
 * es lo único que pasa aquí: el pago lo inicia `StartPagoMembresia` y lo
 * confirma solo el webhook de la pasarela.
 */
#[Layout('components.layouts.cliente')]
#[Title('Suscripción Diamante')]
class Membresia extends Component
{
    public ?int $plan = null;

    public function mount(): void
    {
        $this->plan = $this->planes()->first()?->id;
    }

    public function pagar(StartPagoMembresia $start): void
    {
        $this->redirect($start($this->cliente(), (int) $this->plan));
    }

    public function render(NivelEfectivo $niveles, NivelCalculator $calculator): View
    {
        $planes = $this->planes();

        return view('livewire.cliente.membresia', [
            'cliente' => $this->cliente(),
            'planes' => $planes,
            'elegido' => $planes->firstWhere('id', $this->plan),
            'umbralDiamante' => $calculator->umbral(Nivel::Diamante, $niveles->definicionNiveles()),
        ]);
    }

    /**
     * @return Collection<int, PlanMembresia>
     */
    private function planes(): Collection
    {
        $query = PlanMembresia::query();
        $query->getQuery()->where('activo', true)->orderBy('duracion_meses');

        return $query->get();
    }

    private function cliente(): Cliente
    {
        $cliente = auth('cliente')->user();

        abort_unless($cliente instanceof Cliente, 403);

        return $cliente;
    }
}
