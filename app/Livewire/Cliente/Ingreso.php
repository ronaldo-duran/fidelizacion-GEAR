<?php

declare(strict_types=1);

namespace App\Livewire\Cliente;

use App\Actions\Clientes\LoginCliente;
use App\Enums\TipoDocumento;
use App\Livewire\Forms\IngresoForm;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Ingreso del cliente (E8-02): tipo + número de documento y PIN. La sesión
 * se mantiene en el dispositivo hasta cerrar sesión.
 */
#[Layout('components.layouts.cliente')]
#[Title('Ingresa')]
class Ingreso extends Component
{
    public IngresoForm $form;

    public function submit(LoginCliente $login): void
    {
        $this->form->validate();

        try {
            $cliente = $login($this->form->tipo(), $this->form->documento, $this->form->pin);
        } catch (ValidationException $e) {
            $this->form->addError('documento', $e->validator->errors()->first());
            $this->form->pin = '';

            return;
        }

        Auth::guard('cliente')->login($cliente, true);
        Session::regenerate();
        $this->redirectIntended(route('cliente.inicio'));
    }

    public function render(): View
    {
        return view('livewire.cliente.ingreso', ['tipos' => TipoDocumento::cases()]);
    }
}
