<?php

declare(strict_types=1);

namespace App\Promociones;

use App\Enums\EstadoCumplimiento;
use App\Enums\TipoRegla;
use App\Models\Comercio;
use App\Models\CumplimientoMision;
use App\Models\PromocionOtorgada;
use App\Models\Regla;
use Carbon\CarbonImmutable;

/**
 * Las misiones vigentes y activas de comercios activos, con su estado para un
 * cliente (E3-06, PWA). Lo que valida el envío sigue en
 * `SubmitCumplimientoMision`; esto solo arma la pantalla.
 *
 * Sin N+1: una consulta de misiones, una de comercios, una de promociones
 * otorgadas y una de cumplimientos, sin importar cuántas misiones haya.
 */
class MisionesParaCliente
{
    /**
     * @return list<MisionParaCliente>
     */
    public function para(int $clienteId): array
    {
        $query = Regla::inForceAt(CarbonImmutable::now())->where('tipo', TipoRegla::Mision->value);
        $query->getQuery()->orderBy('id');
        $misiones = $query->get();

        if ($misiones->isEmpty()) {
            return [];
        }

        $comercios = Comercio::query()
            ->where('activo', true)
            ->toBase()
            ->whereIn('id', $misiones->map(
                static fn (Regla $mision): int => MisionParaCliente::entero($mision, 'comercio_id'),
            ))
            ->pluck('razon_social', 'id')
            ->all();
        $codigos = $misiones->map(static fn (Regla $mision): string => $mision->codigo)->values()->all();
        $veces = PromocionOtorgada::query()
            ->where('cliente_id', $clienteId)
            ->toBase()
            ->whereIn('codigo', $codigos)
            ->groupBy('codigo')
            ->selectRaw('codigo, count(*) as total')
            ->pluck('total', 'codigo')
            ->all();
        $envios = $this->ultimosEnvios($clienteId, $codigos);

        $resultado = [];

        foreach ($misiones as $mision) {
            $comercio = $comercios[MisionParaCliente::entero($mision, 'comercio_id')] ?? null;

            if (! is_string($comercio)) {
                continue;
            }

            $total = $veces[$mision->codigo] ?? 0;
            $resultado[] = $this->estado($mision, $comercio, is_numeric($total) ? (int) $total : 0, $envios);
        }

        return $resultado;
    }

    /**
     * @param  array<string, array{pendiente: bool, motivo: string|null}>  $envios
     */
    private function estado(Regla $mision, string $comercio, int $veces, array $envios): MisionParaCliente
    {
        $envio = $envios[$mision->codigo] ?? ['pendiente' => false, 'motivo' => null];

        $estado = match (true) {
            $envio['pendiente'] => MisionParaCliente::EN_REVISION,
            $veces >= MisionParaCliente::entero($mision, 'max_por_cliente') => MisionParaCliente::COMPLETADA,
            default => MisionParaCliente::DISPONIBLE,
        };

        $motivo = $estado === MisionParaCliente::DISPONIBLE ? $envio['motivo'] : null;

        return new MisionParaCliente($mision, $comercio, $estado, $veces, $motivo);
    }

    /**
     * Por código de misión: si hay un envío pendiente y, si el último envío
     * fue rechazado, su motivo. Cuenta los envíos de todas las versiones.
     *
     * @param  array<int, string>  $codigos
     * @return array<string, array{pendiente: bool, motivo: string|null}>
     */
    private function ultimosEnvios(int $clienteId, array $codigos): array
    {
        $filas = CumplimientoMision::query()
            ->toBase()
            ->join('reglas', 'reglas.id', '=', 'cumplimientos_mision.regla_id')
            ->where('cumplimientos_mision.cliente_id', $clienteId)
            ->whereIn('reglas.codigo', $codigos)
            ->orderByDesc('cumplimientos_mision.id')
            ->get(['reglas.codigo', 'cumplimientos_mision.estado', 'cumplimientos_mision.motivo_rechazo']);

        $envios = [];

        foreach ($filas as $fila) {
            $codigo = is_string($fila->codigo) ? $fila->codigo : '';
            $motivo = is_string($fila->motivo_rechazo) ? $fila->motivo_rechazo : null;
            $pendiente = $fila->estado === EstadoCumplimiento::Pendiente->value;

            if (! isset($envios[$codigo])) {
                $rechazado = $fila->estado === EstadoCumplimiento::Rechazado->value;
                $envios[$codigo] = ['pendiente' => $pendiente, 'motivo' => $rechazado ? $motivo : null];

                continue;
            }

            $envios[$codigo]['pendiente'] = $envios[$codigo]['pendiente'] || $pendiente;
        }

        return $envios;
    }
}
