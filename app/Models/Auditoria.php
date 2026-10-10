<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro inmutable de operaciones sensibles sobre modelos auditables.
 * Append-only: se inserta, nunca se actualiza ni se borra.
 *
 * @property int $id
 * @property int|null $usuario_id
 * @property string $evento
 * @property string $modelo
 * @property int $modelo_id
 * @property array<string, mixed>|null $antes
 * @property array<string, mixed>|null $despues
 */
class Auditoria extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'auditorias';

    /** @var list<string> */
    protected $fillable = [
        'usuario_id',
        'evento',
        'modelo',
        'modelo_id',
        'antes',
        'despues',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'usuario_id' => 'integer',
            'modelo_id' => 'integer',
            'antes' => 'array',
            'despues' => 'array',
        ];
    }
}
