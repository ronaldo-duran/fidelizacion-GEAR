<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoMovimiento;
use App\Puntos\MovimientoShape;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Immutable points ledger entry (hard rule 3.4). Rows are written only by the
 * actions in `App\Actions\Puntos`; the model rejects updates, deletes and any
 * movement whose sign or fields do not match its type. The guard lives in
 * Eloquent events, so it is defense in depth, not a database guarantee.
 *
 * @property int $id
 * @property int $cliente_id
 * @property int|null $comercio_id
 * @property int|null $transaccion_id
 * @property TipoMovimiento $tipo
 * @property int $puntos
 * @property int|null $regla_id
 * @property int|null $regla_version
 * @property CarbonImmutable|null $vigencia_hasta
 * @property string|null $motivo
 * @property int|null $usuario_id
 * @property CarbonImmutable|null $created_at
 */
class MovimientoPuntos extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'movimientos_puntos';

    /** @var list<string> */
    protected $fillable = [
        'cliente_id',
        'comercio_id',
        'transaccion_id',
        'tipo',
        'puntos',
        'regla_id',
        'regla_version',
        'vigencia_hasta',
        'motivo',
        'usuario_id',
    ];

    protected static function booted(): void
    {
        static::creating(static function (MovimientoPuntos $movimiento): void {
            $errors = app(MovimientoShape::class)->errors($movimiento);

            if ($errors !== []) {
                throw new LogicException('Invalid points movement: ' . implode(' ', $errors));
            }
        });

        static::updating(static function (): void {
            throw new LogicException('Points movements are never updated: insert a new movement instead.');
        });

        static::deleting(static function (): void {
            throw new LogicException('Points movements are never deleted: insert a reversal or an adjustment.');
        });
    }

    /**
     * @return BelongsTo<Cliente, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoMovimiento::class,
            'puntos' => 'integer',
            'cliente_id' => 'integer',
            'comercio_id' => 'integer',
            'transaccion_id' => 'integer',
            'regla_id' => 'integer',
            'regla_version' => 'integer',
            'usuario_id' => 'integer',
            'vigencia_hasta' => 'immutable_date',
            'created_at' => 'immutable_datetime',
        ];
    }
}
