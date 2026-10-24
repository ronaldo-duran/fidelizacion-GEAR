<?php

declare(strict_types=1);

namespace App\Credenciales;

use App\Models\CredencialQr;
use Carbon\CarbonImmutable;

/**
 * What the customer's app shows: the signed token behind the QR, its short
 * code and when it expires.
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
