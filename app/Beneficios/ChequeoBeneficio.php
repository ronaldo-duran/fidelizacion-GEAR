<?php

declare(strict_types=1);

namespace App\Beneficios;

use App\Enums\ResultadoBeneficio;
use App\Models\Beneficio;
use App\Models\Cliente;
use App\Models\Redencion;
use Carbon\CarbonImmutable;

/**
 * El resultado de validar un beneficio en caja (E5-02, regla 4). Solo trae
 * los datos de su pantalla:
 *
 * - VÁLIDO: el cliente, el estado del beneficio con el valor de su nivel y,
 *   si llegó por código, la credencial que la entrega va a gastar.
 * - YA USADO: el beneficio, el último uso (con su comercio y sede) y cuándo
 *   vuelve.
 * - NO VÁLIDO: el motivo y, si es de un día, la próxima fecha.
 */
final class ChequeoBeneficio
{
    public readonly ResultadoBeneficio $resultado;

    public readonly ?Beneficio $beneficio;

    public readonly ?Cliente $cliente;

    public readonly ?BeneficioParaCliente $estado;

    public readonly ?Redencion $ultimoUso;

    public readonly ?CarbonImmutable $vuelve;

    public readonly ?CarbonImmutable $proximaFecha;

    public readonly ?int $credencialId;

    private function __construct(
        ResultadoBeneficio $resultado,
        ?Beneficio $beneficio = null,
        ?Cliente $cliente = null,
        ?BeneficioParaCliente $estado = null,
        ?Redencion $ultimoUso = null,
        ?CarbonImmutable $vuelve = null,
        ?CarbonImmutable $proximaFecha = null,
        ?int $credencialId = null,
    ) {
        $this->resultado = $resultado;
        $this->beneficio = $beneficio;
        $this->cliente = $cliente;
        $this->estado = $estado;
        $this->ultimoUso = $ultimoUso;
        $this->vuelve = $vuelve;
        $this->proximaFecha = $proximaFecha;
        $this->credencialId = $credencialId;
    }

    public static function valido(Cliente $cliente, BeneficioParaCliente $estado, ?int $credencialId = null): self
    {
        return new self(ResultadoBeneficio::Valido, $estado->beneficio, $cliente, $estado, credencialId: $credencialId);
    }

    public static function usado(?Beneficio $beneficio, ?Redencion $ultimoUso, ?CarbonImmutable $vuelve): self
    {
        return new self(ResultadoBeneficio::Usado, $beneficio, ultimoUso: $ultimoUso, vuelve: $vuelve);
    }

    public static function rechazado(
        ResultadoBeneficio $resultado,
        ?Beneficio $beneficio = null,
        ?CarbonImmutable $proximaFecha = null,
    ): self {
        return new self($resultado, $beneficio, proximaFecha: $proximaFecha);
    }

    public function esValido(): bool
    {
        return $this->resultado === ResultadoBeneficio::Valido;
    }

    /**
     * La pantalla del cajero: 'valido', 'usado' o 'no_valido'.
     */
    public function pantalla(): string
    {
        return match ($this->resultado) {
            ResultadoBeneficio::Valido => 'valido',
            ResultadoBeneficio::Usado => 'usado',
            default => 'no_valido',
        };
    }
}
