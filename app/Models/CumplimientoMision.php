<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoCumplimiento;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Lo que envía un cliente para cumplir una misión (E3-06). Nunca se edita ni
 * se borra: solo se revisa una vez, de pendiente a aprobado o rechazado.
 *
 * @property int $id
 * @property int $cliente_id
 * @property int $regla_id
 * @property int $regla_version
 * @property int $comercio_id
 * @property string|null $soporte_path
 * @property EstadoCumplimiento $estado
 * @property string|null $motivo_rechazo
 * @property int|null $revisado_por
 * @property CarbonImmutable|null $revisado_at
 * @property CarbonImmutable $created_at
 */
class CumplimientoMision extends Model
{
    private const REVIEW_FIELDS = ['estado', 'motivo_rechazo', 'revisado_por', 'revisado_at', 'updated_at'];

    protected $table = 'cumplimientos_mision';

    /** @var list<string> */
    protected $fillable = ['cliente_id', 'regla_id', 'regla_version', 'comercio_id', 'soporte_path', 'estado'];

    protected static function booted(): void
    {
        static::updating(static function (CumplimientoMision $cumplimiento): void {
            $reviewOnce = $cumplimiento->getOriginal('estado') === EstadoCumplimiento::Pendiente
                && $cumplimiento->estado !== EstadoCumplimiento::Pendiente
                && array_diff(array_keys($cumplimiento->getDirty()), self::REVIEW_FIELDS) === [];

            if (! $reviewOnce) {
                throw new LogicException('Mission submissions are only reviewed once, from pending.');
            }
        });

        static::deleting(static function (): void {
            throw new LogicException('Mission submissions are never deleted.');
        });
    }

    /**
     * @return BelongsTo<Regla, $this>
     */
    public function regla(): BelongsTo
    {
        return $this->belongsTo(Regla::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'regla_version' => 'integer',
            'estado' => EstadoCumplimiento::class,
            'revisado_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
