<?php

declare(strict_types=1);

namespace App\Livewire\Cajero;

use App\Actions\Beneficios\CheckBeneficioCredencial;
use App\Actions\Beneficios\RedeemBeneficio;
use App\Actions\Cajeros\FindCliente;
use App\Beneficios\BeneficiosParaCliente;
use App\Beneficios\ChequeoBeneficio;
use App\Beneficios\EntregaBeneficio;
use App\Beneficios\EntregaRechazada;
use App\Beneficios\ResultadoEnCaja;
use App\Beneficios\RevisionBeneficio;
use App\Livewire\Forms\IdentificarForm;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * "Validar beneficio" de la caja (E5-02): escanear o digitar el código del
 * cliente, o buscarlo por documento, ver el resultado a pantalla completa y
 * confirmar la entrega. Toda la lógica vive en las Actions; aquí solo se
 * maneja el paso de la pantalla.
 *
 * Pasos: escaner → resultado → (datos) → entregado, o escaner → documento →
 * lista → resultado.
 */
#[Layout('components.layouts.cajero')]
#[Title('Validar beneficio')]
class ValidarBeneficio extends Component
{
    #[Locked]
    public string $paso = 'escaner';

    /** Si el panel para digitar el código está abierto. */
    public bool $digitar = false;

    public string $codigo = '';

    public IdentificarForm $form;

    /** El cliente encontrado por documento. */
    #[Locked]
    public ?int $clienteId = null;

    /** @var array<string, mixed> el resultado de la validación, en datos simples */
    #[Locked]
    public array $resultado = [];

    /** Referencia de este intento de entrega: repetirla devuelve la misma redención. */
    #[Locked]
    public string $referencia = '';

    public string $soporte = '';

    #[Locked]
    public ?int $valorEntregado = null;

    /**
     * Lo que leyó la cámara o, sin nada, el código digitado (E4-04).
     */
    public function validarCodigo(CheckBeneficioCredencial $check, ?string $entrada = null): void
    {
        if ($this->paso !== 'escaner') {
            return;
        }

        $this->mostrar($check($this->cajero(), $entrada ?? $this->codigo));
    }

    public function porDocumento(): void
    {
        $this->reiniciar();
        $this->paso = 'documento';
    }

    public function buscar(FindCliente $find): void
    {
        if ($this->paso !== 'documento') {
            return;
        }

        $this->form->validarDocumento();
        $cliente = $find($this->cajero(), $this->form->tipo(), $this->form->documento);

        if ($cliente === null) {
            $this->addError('form.documento', 'No hay un cliente con ese documento.');

            return;
        }

        $this->clienteId = $cliente->id;
        $this->paso = 'lista';
    }

    public function elegir(RevisionBeneficio $revision, int $beneficioId): void
    {
        $cliente = $this->form->cliente($this->clienteId);

        if ($this->paso !== 'lista' || $cliente === null) {
            return;
        }

        $chequeo = $revision->revisar($cliente, $beneficioId);

        if ($chequeo->beneficio?->comercio_id === $this->cajero()->comercio_id) {
            $this->mostrar($chequeo);
        }
    }

    /**
     * "Confirmar entrega" en VÁLIDO: pide el monto de la cuenta o el soporte
     * si el beneficio los necesita; si no, entrega de una vez.
     */
    public function confirmar(RedeemBeneficio $redeem): void
    {
        if ($this->paso !== 'resultado' || ($this->resultado['pantalla'] ?? null) !== 'valido') {
            return;
        }

        $vista = ResultadoEnCaja::vista($this->resultado);

        if ($vista['pideMonto'] === true || $vista['pideSoporte'] === true) {
            $this->paso = 'datos';

            return;
        }

        $this->entregar($redeem);
    }

    public function entregar(RedeemBeneficio $redeem, ?int $monto = null): void
    {
        $valido = ($this->resultado['pantalla'] ?? null) === 'valido';

        if (! $valido || ! in_array($this->paso, ['resultado', 'datos'], true)) {
            return;
        }

        try {
            $redencion = $redeem($this->cajero(), new EntregaBeneficio(
                is_int($this->resultado['cliente_id'] ?? null) ? $this->resultado['cliente_id'] : 0,
                is_int($this->resultado['beneficio_id'] ?? null) ? $this->resultado['beneficio_id'] : 0,
                is_int($this->resultado['credencial_id'] ?? null) ? $this->resultado['credencial_id'] : null,
                $monto,
                $this->soporte,
                $this->referencia,
            ));
        } catch (EntregaRechazada $rechazo) {
            $this->mostrar($rechazo->chequeo);

            return;
        } catch (AuthorizationException) {
            $this->reiniciar();
            $this->addError('codigo', 'Este beneficio no se puede entregar en esta caja.');

            return;
        }

        $this->valorEntregado = $redencion->valor_otorgado_cop;
        $this->paso = 'entregado';
    }

    /**
     * "Cancelar", "Escanear otro" o la vuelta sola tras la entrega: no gasta nada.
     */
    public function otro(): void
    {
        $this->reiniciar();
    }

    public function render(BeneficiosParaCliente $beneficios): View
    {
        $cajero = $this->cajero()->load('sede.comercio');
        $cliente = $this->paso === 'lista' ? $this->form->cliente($this->clienteId) : null;

        return view('livewire.cajero.validar-beneficio', [
            'cajero' => $cajero,
            'sede' => $cajero->sede,
            'clienteLista' => $cliente,
            'lista' => $cliente === null ? [] : $beneficios->delComercio($cliente, (int) $cajero->comercio_id),
            'vista' => $this->resultado === [] ? null : ResultadoEnCaja::vista($this->resultado),
            'maxDigitos' => self::setting('max_amount_digits'),
            'segundos' => self::setting('auto_return_seconds'),
        ]);
    }

    private function mostrar(ChequeoBeneficio $chequeo): void
    {
        $this->resultado = ResultadoEnCaja::guardar($chequeo);
        $this->referencia = (string) Str::uuid();
        $this->soporte = '';
        $this->paso = 'resultado';
        $this->resetErrorBag();
    }

    private function reiniciar(): void
    {
        $this->reset('digitar', 'codigo', 'clienteId', 'resultado', 'referencia', 'soporte', 'valorEntregado');
        $this->form->reset();
        $this->paso = 'escaner';
        $this->resetErrorBag();
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
