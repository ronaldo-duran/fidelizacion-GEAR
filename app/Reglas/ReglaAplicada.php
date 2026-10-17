<?php

declare(strict_types=1);

namespace App\Reglas;

use App\Enums\TipoRegla;
use App\Models\Regla;

/**
 * A rule definition handed to the calculator: either a saved version (with
 * its id and version for the movement snapshot, hard rule 3.3) or an unsaved
 * draft from the simulator.
 */
final class ReglaAplicada
{
    public readonly ?int $id;

    public readonly string $codigo;

    public readonly ?int $version;

    public readonly TipoRegla $tipo;

    /** @var array<array-key, mixed> */
    public readonly array $definicion;

    /**
     * @param  array<array-key, mixed>  $definicion
     */
    public function __construct(?int $id, string $codigo, ?int $version, TipoRegla $tipo, array $definicion)
    {
        $this->id = $id;
        $this->codigo = $codigo;
        $this->version = $version;
        $this->tipo = $tipo;
        $this->definicion = $definicion;
    }

    public static function fromModel(Regla $regla): self
    {
        return new self($regla->id, $regla->codigo, $regla->version, $regla->tipo, $regla->definicion);
    }

    /**
     * @param  array<array-key, mixed>  $definicion
     */
    public static function draft(string $codigo, TipoRegla $tipo, array $definicion): self
    {
        return new self(null, $codigo, null, $tipo, $definicion);
    }

    public function isDraft(): bool
    {
        return $this->version === null;
    }

    public function versionLabel(): string
    {
        return $this->version === null ? 'borrador' : "v{$this->version}";
    }
}
