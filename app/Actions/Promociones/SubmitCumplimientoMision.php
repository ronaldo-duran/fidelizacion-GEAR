<?php

declare(strict_types=1);

namespace App\Actions\Promociones;

use App\Enums\EstadoCumplimiento;
use App\Enums\TipoRegla;
use App\Models\CumplimientoMision;
use App\Models\PromocionOtorgada;
use App\Models\Regla;
use App\Puntos\Ledger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * El cliente envía el cumplimiento de una misión vigente (E3-06). Queda
 * pendiente con la versión de la misión de ese momento; no abona nada hasta
 * que el comercio lo apruebe.
 *
 * Un cliente tiene a lo sumo un cumplimiento pendiente por misión y no envía
 * más de los que la misión admite. Los envíos de un mismo cliente se
 * serializan bloqueando su fila, igual que los movimientos del libro.
 */
class SubmitCumplimientoMision
{
    /**
     * Carpeta del disco privado donde el servidor guarda los pantallazos. La
     * ruta nunca la elige el cliente: el comercio descarga lo que haya aquí.
     */
    private const SOPORTE_DIRECTORIO = 'cumplimientos/';

    private readonly Ledger $ledger;

    public function __construct(Ledger $ledger)
    {
        $this->ledger = $ledger;
    }

    /**
     * @param  string|null  $soportePath  el pantallazo que el servidor ya guardó en `cumplimientos/` del disco privado
     *
     * @throws ValidationException
     */
    public function __invoke(int $clienteId, string $codigo, ?string $soportePath): CumplimientoMision
    {
        $mision = Regla::inForceAt(CarbonImmutable::now())
            ->where('tipo', TipoRegla::Mision->value)
            ->where('codigo', mb_strtolower(trim($codigo)))
            ->first();

        if ($mision === null) {
            throw ValidationException::withMessages(['mision' => 'Esta misión ya no está disponible.']);
        }

        $soportePath = $this->soporte($mision, $soportePath);

        return DB::transaction(function () use ($clienteId, $mision, $soportePath): CumplimientoMision {
            $this->ledger->lockCliente($clienteId);
            $this->assertPuedeEnviar($clienteId, $mision);

            return CumplimientoMision::query()->create([
                'cliente_id' => $clienteId,
                'regla_id' => $mision->id,
                'regla_version' => $mision->version,
                'comercio_id' => $mision->definicion['comercio_id'],
                'soporte_path' => $soportePath,
                'estado' => EstadoCumplimiento::Pendiente,
            ]);
        });
    }

    /**
     * @throws ValidationException
     */
    private function soporte(Regla $mision, ?string $soportePath): ?string
    {
        $soportePath = trim((string) $soportePath);

        if ($soportePath === '' && $mision->definicion['requiere_soporte'] === true) {
            throw ValidationException::withMessages(['soporte' => 'Adjunta el pantallazo que pide la misión.']);
        }

        if ($soportePath === '') {
            return null;
        }

        $fueraDeLaCarpeta = ! str_starts_with($soportePath, self::SOPORTE_DIRECTORIO)
            || str_contains($soportePath, '..')
            || str_contains($soportePath, '\\');

        if ($fueraDeLaCarpeta) {
            throw ValidationException::withMessages([
                'soporte' => 'El soporte no es válido. Adjunta de nuevo el pantallazo.',
            ]);
        }

        return $soportePath;
    }

    /**
     * @throws ValidationException
     */
    private function assertPuedeEnviar(int $clienteId, Regla $mision): void
    {
        $versiones = Regla::query()->where('codigo', $mision->codigo)->toBase()->select('id');
        $pendiente = CumplimientoMision::query()
            ->where('cliente_id', $clienteId)
            ->where('estado', EstadoCumplimiento::Pendiente->value)
            ->toBase()
            ->whereIn('regla_id', $versiones)
            ->exists();

        if ($pendiente) {
            throw ValidationException::withMessages([
                'mision' => 'Ya enviaste esta misión y está en revisión. Espera la respuesta del comercio.',
            ]);
        }

        $otorgadas = PromocionOtorgada::query()
            ->where('cliente_id', $clienteId)
            ->where('codigo', $mision->codigo)
            ->toBase()
            ->count();

        if ($otorgadas >= $mision->definicion['max_por_cliente']) {
            throw ValidationException::withMessages([
                'mision' => 'Ya completaste esta misión todas las veces que se puede.',
            ]);
        }
    }
}
