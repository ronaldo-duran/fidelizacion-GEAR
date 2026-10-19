<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoDocumento;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Program customer. `saldo_puntos` is derived from the ledger and is only
 * written by the points actions, inside the same transaction as the
 * movement (hard rule 3.4).
 *
 * @property int $id
 * @property TipoDocumento $tipo_documento
 * @property string $documento
 * @property CarbonImmutable|null $fecha_nacimiento
 * @property string|null $celular
 * @property string|null $email
 * @property int $saldo_puntos
 */
class Cliente extends Model
{
    protected $table = 'clientes';

    /** @var list<string> */
    protected $fillable = ['tipo_documento', 'documento', 'fecha_nacimiento', 'celular', 'email'];

    /**
     * @return HasMany<MovimientoPuntos, $this>
     */
    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoPuntos::class);
    }

    /**
     * The document as it is stored and looked up: uppercase and without
     * spaces, so `ppt123` and `PPT 123` are the same document on every engine
     * (hard rule 3.13). Lookups must normalize with this too.
     */
    public static function normalizeDocumento(string $documento): string
    {
        return mb_strtoupper(str_replace(' ', '', trim($documento)));
    }

    /**
     * @return Attribute<string, string>
     */
    protected function documento(): Attribute
    {
        return Attribute::make(
            set: static fn (string $value): string => self::normalizeDocumento($value),
        );
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: static fn (?string $value): ?string => $value === null ? null : mb_strtolower(trim($value)),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo_documento' => TipoDocumento::class,
            'fecha_nacimiento' => 'immutable_date',
            'saldo_puntos' => 'integer',
        ];
    }
}
