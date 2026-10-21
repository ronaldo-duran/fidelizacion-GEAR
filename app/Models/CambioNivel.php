<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MotivoCambioNivel;
use App\Enums\Nivel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * One change of a customer's effective level. Append-only.
 *
 * @property int $id
 * @property int $cliente_id
 * @property Nivel $nivel_anterior
 * @property Nivel $nivel_nuevo
 * @property MotivoCambioNivel $motivo
 * @property CarbonImmutable $created_at
 */
class CambioNivel extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'cambios_nivel';

    /** @var list<string> */
    protected $fillable = ['cliente_id', 'nivel_anterior', 'nivel_nuevo', 'motivo'];

    protected static function booted(): void
    {
        static::updating(static function (): void {
            throw new LogicException('Level changes are never updated: record a new change instead.');
        });

        static::deleting(static function (): void {
            throw new LogicException('Level changes are never deleted.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nivel_anterior' => Nivel::class,
            'nivel_nuevo' => Nivel::class,
            'motivo' => MotivoCambioNivel::class,
            'created_at' => 'immutable_datetime',
        ];
    }
}
