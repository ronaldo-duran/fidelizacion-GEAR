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

    /**
     * La URL de regreso, como la de la pasarela real, solo si es de esta
     * aplicación: nunca una redirección abierta a otro sitio.
     */
    public function regreso(): ?string
    {
        $regreso = $this->query('regreso');

        return is_string($regreso) && str_starts_with($regreso, url('/') . '/') ? $regreso : null;
    }
}
