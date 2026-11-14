<?php

declare(strict_types=1);

namespace App\Livewire\Cliente;

use App\Actions\Clientes\SetFechaNacimiento;
use App\Actions\Clientes\SetPreferenciaAviso;
use App\Actions\Clientes\UpdateCorreoCliente;
use App\Clientes\PerfilCliente;
use App\Models\Cliente;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Perfil del cliente (E8-05, pantalla 1t): sus datos, los avisos por correo y
 * los documentos. Las escrituras las hacen las Actions de `app/Actions/Clientes`.
 */
#[Layout('components.layouts.cliente')]
#[Title('Mi perfil')]
class Perfil extends Component
{
    /** El dato que se está editando: `email`, `fechaNacimiento` o ninguno. */
    public ?string $editando = null;

    public string $email = '';

    public string $fechaNacimiento = '';

    public function editar(string $campo): void
    {
        $this->resetErrorBag();
        $this->editando = in_array($campo, ['email', 'fechaNacimiento'], true) ? $campo : null;
        $this->email = (string) self::cliente()->email;
    }

    public function cancelar(): void
    {
        $this->resetErrorBag();
        $this->editando = null;
    }

    public function guardarCorreo(UpdateCorreoCliente $update): void
    {
        $this->guardar(fn () => $update(self::cliente(), $this->email));
    }

    public function guardarFecha(SetFechaNacimiento $set): void
    {
        $this->guardar(fn () => $set(self::cliente(), $this->fechaNacimiento));
    }

    /**
     * Si no se pudo guardar, el interruptor vuelve a su estado con el mensaje.
     */
    public function cambiarAviso(string $evento, bool $correo, SetPreferenciaAviso $set): void
    {
        $this->resetErrorBag('avisos');

        try {
            $set(self::cliente(), $evento, $correo);
        } catch (ValidationException $e) {
            $this->addError('avisos', $e->validator->errors()->first());
        }
    }

    public function render(): View
    {
        return view('livewire.cliente.perfil', ['perfil' => new PerfilCliente(self::cliente())]);
    }

    /**
     * @param  callable(): void  $accion
     */
    private function guardar(callable $accion): void
    {
        $this->resetErrorBag();

        try {
            $accion();
            $this->editando = null;
        } catch (ValidationException $e) {
            foreach ($e->validator->errors()->messages() as $campo => $mensajes) {
                $this->addError($campo, $mensajes[0]);
            }
        }
    }

    private static function cliente(): Cliente
    {
        $cliente = auth('cliente')->user();

        abort_unless($cliente instanceof Cliente, 403);

        return $cliente;
    }
}
