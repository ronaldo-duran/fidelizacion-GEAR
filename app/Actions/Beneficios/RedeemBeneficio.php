<?php

declare(strict_types=1);

namespace App\Actions\Beneficios;

use App\Beneficios\ChequeoBeneficio;
use App\Beneficios\EntregaBeneficio;
use App\Beneficios\EntregaRechazada;
use App\Beneficios\RevisionBeneficio;
use App\Beneficios\ValorOtorgado;
use App\Enums\ResultadoBeneficio;
use App\Enums\TipoBeneficio;
use App\Models\Beneficio;
use App\Models\Cliente;
use App\Models\CredencialQr;
use App\Models\Redencion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Confirmar entrega" (E5-02, regla 7). En una sola transacción:
 *
 * 1. bloquea la fila del beneficio, que ordena las entregas que compiten por
 *    el último cupo global o el último uso de la ventana del cliente;
 * 2. gasta el código, si lo hay, con un UPDATE condicionado (E4-04);
 * 3. vuelve a calcular el estado del beneficio con los conteos de ese
 *    momento;
 * 4. inserta la redención con su valor otorgado (regla dura 3.11).
 *
 * Si algo falla, nada queda escrito y el código sigue sin gastar. No toca los
 * puntos (regla dura 3.1). La referencia del intento tiene un índice único:
 * repetirla devuelve la redención ya hecha.
 */
class RedeemBeneficio
{
    public const MONTO = 'Escribe el monto de la cuenta.';

    public const SIN_VALOR = 'Con ese monto el beneficio no da descuento. Revisa el monto de la cuenta.';

    public const SOPORTE = 'Escribe el número del soporte: contrato o factura.';

    private readonly RevisionBeneficio $revision;

    public function __construct(RevisionBeneficio $revision)
    {
        $this->revision = $revision;
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws EntregaRechazada
     */
    public function __invoke(User $cajero, EntregaBeneficio $entrega): Redencion
    {
        $this->authorize($cajero);

        $repetida = $this->repetida($entrega->referencia, $cajero);

        if ($repetida !== null) {
            return $repetida;
        }

        try {
            return DB::transaction(fn (): Redencion => $this->entregar($cajero, $entrega));
        } catch (UniqueConstraintViolationException) {
            return $this->repetida($entrega->referencia, $cajero)
                ?? throw new AuthorizationException('La referencia de la entrega es de otro comercio.');
        }
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    private function entregar(User $cajero, EntregaBeneficio $entrega): Redencion
    {
        $query = Beneficio::query()->whereKey($entrega->beneficioId);
        $query->getQuery()->lockForUpdate();
        $beneficio = $query->first();

        if ($beneficio === null || $beneficio->comercio_id !== $cajero->comercio_id) {
            throw new AuthorizationException('El beneficio no es de este comercio.');
        }

        $soporte = $this->soporte($beneficio, $entrega->soporte);
        $this->validateMonto($beneficio, $entrega->montoCuenta);

        if ($entrega->credencialId !== null) {
            $this->gastar($cajero, $entrega, $beneficio);
        }

        $chequeo = $this->revision->revisar(Cliente::query()->findOrFail($entrega->clienteId), $beneficio->id);

        if (! $chequeo->esValido() || $chequeo->estado?->valor === null) {
            throw new EntregaRechazada($chequeo);
        }

        $monto = $beneficio->tipo === TipoBeneficio::Porcentaje ? $entrega->montoCuenta : null;
        $valor = ValorOtorgado::calcular($beneficio->tipo, $chequeo->estado->valor, $monto);

        if ($valor < 1) {
            throw ValidationException::withMessages(['monto_cuenta' => self::SIN_VALOR]);
        }

        return Redencion::query()->create([
            'cliente_id' => $entrega->clienteId,
            'beneficio_id' => $beneficio->id,
            'comercio_id' => $beneficio->comercio_id,
            'sede_id' => $cajero->sede_id,
            'valor_otorgado_cop' => $valor,
            'validado_por' => $cajero->id,
            'referencia' => $entrega->referencia,
            'credencial_qr_id' => $entrega->credencialId,
            'monto_cuenta_cop' => $monto,
            'soporte' => $soporte,
        ]);
    }

    /**
     * El código se validó vigente al escanearlo; se puede confirmar hasta N
     * segundos después de que venció (regla 11). Se gasta solo si nadie lo
     * gastó antes: de dos confirmaciones, la segunda ve "ya usado".
     *
     * @throws EntregaRechazada
     */
    private function gastar(User $cajero, EntregaBeneficio $entrega, Beneficio $beneficio): void
    {
        $credencial = CredencialQr::query()->find($entrega->credencialId);

        $esDeEstaEntrega = $credencial !== null
            && $credencial->cliente_id === $entrega->clienteId
            && $credencial->beneficio_id === $beneficio->id;

        if (! $esDeEstaEntrega) {
            throw new EntregaRechazada(ChequeoBeneficio::rechazado(ResultadoBeneficio::Invalido, $beneficio));
        }

        $now = CarbonImmutable::now();
        $limite = $credencial->vence_at->addSeconds(self::setting('cajero.redeem_confirm_window_seconds'));

        if ($credencial->usado_at === null && $now->gt($limite)) {
            throw new EntregaRechazada(ChequeoBeneficio::rechazado(ResultadoBeneficio::Vencido, $beneficio));
        }

        $gastada = CredencialQr::query()->toBase()
            ->where('id', $credencial->id)
            ->whereNull('usado_at')
            ->update(['usado_at' => $now, 'usado_por' => $cajero->id]);

        if ($gastada !== 1) {
            throw new EntregaRechazada($this->revision->usada($credencial->refresh()));
        }
    }

    /**
     * @throws ValidationException
     */
    private function validateMonto(Beneficio $beneficio, ?int $monto): void
    {
        $maximo = 10 ** self::setting('cajero.max_amount_digits') - 1;
        $valido = $monto !== null && $monto >= 1 && $monto <= $maximo;

        if ($beneficio->tipo === TipoBeneficio::Porcentaje && ! $valido) {
            throw ValidationException::withMessages(['monto_cuenta' => self::MONTO]);
        }
    }

    /**
     * La referencia del soporte, sin espacios a los lados, solo si el
     * beneficio la exige (regla 6).
     *
     * @throws ValidationException
     */
    private function soporte(Beneficio $beneficio, ?string $soporte): ?string
    {
        if (! $beneficio->requiere_soporte) {
            return null;
        }

        $soporte = trim((string) $soporte);
        $maximo = self::setting('cajero.support_max_length');

        if ($soporte === '' || mb_strlen($soporte) > $maximo) {
            throw ValidationException::withMessages([
                'soporte' => $soporte === '' ? self::SOPORTE : "El soporte no puede pasar de {$maximo} caracteres.",
            ]);
        }

        return $soporte;
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    private function authorize(User $cajero): void
    {
        if (! $cajero->puedeEntrarALaCaja()) {
            throw new AuthorizationException('Solo un cajero activo con sede entrega beneficios.');
        }

        $sede = $cajero->sede;

        if ($sede === null || ! $sede->activa || $sede->comercio_id !== $cajero->comercio_id) {
            throw ValidationException::withMessages(['entrega' => 'La sede de esta caja no está activa.']);
        }
    }

    private function repetida(string $referencia, User $cajero): ?Redencion
    {
        return Redencion::query()
            ->where('referencia', $referencia)
            ->where('comercio_id', $cajero->comercio_id)
            ->first();
    }

    private static function setting(string $key): int
    {
        $value = config($key);

        return is_int($value) ? $value : 0;
    }
}
