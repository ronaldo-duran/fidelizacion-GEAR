<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Clientes\ReglasCliente;
use App\Enums\TipoDocumento;
use Livewire\Form;

/**
 * "Olvidé mi PIN" (E8-02) en dos pasos: el documento, y luego el código del
 * SMS con el PIN nuevo (con las mismas reglas del registro).
 */
class RecuperarPinForm extends Form
{
    public string $tipoDocumento = 'CC';

    public string $documento = '';

    public string $codigo = '';

    public string $pin = '';

    public string $pinConfirmation = '';

    /**
     * @return array<string, list<mixed>>
     */
    public function documentoRules(): array
    {
        return [
            'tipoDocumento' => ReglasCliente::tipoDocumento(),
            'documento' => ['required', 'string', 'max:30'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function pinRules(): array
    {
        return [
            ...$this->documentoRules(),
            'codigo' => ['required', 'string', 'max:10'],
            'pin' => ReglasCliente::pin(),
            'pinConfirmation' => ['same:pin'],
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
            'codigo.required' => 'Escribe el código del SMS.',
            'codigo.max' => 'Revisa el código del SMS.',
            'pin.required' => 'Elige un PIN nuevo.',
        ];
    }

    public function tipo(): TipoDocumento
    {
        return TipoDocumento::from($this->tipoDocumento);
    }
}
