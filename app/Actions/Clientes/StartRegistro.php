<?php

declare(strict_types=1);

namespace App\Actions\Clientes;

use App\Actions\Verificacion\SendVerificationCode;
use App\Clientes\DatosRegistro;
use App\Enums\PropositoVerificacion;
use App\Models\Cliente;
use App\Models\RegistroPendiente;
use App\Verificacion\CodigoHasher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Primer paso del registro (E8-02): guarda los datos en un registro pendiente
 * que vence y pide el código por SMS (E8-06). No crea el cliente ni reserva
 * el documento; eso pasa al verificar (`CompleteRegistro`). Un intento nuevo
 * con el mismo documento reemplaza al anterior, salvo uno vigente con otro
 * celular empezado en otra sesión: así un tercero que conozca el documento no
 * le cambia el celular a quien se está registrando ni se queda con su cuenta.
 */
class StartRegistro
{
    public const DOCUMENTO_REGISTRADO = 'Ya hay una cuenta con ese documento. Ingresa o recupera tu PIN.';

    public const REGISTRO_EN_CURSO = 'Ya hay un registro en curso con ese documento. '
        . 'Termínalo en el celular donde lo empezaste o intenta más tarde.';

    private readonly SendVerificationCode $sendCode;

    public function __construct(SendVerificationCode $sendCode)
    {
        $this->sendCode = $sendCode;
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(DatosRegistro $datos, ?int $pendienteDeLaSesion = null): RegistroPendiente
    {
        $documento = Cliente::normalizeDocumento($datos->documento);
        $celular = CodigoHasher::normalizeCelular($datos->celular);
        $existe = Cliente::query()->toBase()
            ->where('tipo_documento', $datos->tipoDocumento->value)
            ->where('documento', $documento)
            ->exists();

        if ($existe) {
            throw ValidationException::withMessages(['documento' => self::DOCUMENTO_REGISTRADO]);
        }

        $enCurso = RegistroPendiente::query()
            ->where('tipo_documento', $datos->tipoDocumento->value)
            ->where('documento', $documento)
            ->first();
        $ajeno = $enCurso !== null
            && $enCurso->vence_at->isFuture()
            && $enCurso->celular !== $celular
            && $enCurso->id !== $pendienteDeLaSesion;

        if ($ajeno) {
            throw ValidationException::withMessages(['documento' => self::REGISTRO_EN_CURSO]);
        }

        $now = CarbonImmutable::now();
        $email = $datos->email === null || trim($datos->email) === '' ? null : mb_strtolower(trim($datos->email));

        // Upsert: el índice único (tipo, documento) hace que dos intentos
        // simultáneos dejen una sola fila, la del último.
        DB::table('registros_pendientes')->upsert([[
            'tipo_documento' => $datos->tipoDocumento->value,
            'documento' => $documento,
            'nombre_completo' => trim($datos->nombreCompleto),
            'fecha_nacimiento' => $datos->fechaNacimiento->toDateString(),
            'celular' => $celular,
            'email' => $email,
            'pin_hash' => Hash::make($datos->pin),
            'autorizacion_version' => self::autorizacionVersion(),
            'ip' => $datos->ip,
            'canal' => $datos->canal->value,
            'vence_at' => $now->addMinutes(self::minutes()),
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['tipo_documento', 'documento'], [
            'nombre_completo', 'fecha_nacimiento', 'celular', 'email', 'pin_hash', 'autorizacion_version', 'ip',
            'canal', 'vence_at', 'created_at', 'updated_at',
        ]);

        $pendiente = RegistroPendiente::query()
            ->where('tipo_documento', $datos->tipoDocumento->value)
            ->where('documento', $documento)
            ->sole();

        ($this->sendCode)(PropositoVerificacion::Registro, $celular, $datos->ip);

        return $pendiente;
    }

    private static function minutes(): int
    {
        $value = config('registro.registro_pendiente_minutes');

        return is_int($value) ? $value : 0;
    }

    private static function autorizacionVersion(): string
    {
        $value = config('registro.autorizacion_version');

        return is_string($value) ? $value : '';
    }
}
