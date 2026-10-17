<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Program customer. `saldo_puntos` is derived from the ledger and is only
 * written by the points actions, inside the same transaction as the
 * movement (hard rule 3.4).
 *
 * @property int $id
 * @property string $documento
 * @property string|null $celular
 * @property string|null $email
 * @property int $saldo_puntos
 */
class Cliente extends Model
{
    protected $table = 'clientes';

    /** @var list<string> */
    protected $fillable = ['documento', 'celular', 'email'];

    /**
     * @return HasMany<MovimientoPuntos, $this>
     */
    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoPuntos::class);
    }

    /**
     * The document is stored without spaces so lookups match on every engine
     * (hard rule 3.13).
     *
     * @return Attribute<string, string>
     */
    protected function documento(): Attribute
    {
        return Attribute::make(
            set: static fn (string $value): string => str_replace(' ', '', trim($value)),
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
        return ['saldo_puntos' => 'integer'];
    }
}
