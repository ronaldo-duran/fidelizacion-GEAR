<?php

declare(strict_types=1);

namespace App\Livewire\Cliente;

use App\Beneficios\BeneficiosParaCliente;
use App\Models\Cliente;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * "Para usar hoy" del inicio (E8-03, regla 6): los beneficios disponibles
 * hoy hasta el tope de `config/inicio.php`. Se carga diferida; mientras
 * tanto se ve un skeleton.
 */
#[Lazy]
class ParaUsarHoy extends Component
{
    public function placeholder(): View
    {
        return view('livewire.cliente.para-usar-hoy-cargando');
    }

    public function render(BeneficiosParaCliente $beneficios): View
    {
        $cliente = auth('cliente')->user();

        abort_unless($cliente instanceof Cliente, 403);

        $disponibles = $beneficios->disponibles($cliente);
        $max = config('inicio.para_usar_hoy_max');

        return view('livewire.cliente.para-usar-hoy', [
            'beneficios' => array_slice($disponibles, 0, is_int($max) ? $max : count($disponibles)),
            'total' => count($disponibles),
        ]);
    }
}
