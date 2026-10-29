<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Nivel;
use App\Enums\TipoBeneficio;
use App\Enums\VentanaBeneficio;
use App\Observers\AuditoriaObserver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Un beneficio de un comercio (E5-01), con un valor por nivel. No cuesta
 * puntos (regla dura 3.1). Lo guarda `SaveBeneficio`, que valida las
 * condiciones contra su esquema cerrado (regla dura 3.8). Editarlo cambia las
 * próximas redenciones; las pasadas guardan su valor otorgado (regla dura
 * 3.11). Con redenciones no se borra: se desactiva.
 *
 * @property int $id
 * @property int $comercio_id
 * @property string $nombre
 * @property string|null $condiciones_texto
 * @property TipoBeneficio $tipo
 * @property array<string, int> $valores_por_nivel
 * @property array<string, mixed> $condiciones
 * @property VentanaBeneficio|null $max_por_cliente_ventana
 * @property int|null $max_por_cliente_cantidad
 * @property int|null $cupo_global
 * @property bool $requiere_soporte
 * @property CarbonImmutable $vigente_desde
 * @property CarbonImmutable|null $vigente_hasta
 * @property bool $activo
 */
#[ObservedBy(AuditoriaObserver::class)]
class Beneficio extends Model
{
    protected $table = 'beneficios';

    /** @var list<string> */
    protected $fillable = [
        'comercio_id',
        'nombre',
        'condiciones_texto',
        'tipo',
        'valores_por_nivel',
        'condiciones',
        'max_por_cliente_ventana',
        'max_por_cliente_cantidad',
        'cupo_global',
        'requiere_soporte',
        'vigente_desde',
        'vigente_hasta',
        'activo',
    ];

    protected static function booted(): void
    {
        static::deleting(static function (Beneficio $beneficio): void {
            if ($beneficio->tieneRedenciones()) {
                throw new LogicException('A benefit with redemptions is never deleted: deactivate it instead.');
            }
        });
    }

    /**
     * El valor que recibe ese nivel; null si ese nivel no lo recibe.
     */
    public function valorPara(Nivel $nivel): ?int
    {
        $valor = $this->valores_por_nivel[$nivel->value] ?? null;

        return is_int($valor) ? $valor : null;
    }

    /**
     * El nivel más bajo que tiene valor.
     */
    public function nivelMinimo(): Nivel
    {
        foreach (Nivel::cases() as $nivel) {
            if ($this->valorPara($nivel) !== null) {
                return $nivel;
            }
        }

        return Nivel::Diamante;
    }

    public function tieneRedenciones(): bool
    {
        return Redencion::query()->toBase()->where('beneficio_id', $this->id)->exists();
    }

    /**
     * @return BelongsTo<Comercio, $this>
     */
    public function comercio(): BelongsTo
    {
        return $this->belongsTo(Comercio::class);
    }

    /**
     * @return HasMany<Redencion, $this>
     */
    public function redenciones(): HasMany
    {
        return $this->hasMany(Redencion::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoBeneficio::class,
            'valores_por_nivel' => 'array',
            'condiciones' => 'array',
            'max_por_cliente_ventana' => VentanaBeneficio::class,
            'max_por_cliente_cantidad' => 'integer',
            'cupo_global' => 'integer',
            'requiere_soporte' => 'boolean',
            'vigente_desde' => 'immutable_date',
            'vigente_hasta' => 'immutable_date',
            'activo' => 'boolean',
        ];
    }
}
