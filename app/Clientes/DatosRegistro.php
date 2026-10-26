<?php

declare(strict_types=1);

namespace App\Clientes;

use App\Enums\CanalRegistro;
use App\Enums\TipoDocumento;
use Carbon\CarbonImmutable;
use SensitiveParameter;

/**
 * Los datos ya validados del formulario de registro (E8-02), como los recibe
 * `StartRegistro`. El PIN llega en claro solo hasta calcular su hash.
 */
final readonly class DatosRegistro
{
    public TipoDocumento $tipoDocumento;

    public string $documento;

    public string $nombreCompleto;

    public CarbonImmutable $fechaNacimiento;

    public string $celular;

    public ?string $email;

    public string $pin;

    public CanalRegistro $canal;

    public ?string $ip;

    public function __construct(
        TipoDocumento $tipoDocumento,
        string $documento,
        string $nombreCompleto,
        CarbonImmutable $fechaNacimiento,
        string $celular,
        ?string $email,
        #[SensitiveParameter]
        string $pin,
        CanalRegistro $canal,
        ?string $ip,
    ) {
        $this->tipoDocumento = $tipoDocumento;
        $this->documento = $documento;
        $this->nombreCompleto = $nombreCompleto;
        $this->fechaNacimiento = $fechaNacimiento;
        $this->celular = $celular;
        $this->email = $email;
        $this->pin = $pin;
        $this->canal = $canal;
        $this->ip = $ip;
    }
}
