<?php

declare(strict_types=1);

namespace App\Livewire\Cajero;

use App\Actions\Cajeros\LoginCajero;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Ingreso del cajero (E4-02, diseño 1y): correo, contraseña y "Mantener la
 * sesión en este celular". Quien ya tiene sesión de cajero va directo a la caja.
 */
#[Layout('components.layouts.cliente')]
#[Title('Ingreso a la caja')]
class Ingreso extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $recordar = true;

    public function mount(): void
    {
        $user = Auth::guard('web')->user();

        if ($user instanceof User && $user->puedeEntrarALaCaja()) {
            $this->redirectRoute('caja.inicio');
        }
    }

    public function submit(LoginCajero $login): void
    {
        $this->validate(
            ['email' => ['required', 'string', 'max:255'], 'password' => ['required', 'string', 'max:255']],
            ['email.required' => 'Escribe tu correo.', 'password.required' => 'Escribe tu contraseña.'],
        );

        try {
            $user = $login($this->email, $this->password);
        } catch (ValidationException $e) {
            $this->addError('email', $e->validator->errors()->first());
            $this->password = '';

            return;
        }

        Auth::guard('web')->login($user, $this->recordar);
        Session::regenerate();
        $this->redirectRoute('caja.inicio');
    }

    public function render(): View
    {
        return view('livewire.cajero.ingreso');
    }
}
