<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Clientes\DatosRegistro;
use App\Clientes\ReglasCliente;
use App\Enums\CanalRegistro;
use App\Enums\TipoDocumento;
use App\Models\Cliente;
use App\Verificacion\CodigoHasher;
use Carbon\CarbonImmutable;
use Livewire\Form;

/**
 * El formulario de registro (E8-02, pantalla 1m) y sus reglas, que salen de
 * `ReglasCliente`. El documento y el celular se normalizan antes de validar.
 */
class RegistroForm extends Form
{
    public string $tipoDocumento = 'CC';

    public string $documento = '';

    public string $nombreCompleto = '';

    public string $fechaNacimiento = '';

    public string $celular = '';

    public string $email = '';

    public string $pin = '';

    public string $pinConfirmation = '';

    public bool $autorizacion = false;

    public function normalize(): void
    {
        $this->documento = Cliente::normalizeDocumento($this->documento);
        $this->celular = CodigoHasher::normalizeCelular($this->celular);
        $this->nombreCompleto = trim($this->nombreCompleto);
        $this->email = trim($this->email);
    }

    public function datos(CanalRegistro $canal, ?string $ip): DatosRegistro
    {
        return new DatosRegistro(
            TipoDocumento::from($this->tipoDocumento),
            $this->documento,
            $this->nombreCompleto,
            CarbonImmutable::parse($this->fechaNacimiento),
            $this->celular,
            $this->email === '' ? null : $this->email,
            $this->pin,
            $canal,
            $ip,
        );
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'tipoDocumento' => ReglasCliente::tipoDocumento(),
            'documento' => ReglasCliente::documento(TipoDocumento::tryFrom($this->tipoDocumento)),
            'nombreCompleto' => [
                'required',
                'string',
                'min:' . self::setting('nombre_min_length'),
                'max:' . self::setting('nombre_max_length'),
            ],
            'fechaNacimiento' => [
                'required',
                'date_format:Y-m-d',
                'after:1900-01-01',
                'before_or_equal:' . self::fechaLimite(),
            ],
            'celular' => ReglasCliente::celular(),
            'email' => ['nullable', 'email', 'max:255'],
            'pin' => ReglasCliente::pin(),
            'pinConfirmation' => ['same:pin'],
            'autorizacion' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...ReglasCliente::messages(TipoDocumento::tryFrom($this->tipoDocumento)),
            'nombreCompleto.required' => 'Escribe tu nombre completo.',
            'nombreCompleto.min' => 'Escribe tu nombre completo.',
            'nombreCompleto.max' => 'El nombre es demasiado largo.',
            'fechaNacimiento.required' => 'Escribe tu fecha de nacimiento.',
            'fechaNacimiento.date_format' => 'Revisa la fecha de nacimiento.',
            'fechaNacimiento.after' => 'Revisa la fecha de nacimiento.',
            'fechaNacimiento.before_or_equal' => 'Para unirte al Club debes ser mayor de edad.',
            'email.email' => 'Revisa el correo.',
            'email.max' => 'Revisa el correo.',
            'autorizacion.accepted' => 'Marca la autorización para continuar.',
        ];
    }

    /**
     * El último día de nacimiento que cumple la edad mínima.
     */
    private static function fechaLimite(): string
    {
        return CarbonImmutable::today()->subYears(self::setting('edad_minima_anios'))->toDateString();
    }

    private static function setting(string $key): int
    {
        $value = config("registro.{$key}");

        return is_int($value) ? $value : 0;
    }
}
