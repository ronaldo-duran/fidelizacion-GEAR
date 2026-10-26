<?php

declare(strict_types=1);

namespace App\Actions\Clientes;

use App\Actions\Compras\CreditPendingCompras;
use App\Actions\Verificacion\VerifyCode;
use App\Enums\PropositoVerificacion;
use App\Models\AutorizacionDatos;
use App\Models\Cliente;
use App\Models\RegistroPendiente;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Segundo paso del registro (E8-02): con el código del SMS correcto, en una
 * sola transacción crea el cliente, guarda su autorización de datos, le
 * acredita las compras pendientes de su tipo + número (E4-01) y borra el
 * registro pendiente. Si algo falla, no queda nada.
 *
 * Un código equivocado no aborta la transacción: sus intentos fallidos deben
 * quedar contados (E8-06), así que el rechazo se lanza después de confirmarla.
 */
class CompleteRegistro
{
    public const CODIGO_INVALIDO = 'El código no es válido o ya venció. Revisa el SMS o pide otro.';

    public const REGISTRO_VENCIDO = 'Tu registro venció. Empieza de nuevo.';

    private readonly VerifyCode $verifyCode;

    private readonly CreditPendingCompras $creditCompras;

    public function __construct(VerifyCode $verifyCode, CreditPendingCompras $creditCompras)
    {
        $this->verifyCode = $verifyCode;
        $this->creditCompras = $creditCompras;
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(int $registroPendienteId, string $codigo): Cliente
    {
        $pendiente = RegistroPendiente::query()->find($registroPendienteId);

        if ($pendiente === null || $pendiente->vence_at->lt(CarbonImmutable::now())) {
            throw ValidationException::withMessages(['codigo' => self::REGISTRO_VENCIDO]);
        }

        try {
            $cliente = DB::transaction(function () use ($pendiente, $codigo): ?Cliente {
                if (! ($this->verifyCode)(PropositoVerificacion::Registro, $pendiente->celular, $codigo)) {
                    return null;
                }

                return $this->create($pendiente);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['codigo' => StartRegistro::DOCUMENTO_REGISTRADO]);
        }

        if ($cliente === null) {
            throw ValidationException::withMessages(['codigo' => self::CODIGO_INVALIDO]);
        }

        return $cliente;
    }

    private function create(RegistroPendiente $pendiente): Cliente
    {
        $now = CarbonImmutable::now();
        $cliente = new Cliente([
            'tipo_documento' => $pendiente->tipo_documento,
            'documento' => $pendiente->documento,
            'fecha_nacimiento' => $pendiente->fecha_nacimiento,
            'celular' => $pendiente->celular,
            'email' => $pendiente->email,
        ]);
        $cliente->forceFill([
            'nombre_completo' => $pendiente->nombre_completo,
            'pin_hash' => $pendiente->pin_hash,
            'celular_verificado_at' => $now,
        ])->save();

        AutorizacionDatos::query()->create([
            'cliente_id' => $cliente->id,
            'version_texto' => $pendiente->autorizacion_version,
            'aceptada_at' => $pendiente->created_at,
            'ip' => $pendiente->ip,
            'canal' => $pendiente->canal,
        ]);

        ($this->creditCompras)($cliente->id);

        // TODO(E3-06): abonar el bono de registro con `GrantBonoRegistro` cuando exista.

        $pendiente->delete();

        return $cliente->refresh();
    }
}
