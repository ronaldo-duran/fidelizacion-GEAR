<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoEventoCalificado;
use App\Observers\AuditoriaObserver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * A qualifying event of a customer. It is never edited nor deleted: the only
 * change allowed is closing it once, which brings `vigente_hasta` forward
 * with a reason. The audit observer keeps the before and after.
 *
 * @property int $id
 * @property int $cliente_id
 * @property int $comercio_id
 * @property TipoEventoCalificado $tipo
 * @property CarbonImmutable $vigente_desde
 * @property CarbonImmutable $vigente_hasta
 * @property string $soporte_path
 * @property string|null $motivo_cierre
 * @property int $registrado_por
 * @property int|null $cerrado_por
 */
#[ObservedBy(AuditoriaObserver::class)]
class EventoCalificado extends Model
{
    private const CLOSING_FIELDS = ['vigente_hasta', 'motivo_cierre', 'cerrado_por', 'updated_at'];

    protected $table = 'eventos_calificados';

    /** @var list<string> */
    protected $fillable = [
        'cliente_id',
        'comercio_id',
        'tipo',
        'vigente_desde',
        'vigente_hasta',
        'soporte_path',
        'registrado_por',
    ];

    protected static function booted(): void
    {
        static::updating(static function (EventoCalificado $evento): void {
            $onlyCloses = array_diff(array_keys($evento->getDirty()), self::CLOSING_FIELDS) === []
                && $evento->getOriginal('motivo_cierre') === null
                && $evento->motivo_cierre !== null;

            if (! $onlyCloses) {
                throw new LogicException('Qualifying events are never edited: they can only be closed once.');
            }
        });

        static::deleting(static function (): void {
            throw new LogicException('Qualifying events are never deleted: close them instead.');
        });
    }

    /**
     * Valid on that day: end dates count the whole day.
     */
    public function isVigenteOn(CarbonImmutable $day): bool
    {
        return $this->vigente_desde->lte($day) && $this->vigente_hasta->gte($day);
    }

    /**
     * @return BelongsTo<Cliente, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * @return BelongsTo<Comercio, $this>
     */
    public function comercio(): BelongsTo
    {
        return $this->belongsTo(Comercio::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoEventoCalificado::class,
            'vigente_desde' => 'immutable_date',
            'vigente_hasta' => 'immutable_date',
        ];
    }
}
