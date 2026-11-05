<?php

declare(strict_types=1);

namespace App\Http\Requests\Cliente;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Lo que se elige en la pasarela simulada: aprobar o rechazar el pago.
 */
class PagoSimuladoRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['resultado' => ['required', 'in:aprobar,rechazar']];
    }

    public function aprobar(): bool
    {
        return $this->input('resultado') === 'aprobar';
    }
}
