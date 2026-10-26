<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Clientes\ReglasCliente;
use App\Enums\TipoDocumento;
use Livewire\Form;

/**
 * El formulario de ingreso del cliente (E8-02): tipo + número de documento y
 * PIN. Solo pide que estén; la comparación la hace `LoginCliente`.
 */
class IngresoForm extends Form
{
    public string $tipoDocumento = 'CC';

    public string $documento = '';

    public string $pin = '';

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'tipoDocumento' => ReglasCliente::tipoDocumento(),
            'documento' => ['required', 'string', 'max:30'],
            'pin' => ['required', 'string', 'max:10'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...ReglasCliente::messages(null),
            'documento.max' => 'Revisa el número del documento.',
            'pin.required' => 'Escribe tu PIN.',
            'pin.max' => 'Revisa tu PIN.',
        ];
    }

    public function tipo(): TipoDocumento
    {
        return TipoDocumento::from($this->tipoDocumento);
    }
}
