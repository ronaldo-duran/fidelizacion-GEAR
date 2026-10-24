<?php

declare(strict_types=1);

namespace App\Credenciales;

use App\Enums\ResultadoCredencial;
use App\Models\Cliente;
use App\Models\CredencialQr;

/**
 * Resultado de una validación del cajero. El cliente y la credencial solo
 * vienen cuando es válida, así un código inválido nunca revela datos del
 * cliente.
 */
final class ValidacionCredencial
{
    public readonly ResultadoCredencial $resultado;

    public readonly ?Cliente $cliente;

    public readonly ?CredencialQr $credencial;

    private function __construct(ResultadoCredencial $resultado, ?Cliente $cliente, ?CredencialQr $credencial)
    {
        $this->resultado = $resultado;
        $this->cliente = $cliente;
        $this->credencial = $credencial;
    }

    public static function valida(Cliente $cliente, CredencialQr $credencial): self
    {
        return new self(ResultadoCredencial::Valido, $cliente, $credencial);
    }

    public static function rechazada(ResultadoCredencial $resultado): self
    {
        return new self($resultado, null, null);
    }

    public function esValida(): bool
    {
        return $this->resultado === ResultadoCredencial::Valido;
    }
}
