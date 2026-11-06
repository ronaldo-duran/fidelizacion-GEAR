<?php

declare(strict_types=1);

namespace App\Livewire\Cliente;

use App\Actions\Notificaciones\MarkAvisosLeidos;
use App\Models\Cliente;
use App\Models\EnvioNotificacion;
use App\Notificaciones\AvisosDelCliente;
use App\Notificaciones\RenderPlantilla;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * La bandeja de avisos del cliente (E7-01). Al abrirla, los avisos quedan
 * leídos; los que eran nuevos se marcan así solo en esta visita.
 */
#[Layout('components.layouts.cliente')]
#[Title('Avisos')]
class Avisos extends Component
{
    /** @var list<int> */
    public array $nuevos = [];

    /**
     * Los avisos de esta petición: mount y render los leen una sola vez.
     *
     * @var Collection<int, EnvioNotificacion>|null
     */
    private ?Collection $avisos = null;

    public function mount(MarkAvisosLeidos $marcar): void
    {
        $this->nuevos = array_values($this->avisos()
            ->filter(static fn (EnvioNotificacion $aviso): bool => $aviso->leido_at === null)
            ->map(static fn (EnvioNotificacion $aviso): int => $aviso->id)
            ->all());

        $marcar($this->cliente()->id);
    }

    public function render(RenderPlantilla $render): View
    {
        return view('livewire.cliente.avisos', ['avisos' => $this->avisos(), 'render' => $render]);
    }

    /**
     * @return Collection<int, EnvioNotificacion>
     */
    private function avisos(): Collection
    {
        return $this->avisos ??= app(AvisosDelCliente::class)->recientes($this->cliente()->id);
    }

    private function cliente(): Cliente
    {
        $cliente = auth('cliente')->user();

        abort_unless($cliente instanceof Cliente, 403);

        return $cliente;
    }
}
