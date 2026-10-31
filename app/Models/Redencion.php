<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * La redención de un beneficio, append-only. Guarda el valor en pesos que se
 * entregó (regla dura 3.11). La escribe `RedeemBeneficio` (E5-02); E5-01 la
 * lee para los usos por ventana y el cupo global.
 *
 * @property int $id
 * @property int $cliente_id
 * @property int $beneficio_id
 * @property int $comercio_id
 * @property int|null $sede_id
 * @property int $valor_otorgado_cop
 * @property int $validado_por
 * @property string $referencia
 * @property int|null $credencial_qr_id
 * @property int|null $monto_cuenta_cop
 * @property string|null $soporte
 * @property CarbonImmutable $created_at
 * @property-read Comercio $comercio
 * @property-read Sede|null $sede
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
        'referencia',
        'credencial_qr_id',
        'monto_cuenta_cop',
        'soporte',
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
     * @return BelongsTo<Comercio, $this>
     */
    public function comercio(): BelongsTo
    {
        return $this->belongsTo(Comercio::class);
    }

    /**
     * @return BelongsTo<Sede, $this>
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor_otorgado_cop' => 'integer',
            'credencial_qr_id' => 'integer',
            'monto_cuenta_cop' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}
