<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PropositoCredencial;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One issued QR credential. Its token is signed with the application key and
 * lives for seconds (hard rule 3.12).
 *
 * @property int $id
 * @property int $cliente_id
 * @property PropositoCredencial $proposito
 * @property int|null $beneficio_id
 * @property int|null $comercio_id
 * @property string $nonce
 * @property string $codigo_corto
 * @property CarbonImmutable $emitido_at
 * @property CarbonImmutable $vence_at
 * @property CarbonImmutable|null $usado_at
 * @property int|null $usado_por
 */
class CredencialQr extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'credenciales_qr';

    /** @var list<string> */
    protected $fillable = [
        'cliente_id',
        'proposito',
        'beneficio_id',
        'comercio_id',
        'nonce',
        'codigo_corto',
        'emitido_at',
        'vence_at',
    ];

    /**
     * @return BelongsTo<Cliente, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'proposito' => PropositoCredencial::class,
            'beneficio_id' => 'integer',
            'comercio_id' => 'integer',
            'emitido_at' => 'immutable_datetime',
            'vence_at' => 'immutable_datetime',
            'usado_at' => 'immutable_datetime',
        ];
    }
}
