<?php

declare(strict_types=1);

namespace App\Actions\Promociones;

use App\Models\CumplimientoMision;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * El envío de una misión desde la PWA (E3-06): guarda el pantallazo en la
 * carpeta del cliente en el disco privado, con un nombre que pone el
 * servidor, y registra el cumplimiento. Si el envío no se acepta, el
 * archivo se borra para no dejar soportes huérfanos.
 */
class SendCumplimientoMision
{
    private const DISCO = 'local';

    private readonly SubmitCumplimientoMision $submit;

    public function __construct(SubmitCumplimientoMision $submit)
    {
        $this->submit = $submit;
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(int $clienteId, string $codigo, ?UploadedFile $soporte): CumplimientoMision
    {
        $carpeta = SubmitCumplimientoMision::carpeta($clienteId);
        $path = $soporte === null ? null : $soporte->store($carpeta, self::DISCO);

        if ($path === false) {
            throw ValidationException::withMessages([
                'soporte' => 'No pudimos guardar el pantallazo. Intenta de nuevo.',
            ]);
        }

        try {
            return ($this->submit)($clienteId, $codigo, $path);
        } catch (Throwable $e) {
            if ($path !== null) {
                Storage::disk(self::DISCO)->delete($path);
            }

            throw $e;
        }
    }
}
