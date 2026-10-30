<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Una promoción abonada a un cliente (E3-06), append-only. Su restricción
 * única (cliente, código, ocurrencia) garantiza que no se abone dos veces
 * (regla dura 3.7).
 *
 * @property int $id
 * @property int $cliente_id
 * @property string $codigo
 * @property int $ocurrencia
 * @property int $movimiento_id
 * @property CarbonImmutable $created_at
 */
class PromocionOtorgada extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'promociones_otorgadas';

    /** @var list<string> */
    protected $fillable = ['cliente_id', 'codigo', 'ocurrencia', 'movimiento_id'];

    protected static function booted(): void
    {
        static::updating(static function (): void {
            throw new LogicException('Granted promotions are never updated.');
        });

        static::deleting(static function (): void {
            throw new LogicException('Granted promotions are never deleted.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ocurrencia' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}
