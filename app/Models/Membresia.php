<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Nivel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un periodo de membresía (E6-01): uno por pago aprobado, append-only.
 * `vigente_hasta` es el último día válido (inclusive). La vigencia sale de
 * las fechas, así que el periodo no tiene estado y nunca se edita.
 *
 * @property int $id
 * @property int $cliente_id
 * @property int $plan_id
 * @property int $pago_id
 * @property Nivel $nivel
 * @property CarbonImmutable $vigente_desde
 * @property CarbonImmutable $vigente_hasta
 * @property CarbonImmutable $created_at
 * @property-read PlanMembresia $plan
 * @property-read PagoMembresia $pago
 */
class Membresia extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'membresias';

    /** @var list<string> */
    protected $fillable = ['cliente_id', 'plan_id', 'pago_id', 'nivel', 'vigente_desde', 'vigente_hasta'];

    /**
     * @return BelongsTo<Cliente, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * @return BelongsTo<PlanMembresia, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanMembresia::class);
    }

    /**
     * @return BelongsTo<PagoMembresia, $this>
     */
    public function pago(): BelongsTo
    {
        return $this->belongsTo(PagoMembresia::class, 'pago_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nivel' => Nivel::class,
            'vigente_desde' => 'immutable_date',
            'vigente_hasta' => 'immutable_date',
            'created_at' => 'immutable_datetime',
        ];
    }
}
