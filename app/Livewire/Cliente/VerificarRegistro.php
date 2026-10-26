<?php

declare(strict_types=1);

namespace App\Livewire\Cliente;

use App\Actions\Clientes\CompleteRegistro;
use App\Actions\Verificacion\SendVerificationCode;
use App\Enums\PropositoVerificacion;
use App\Models\RegistroPendiente;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Verificación del celular del registro (E8-02 con E8-06). Con el código
 * correcto se crea la cuenta y el cliente queda con la sesión iniciada.
 */
#[Layout('components.layouts.cliente')]
#[Title('Verifica tu celular')]
class VerificarRegistro extends Component
{
    public string $codigo = '';

    public ?string $aviso = null;

    #[Locked]
    public ?int $pendienteId = null;

    public function mount(): void
    {
        $id = session(Registro::SESION_PENDIENTE);
        $this->pendienteId = is_int($id) ? $id : null;
    }

    public function verify(CompleteRegistro $complete): void
    {
        try {
            $cliente = $complete($this->pendienteId ?? 0, $this->codigo);
        } catch (ValidationException $e) {
            $this->addError('codigo', $e->validator->errors()->first());

            return;
        }

        Session::forget(Registro::SESION_PENDIENTE);
        Auth::guard('cliente')->login($cliente, true);
        Session::regenerate();
        $this->redirectRoute('cliente.inicio');
    }

    public function resend(SendVerificationCode $send): void
    {
        $pendiente = $this->pendiente();

        if ($pendiente === null) {
            return;
        }

        try {
            $send(PropositoVerificacion::Registro, $pendiente->celular, request()->ip());
            $this->aviso = 'Te enviamos un código nuevo.';
        } catch (ValidationException $e) {
            $this->addError('codigo', $e->validator->errors()->first());
        }
    }

    public function render(): View
    {
        return view('livewire.cliente.verificar-registro', ['pendiente' => $this->pendiente()]);
    }

    private function pendiente(): ?RegistroPendiente
    {
        $pendiente = $this->pendienteId === null ? null : RegistroPendiente::query()->find($this->pendienteId);

        return $pendiente !== null && $pendiente->vence_at->isFuture() ? $pendiente : null;
    }
}
