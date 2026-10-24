<?php

declare(strict_types=1);

namespace App\Credenciales;

use App\Models\CredencialQr;
use Carbon\CarbonImmutable;

/**
 * Lo que muestra la app del cliente: el token firmado detrás del QR, su código
 * corto y cuándo vence.
 */
final class CredencialEmitida
{
    public readonly CredencialQr $credencial;

    public readonly string $token;

    public readonly string $codigoCorto;

    public readonly CarbonImmutable $venceAt;

    public function __construct(CredencialQr $credencial, string $token)
    {
        $this->credencial = $credencial;
        $this->token = $token;
        $this->codigoCorto = $credencial->codigo_corto;
        $this->venceAt = $credencial->vence_at;
    }
}
