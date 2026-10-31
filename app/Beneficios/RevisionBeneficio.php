<?php

declare(strict_types=1);

namespace App\Beneficios;

use App\Enums\EstadoBeneficio;
use App\Enums\ResultadoBeneficio;
use App\Models\Beneficio;
use App\Models\Cliente;
use App\Models\CredencialQr;
use App\Models\Redencion;

/**
 * Traduce el estado de un beneficio para un cliente (E5-01) a lo que ve el
 * cajero (E5-02, regla 4). La usan la validación del código, la lista por
 * documento y la entrega, que la llama otra vez después de bloquear el
 * beneficio para decidir con los conteos de ese momento.
 */
class RevisionBeneficio
{
    private readonly BeneficiosParaCliente $beneficios;

    public function __construct(BeneficiosParaCliente $beneficios)
    {
        $this->beneficios = $beneficios;
    }

    public function revisar(Cliente $cliente, int $beneficioId, ?int $credencialId = null): ChequeoBeneficio
    {
        $estado = $this->beneficios->deBeneficio($cliente, $beneficioId);

        if ($estado === null) {
            $beneficio = Beneficio::query()->find($beneficioId);

            return ChequeoBeneficio::rechazado(ResultadoBeneficio::NoDisponible, $beneficio);
        }

        $beneficio = $estado->beneficio;

        return match ($estado->estado) {
            EstadoBeneficio::Disponible => ChequeoBeneficio::valido($cliente, $estado, $credencialId),
            EstadoBeneficio::Usado => ChequeoBeneficio::usado(
                $beneficio,
                $this->ultimoUso($cliente->id, $beneficio->id),
                $estado->vuelve,
            ),
            EstadoBeneficio::SinCupos => ChequeoBeneficio::rechazado(ResultadoBeneficio::SinCupos, $beneficio),
            EstadoBeneficio::NoDisponibleHoy => ChequeoBeneficio::rechazado(
                ResultadoBeneficio::NoDisponibleHoy,
                $beneficio,
                $estado->proximaFecha,
            ),
            EstadoBeneficio::Bloqueado => ChequeoBeneficio::rechazado(
                ResultadoBeneficio::NivelInsuficiente,
                $beneficio,
            ),
        };
    }

    /**
     * Un código ya gastado: dónde se usó y, si el cliente agotó su límite,
     * cuándo vuelve.
     */
    public function usada(CredencialQr $credencial): ChequeoBeneficio
    {
        $redencion = Redencion::query()
            ->with(['comercio', 'sede'])
            ->where('credencial_qr_id', $credencial->id)
            ->first();
        $cliente = Cliente::query()->find($credencial->cliente_id);
        $estado = $cliente === null || $credencial->beneficio_id === null
            ? null
            : $this->beneficios->deBeneficio($cliente, $credencial->beneficio_id);
        $beneficio = $estado->beneficio ?? Beneficio::query()->find($credencial->beneficio_id);

        return ChequeoBeneficio::usado(
            $beneficio,
            $redencion,
            $estado?->estado === EstadoBeneficio::Usado ? $estado->vuelve : null,
        );
    }

    private function ultimoUso(int $clienteId, int $beneficioId): ?Redencion
    {
        return Redencion::query()
            ->with(['comercio', 'sede'])
            ->where('cliente_id', $clienteId)
            ->where('beneficio_id', $beneficioId)
            ->latest('id')
            ->first();
    }
}
