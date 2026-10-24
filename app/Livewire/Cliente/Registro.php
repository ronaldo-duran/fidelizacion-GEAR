<?php

declare(strict_types=1);

namespace App\Livewire\Cliente;

use App\Actions\Clientes\StartRegistro;
use App\Enums\CanalRegistro;
use App\Enums\TipoDocumento;
use App\Livewire\Forms\RegistroForm;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Pantalla de registro (E8-02, 1m). Desde el QR de la caja llega con el tipo
 * y el número puestos.
 */
#[Layout('components.layouts.cliente')]
#[Title('Únete al Club')]
class Registro extends Component
{
    public const SESION_PENDIENTE = 'registro.pendiente';

    public RegistroForm $form;

    #[Locked]
    public string $canal = 'pwa';

    public function mount(): void
    {
        $prefill = session('registro.prefill');

        if (is_array($prefill) && is_string($prefill['tipo_documento'] ?? null)) {
            $this->form->tipoDocumento = $prefill['tipo_documento'];
            $this->form->documento = is_string($prefill['documento'] ?? null) ? $prefill['documento'] : '';
            $this->canal = CanalRegistro::CajaQr->value;
        }
    }

    public function submit(StartRegistro $start): void
    {
        $this->form->normalize();
        $this->form->validate();

        try {
            $pendiente = $start($this->form->datos(CanalRegistro::from($this->canal), request()->ip()));
        } catch (ValidationException $e) {
            foreach ($e->validator->errors()->messages() as $campo => $mensajes) {
                $this->form->addError($campo, $mensajes[0]);
            }

            return;
        }

        Session::put(self::SESION_PENDIENTE, $pendiente->id);
        $this->redirectRoute('cliente.registro.verificar');
    }

    public function render(): View
    {
        return view('livewire.cliente.registro', ['tipos' => TipoDocumento::cases()]);
    }
}
