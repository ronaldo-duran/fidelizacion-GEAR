<?php

declare(strict_types=1);

namespace App\Livewire\Cliente;

use App\Beneficios\CatalogoCliente;
use App\Models\Cliente;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Catálogo de beneficios del cliente (E8-04, pantalla 1n). La ficha de
 * categoría elegida queda en la URL; el resto lo arma `CatalogoCliente`.
 */
#[Layout('components.layouts.cliente')]
#[Title('Beneficios')]
class Beneficios extends Component
{
    #[Url(except: null)]
    public ?string $categoria = null;

    public function render(CatalogoCliente $catalogo): View
    {
        $cliente = auth('cliente')->user();

        abort_unless($cliente instanceof Cliente, 403);

        $resultado = $catalogo->para($cliente, $this->categoria);
        $this->categoria = $resultado->categoria;

        return view('livewire.cliente.beneficios', [
            'catalogo' => $resultado,
            'nivel' => $cliente->nivel_efectivo,
        ]);
    }
}
