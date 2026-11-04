<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MotivoSupresion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Un correo que no debe recibir mensajes (E7-01; la llena E7-02). El correo
 * se guarda y se busca en minúsculas (regla dura 3.13).
 *
 * @property int $id
 * @property string $email
 * @property MotivoSupresion $motivo
 * @property CarbonImmutable $created_at
 */
class SupresionCorreo extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'supresiones_correo';

    /** @var list<string> */
    protected $fillable = ['email', 'motivo'];

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public static function motivoPara(string $email): ?MotivoSupresion
    {
        return self::query()->where('email', self::normalizeEmail($email))->first()?->motivo;
    }

    protected static function booted(): void
    {
        static::saving(static function (SupresionCorreo $supresion): void {
            $supresion->email = self::normalizeEmail($supresion->email);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'motivo' => MotivoSupresion::class,
            'created_at' => 'immutable_datetime',
        ];
    }
}
