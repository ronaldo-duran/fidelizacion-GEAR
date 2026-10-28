<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CanalCompra;
use App\Enums\EstadoTransaccion;
use App\Enums\TipoDocumento;
use Carbon\CarbonImmutable;
use Database\Factories\TransaccionFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * A purchase. It is never edited nor deleted: only its state moves forward,
 * once (pending to credited or annulled, credited to annulled), and a
 * pending purchase gets its customer when it is credited.
 *
 * @property int $id
 * @property int|null $cliente_id
 * @property TipoDocumento $tipo_documento_cliente
 * @property string $documento_cliente
 * @property int $comercio_id
 * @property int|null $sede_id
 * @property int $monto
 * @property string $referencia_externa
 * @property string|null $idempotency_key
 * @property CanalCompra $canal
 * @property EstadoTransaccion $estado
 * @property CarbonImmutable|null $acreditada_at
 * @property int|null $registrada_por
 * @property CarbonImmutable $created_at
 */
class Transaccion extends Model
{
    /** @use HasFactory<TransaccionFactory> */
    use HasFactory;

    private const STATE_FIELDS = ['estado', 'cliente_id', 'acreditada_at', 'updated_at'];

    protected $table = 'transacciones';

    /** @var list<string> */
    protected $fillable = [
        'cliente_id',
        'tipo_documento_cliente',
        'documento_cliente',
        'comercio_id',
        'sede_id',
        'monto',
        'referencia_externa',
        'idempotency_key',
        'canal',
        'estado',
        'acreditada_at',
        'registrada_por',
    ];

    protected static function booted(): void
    {
        static::updating(static function (Transaccion $transaccion): void {
            $original = $transaccion->getOriginal('estado');
            $onlyMovesState = array_diff(array_keys($transaccion->getDirty()), self::STATE_FIELDS) === []
                && $original instanceof EstadoTransaccion
                && $original->canMoveTo($transaccion->estado)
                && ($transaccion->getRawOriginal('cliente_id') === null || ! $transaccion->isDirty('cliente_id'));

            if (! $onlyMovesState) {
                throw new LogicException('Purchases are never edited: only their state moves forward once.');
            }
        });

        static::deleting(static function (): void {
            throw new LogicException('Purchases are never deleted: annul them instead.');
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
     * @return BelongsTo<Comercio, $this>
     */
    public function comercio(): BelongsTo
    {
        return $this->belongsTo(Comercio::class);
    }

    /**
     * @return HasMany<MovimientoPuntos, $this>
     */
    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoPuntos::class);
    }

    /**
     * Los puntos que dejó la compra en el libro, neto de sus reversas.
     */
    public function puntosOtorgados(): int
    {
        return $this->movimientos->reduce(
            static fn (int $total, MovimientoPuntos $movimiento): int => $total + $movimiento->puntos,
            0,
        );
    }

    /**
     * The document is stored and looked up normalized (hard rule 3.10).
     *
     * @return Attribute<string, string>
     */
    protected function documentoCliente(): Attribute
    {
        return Attribute::make(
            set: static fn (string $value): string => Cliente::normalizeDocumento($value),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo_documento_cliente' => TipoDocumento::class,
            'monto' => 'integer',
            'canal' => CanalCompra::class,
            'estado' => EstadoTransaccion::class,
            'acreditada_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
