<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoPago;
use App\Enums\Nivel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Pago de una membresía (E6-01). Guarda una copia del plan al iniciarse
 * (`nivel`, `duracion_meses`, `monto_cop`). Solo el webhook firmado de la
 * pasarela lo aprueba o lo rechaza.
 *
 * @property int $id
 * @property int $cliente_id
 * @property int $plan_id
 * @property string $referencia
 * @property string $pasarela
 * @property string|null $id_externo
 * @property EstadoPago $estado
 * @property Nivel $nivel
 * @property int $duracion_meses
 * @property int $monto_cop
 * @property CarbonImmutable|null $aprobado_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Cliente $cliente
 * @property-read PlanMembresia $plan
 * @property-read Membresia|null $membresia
 */
class PagoMembresia extends Model
{
    protected $table = 'pagos_membresia';

    /** @var list<string> */
    protected $fillable = [
        'cliente_id',
        'plan_id',
        'referencia',
        'pasarela',
        'nivel',
        'duracion_meses',
        'monto_cop',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['estado' => 'pendiente'];

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
     * @return HasOne<Membresia, $this>
     */
    public function membresia(): HasOne
    {
        return $this->hasOne(Membresia::class, 'pago_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoPago::class,
            'nivel' => Nivel::class,
            'duracion_meses' => 'integer',
            'monto_cop' => 'integer',
            'aprobado_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
