@props(['prefijo' => 'form'])

{{-- Tipo y número de documento (CLAUDE.md 3.10): el tipo siempre se pide, con CC primero. --}}
<div class="flex flex-col gap-3.5">
    <div class="flex flex-col gap-1.5">
        <x-select label="Tipo de documento" wire:model.live="{{ $prefijo }}.tipoDocumento" name="tipo_documento">
            @foreach (\App\Enums\TipoDocumento::cases() as $tipo)
                <option value="{{ $tipo->value }}">{{ $tipo->getLabel() }}</option>
            @endforeach
        </x-select>
        @error("{$prefijo}.tipoDocumento")
            <p class="text-sm text-danger">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex flex-col gap-1.5">
        <x-field
            label="Número de documento"
            name="documento"
            wire:model="{{ $prefijo }}.documento"
            autocomplete="off"
            autocapitalize="characters"
            class="tabular-nums"
        />
        @error("{$prefijo}.documento")
            <p class="text-sm text-danger" role="alert">{{ $message }}</p>
        @enderror
    </div>
</div>
