<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoRegla;
use App\Reglas\ReglaDefinitionValidator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Immutable version of an engine rule. Saving a change creates a new row
 * with `version + 1`; the previous one only gets `vigente_hasta` (hard rule
 * 3.2). Rows are created through the actions in `App\Actions\Reglas`.
 *
 * The immutability guard lives in Eloquent events: mass updates through the
 * query builder or raw SQL skip it. It is defense in depth, not a database
 * guarantee.
 *
 * @property int $id
 * @property string $codigo
 * @property TipoRegla $tipo
 * @property int $version
 * @property CarbonImmutable $vigente_desde
 * @property CarbonImmutable|null $vigente_hasta
 * @property bool $activa
 * @property array<string, mixed> $definicion
 * @property int|null $creada_por
 * @property CarbonImmutable|null $created_at
 */
class Regla extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'reglas';

    /** @var list<string> */
    protected $fillable = [
        'codigo',
        'tipo',
        'version',
        'vigente_desde',
        'vigente_hasta',
        'activa',
        'definicion',
        'creada_por',
    ];

    /**
     * Last line of defense against in-place edits: even if someone skips the
     * actions, the model rejects an invalid definition, content changes and
     * deletes. The only write allowed on an existing row is closing its
     * validity, once.
     */
    protected static function booted(): void
    {
        static::creating(static function (Regla $regla): void {
            $errors = app(ReglaDefinitionValidator::class)->errors($regla->tipo->value, $regla->definicion);

            if ($errors !== []) {
                throw new LogicException('Invalid rule definition: ' . implode(' ', $errors));
            }
        });

        static::updating(static function (Regla $regla): void {
            $onlyClosesValidity = array_keys($regla->getDirty()) === ['vigente_hasta']
                && $regla->getOriginal('vigente_hasta') === null;

            if (! $onlyClosesValidity) {
                throw new LogicException('Rules are never edited: save a new version instead.');
            }
        });

        static::deleting(static function (): void {
            throw new LogicException('Rules are never deleted: save a new inactive version instead.');
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creada_por');
    }

    /**
     * Every version of the same rule, this one included.
     *
     * @return HasMany<Regla, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(self::class, 'codigo', 'codigo');
    }

    /**
     * The code is stored and looked up in lowercase (hard rule 3.13).
     *
     * @return Attribute<string, string>
     */
    protected function codigo(): Attribute
    {
        return Attribute::make(
            set: static fn (string $value): string => mb_strtolower(trim($value)),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoRegla::class,
            'version' => 'integer',
            'vigente_desde' => 'immutable_datetime',
            'vigente_hasta' => 'immutable_datetime',
            'activa' => 'boolean',
            'definicion' => 'array',
            'creada_por' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}
