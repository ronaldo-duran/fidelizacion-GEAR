<?php

declare(strict_types=1);

namespace App\Livewire\Cliente;

use App\Actions\Clientes\RequestPinReset;
use App\Actions\Clientes\ResetPin;
use App\Enums\TipoDocumento;
use App\Livewire\Forms\RecuperarPinForm;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * "Olvidé mi PIN" (E8-02): primero el documento, que recibe un código por SMS
 * en su celular registrado; luego el código y el PIN nuevo.
 */
#[Layout('components.layouts.cliente')]
#[Title('Recupera tu PIN')]
class RecuperarPin extends Component
{
    public RecuperarPinForm $form;

    public bool $codigoEnviado = false;

    public ?string $aviso = null;

    public function requestCode(RequestPinReset $request): void
    {
        $this->form->validate($this->form->documentoRules(), $this->form->messages());

        $this->aviso = $request($this->form->tipo(), $this->form->documento, request()->ip());
        $this->codigoEnviado = true;
    }

    public function changePin(ResetPin $reset): void
    {
        $this->form->validate($this->form->pinRules(), $this->form->messages());

        try {
            $cliente = $reset($this->form->tipo(), $this->form->documento, $this->form->codigo, $this->form->pin);
        } catch (ValidationException $e) {
            $this->form->addError('codigo', $e->validator->errors()->first());

            return;
        }

        Auth::guard('cliente')->login($cliente, true);
        Session::regenerate();
        $this->redirectRoute('cliente.inicio');
    }

    public function render(): View
    {
        return view('livewire.cliente.recuperar-pin', ['tipos' => TipoDocumento::cases()]);
    }
}
