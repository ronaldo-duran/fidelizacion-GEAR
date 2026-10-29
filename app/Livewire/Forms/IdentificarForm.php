<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Clientes\ReglasCliente;
use App\Enums\TipoDocumento;
use App\Models\Cliente;
use Livewire\Form;

/**
 * Cómo identifica el cajero al cliente (E4-02): tipo y número de documento,
 * con CC primero (CLAUDE.md 3.10), o el código corto de su QR cuando la
 * cámara no lo lee.
 */
class IdentificarForm extends Form
{
    public string $tipoDocumento = 'CC';

    public string $documento = '';

    public string $codigo = '';

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'tipoDocumento' => ReglasCliente::tipoDocumento(),
            'documento' => ReglasCliente::documento(TipoDocumento::tryFrom($this->tipoDocumento)),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ReglasCliente::messages(TipoDocumento::tryFrom($this->tipoDocumento));
    }

    /**
     * Valida el documento ya normalizado, como se guarda y se busca.
     */
    public function validarDocumento(): void
    {
        $this->documento = Cliente::normalizeDocumento($this->documento);
        $this->validate();
    }

    public function tipo(): TipoDocumento
    {
        return TipoDocumento::from($this->tipoDocumento);
    }

    /**
     * El cliente que se identificó, cargado de nuevo en cada petición: la
     * caja solo guarda su id.
     */
    public function cliente(?int $id): ?Cliente
    {
        return $id === null ? null : Cliente::query()->find($id);
    }

    public function usar(Cliente $cliente): void
    {
        $this->tipoDocumento = $cliente->tipo_documento->value;
        $this->documento = $cliente->documento;
    }
}
