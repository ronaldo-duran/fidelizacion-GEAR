<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * La redención de un beneficio, append-only. Guarda el valor en pesos que se
 * entregó (regla dura 3.11). E5-01 solo la lee; la escribe la Action de E5-02.
 *
 * @property int $id
 * @property int $cliente_id
 * @property int $beneficio_id
 * @property int $comercio_id
 * @property int|null $sede_id
 * @property int $valor_otorgado_cop
 * @property int $validado_por
 * @property CarbonImmutable $created_at
 */
class Redencion extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'redenciones';

    /** @var list<string> */
    protected $fillable = [
        'cliente_id',
        'beneficio_id',
        'comercio_id',
        'sede_id',
        'valor_otorgado_cop',
        'validado_por',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): void {
            throw new LogicException('Redemptions are never updated.');
        });

        static::deleting(static function (): void {
            throw new LogicException('Redemptions are never deleted.');
        });
    }

    /**
     * @return BelongsTo<Beneficio, $this>
     */
    public function beneficio(): BelongsTo
    {
        return $this->belongsTo(Beneficio::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor_otorgado_cop' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}
