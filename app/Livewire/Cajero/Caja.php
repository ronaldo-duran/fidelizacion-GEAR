<?php

declare(strict_types=1);

namespace App\Livewire\Cajero;

use App\Actions\Clientes\MakeEnlaceRegistro;
use App\Actions\Compras\AnnulCompra;
use App\Actions\Compras\RegisterCompra;
use App\Actions\Credenciales\ValidateCredencial;
use App\Compras\AnulacionCajero;
use App\Compras\NuevaCompra;
use App\Compras\RegistroCompra;
use App\Credenciales\QrRenderer;
use App\Enums\CanalCompra;
use App\Enums\PropositoCredencial;
use App\Livewire\Forms\IdentificarForm;
use App\Models\Cliente;
use App\Models\User;
use App\Niveles\ProximoNivel;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * La caja del comercio (E4-02): monto primero, identificar al cliente por
 * documento o por su QR, confirmar y registrar. Si el documento no está
 * registrado, el monto no se pierde: se guarda pendiente o se le muestra el
 * QR de registro. Toda la lógica vive en las Actions de E4-01, E4-04 y E8-02;
 * aquí solo se maneja el paso de la pantalla.
 *
 * Pasos: monto → identificar → confirmar → registrada, o identificar →
 * no_registrado → registro_qr.
 */
#[Layout('components.layouts.cajero')]
#[Title('Caja')]
class Caja extends Component
{
    #[Locked]
    public string $paso = 'monto';

    #[Locked]
    public int $monto = 0;

    /** Cómo identifica al cliente: 'documento' o 'qr'. */
    public string $metodo = 'documento';

    public IdentificarForm $form;

    #[Locked]
    public ?int $clienteId = null;

    /** Referencia de este intento: repetirla devuelve la misma compra (regla dura 3.7). */
    #[Locked]
    public string $referencia = '';

    #[Locked]
    public ?int $transaccionId = null;

    #[Locked]
    public int $puntos = 0;

    #[Locked]
    public ?string $aviso = null;

    #[Locked]
    public ?string $enlace = null;

    public function continuar(int $monto): void
    {
        $maximo = 10 ** self::setting('max_amount_digits') - 1;

        if ($monto < 1 || $monto > $maximo) {
            $this->addError('monto', 'Escribe el monto de la compra.');

            return;
        }

        $this->monto = $monto;
        $this->aviso = null;
        $this->volverA('identificar');
    }

    /**
     * Vuelve al monto (sin perderlo) o a identificar al cliente ("Corregir cédula").
     */
    public function volverA(string $paso): void
    {
        if (! in_array($paso, ['monto', 'identificar'], true)) {
            return;
        }

        $this->paso = $paso;
        $this->metodo = 'documento';
        $this->clienteId = null;
        $this->referencia = '';
        $this->form->codigo = '';
        $this->resetErrorBag();
    }

    public function buscar(): void
    {
        if ($this->paso !== 'identificar') {
            return;
        }

        $this->form->validarDocumento();

        $cliente = Cliente::query()
            ->where('tipo_documento', $this->form->tipoDocumento)
            ->where('documento', $this->form->documento)
            ->first();

        $this->referencia = (string) Str::uuid();
        $this->clienteId = $cliente?->id;
        $this->paso = $cliente === null ? 'no_registrado' : 'confirmar';
    }

    /**
     * Lo que leyó la cámara o, sin nada, el código corto digitado (E4-04).
     */
    public function identificarQr(ValidateCredencial $validate, ?string $entrada = null): void
    {
        if ($this->paso !== 'identificar') {
            return;
        }

        $validacion = $validate($this->cajero(), $entrada ?? $this->form->codigo, PropositoCredencial::Identificar);

        if (! $validacion->esValida() || $validacion->cliente === null) {
            $this->addError('form.codigo', $validacion->resultado->mensaje());

            return;
        }

        $this->form->usar($validacion->cliente);
        $this->clienteId = $validacion->cliente->id;
        $this->referencia = (string) Str::uuid();
        $this->paso = 'confirmar';
    }

    public function registrar(RegisterCompra $register): void
    {
        if ($this->paso !== 'confirmar') {
            return;
        }

        $registro = $this->registrarCompra($register);

        if ($registro === null) {
            return;
        }

        $this->transaccionId = $registro->transaccion->id;
        $this->puntos = $registro->repetida
            ? $registro->transaccion->puntosOtorgados()
            : $registro->puntos->puntos;
        $this->paso = 'registrada';
    }

    /**
     * Documento no registrado: la compra queda pendiente y se acredita sola
     * cuando el cliente se registre (E4-01).
     */
    public function guardarPendiente(RegisterCompra $register): void
    {
        if ($this->paso !== 'no_registrado' || $this->registrarCompra($register) === null) {
            return;
        }

        $aviso = 'Compra pendiente guardada. Los puntos se le suman cuando se registre.';
        $this->nuevaCompra();
        $this->aviso = $aviso;
    }

    /**
     * Guarda la compra pendiente y muestra el QR para que el cliente se registre desde su celular (1i).
     */
    public function registrarAhora(RegisterCompra $register, MakeEnlaceRegistro $enlace): void
    {
        if ($this->paso !== 'no_registrado' || $this->registrarCompra($register) === null) {
            return;
        }

        $this->enlace = $enlace($this->form->tipo(), $this->form->documento);
        $this->paso = 'registro_qr';
    }

    public function nuevaCompra(): void
    {
        $this->reset('monto', 'transaccionId', 'puntos', 'aviso', 'enlace');
        $this->form->reset();
        $this->volverA('monto');
    }

    public function anular(AnnulCompra $annul, int $transaccionId): void
    {
        try {
            $motivo = config('cajero.annul_reason');
            $annul($this->cajero(), $transaccionId, is_string($motivo) ? $motivo : '');
        } catch (AuthorizationException) {
            $this->nuevaCompra();
            $this->aviso = 'Esta compra ya no se puede anular desde la caja. Pídele al administrador.';

            return;
        } catch (ValidationException $e) {
            $this->nuevaCompra();
            $this->aviso = $e->validator->errors()->first();

            return;
        }

        $this->nuevaCompra();
        $this->aviso = 'Compra anulada.';
    }

    public function render(ProximoNivel $proximo, QrRenderer $renderer): View
    {
        $cajero = $this->cajero()->load('sede.comercio');
        $cliente = $this->clienteId === null ? null : Cliente::query()->find($this->clienteId);

        return view('livewire.cajero.caja', [
            'cajero' => $cajero,
            'sede' => $cajero->sede,
            'cliente' => $cliente,
            'siguiente' => $cliente === null ? null : $proximo->para($cliente),
            'ultima' => $this->paso === 'monto' ? AnulacionCajero::ultima($cajero) : null,
            'qr' => $this->paso === 'registro_qr' && $this->enlace !== null ? $renderer->svg($this->enlace) : null,
            'maxDigitos' => self::setting('max_amount_digits'),
            'segundos' => self::setting('auto_return_seconds'),
        ]);
    }

    /**
     * Registra la compra en el comercio y la sede del cajero. Un error de
     * negocio (comercio inactivo, sede cerrada) se muestra sin perder el monto.
     */
    private function registrarCompra(RegisterCompra $register): ?RegistroCompra
    {
        $cajero = $this->cajero();

        if ($this->clienteId !== null) {
            $this->form->usar(Cliente::query()->findOrFail($this->clienteId));
        }

        try {
            return $register($cajero, new NuevaCompra(
                (int) $cajero->comercio_id,
                $cajero->sede_id,
                $this->form->tipo(),
                $this->form->documento,
                $this->monto,
                $this->referencia,
                CanalCompra::Cajero,
                $this->referencia,
            ));
        } catch (ValidationException $e) {
            $this->addError('registro', $e->validator->errors()->first());

            return null;
        }
    }

    private function cajero(): User
    {
        $user = Auth::guard('web')->user();

        if (! $user instanceof User) {
            throw new AuthorizationException('La caja necesita un cajero con sesión.');
        }

        return $user;
    }

    private static function setting(string $key): int
    {
        $value = config("cajero.{$key}");

        return is_int($value) ? $value : 0;
    }
}
