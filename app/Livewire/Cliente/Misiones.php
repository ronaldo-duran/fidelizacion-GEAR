<?php

declare(strict_types=1);

namespace App\Livewire\Cliente;

use App\Actions\Promociones\SendCumplimientoMision;
use App\Models\Cliente;
use App\Promociones\MisionesParaCliente;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Misiones del cliente (E3-06): las misiones vigentes con su estado y el
 * envío del pantallazo. Lo valida y lo registra `SendCumplimientoMision`;
 * aquí solo se maneja qué misión está abierta.
 */
#[Layout('components.layouts.cliente')]
#[Title('Misiones')]
class Misiones extends Component
{
    use WithFileUploads;

    /** El código de la misión cuyo envío está abierto. */
    public ?string $abierta = null;

    /** @var UploadedFile|null el pantallazo, mientras se sube */
    public $soporte = null;

    public bool $enviada = false;

    public function abrir(string $codigo): void
    {
        $this->resetErrorBag();
        $this->soporte = null;
        $this->enviada = false;
        $this->abierta = $codigo;
    }

    public function cerrar(): void
    {
        $this->resetErrorBag();
        $this->soporte = null;
        $this->abierta = null;
    }

    public function enviar(SendCumplimientoMision $send): void
    {
        if ($this->abierta === null) {
            return;
        }

        $this->validate(
            ['soporte' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']],
            [
                'soporte.image' => 'El soporte debe ser una imagen.',
                'soporte.mimes' => 'Adjunta una imagen JPG, PNG o WEBP.',
                'soporte.max' => 'La imagen no puede pasar de 5 MB.',
            ],
        );

        try {
            $send($this->cliente()->id, $this->abierta, $this->soporte instanceof UploadedFile ? $this->soporte : null);
        } catch (ValidationException $e) {
            $this->addError('soporte', (string) $e->validator->errors()->first());

            return;
        }

        $this->cerrar();
        $this->enviada = true;
    }

    public function render(): View
    {
        $misiones = app(MisionesParaCliente::class)->para($this->cliente()->id);

        return view('livewire.cliente.misiones', ['misiones' => $misiones]);
    }

    private function cliente(): Cliente
    {
        $cliente = auth('cliente')->user();

        abort_unless($cliente instanceof Cliente, 403);

        return $cliente;
    }
}
