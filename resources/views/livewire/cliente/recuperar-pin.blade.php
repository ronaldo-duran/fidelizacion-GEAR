{{-- Olvidé mi PIN (E8-02): documento, código por SMS y PIN nuevo. --}}
<main class="flex min-h-dvh flex-col gap-6 px-5 pb-8 pt-6">
    <x-logo-ar :grande="true" />

    <div class="flex flex-col gap-2">
        <h1 class="font-display text-2xl">Recupera tu PIN</h1>
        <p class="text-md text-ink-2">Te enviamos un código por SMS al celular con el que te registraste.</p>
    </div>

    @if (! $codigoEnviado)
        <form wire:submit="requestCode" class="flex flex-1 flex-col gap-6" novalidate>
            <x-documento-campos />

            <div class="flex-1"></div>

            <x-button type="submit">Enviarme el código</x-button>
        </form>
    @else
        <form wire:submit="changePin" class="flex flex-1 flex-col gap-6" novalidate>
            @if ($aviso)
                <x-notice tipo="info">{{ $aviso }}</x-notice>
            @endif

            <div class="flex flex-col gap-3.5">
                <div class="flex flex-col gap-1.5">
                    <x-field
                        label="Código del SMS"
                        name="codigo"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        wire:model="form.codigo"
                        class="font-mono tracking-widest tabular-nums"
                    />
                    @error('form.codigo') <p class="text-sm text-danger" role="alert">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-col gap-1.5">
                    <x-field label="PIN nuevo de {{ config('registro.pin_digits') }} números" type="password" name="pin" inputmode="numeric" wire:model="form.pin" autocomplete="new-password" />
                    <span class="text-sm font-normal text-ink-2">Sin números repetidos ni seguidos.</span>
                    @error('form.pin') <p class="text-sm text-danger" role="alert">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-col gap-1.5">
                    <x-field label="Repite el PIN nuevo" type="password" name="pin_confirmation" inputmode="numeric" wire:model="form.pinConfirmation" autocomplete="new-password" />
                @error('form.pinConfirmation') <p class="text-sm text-danger" role="alert">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex-1"></div>

            <x-button type="submit">Guardar PIN nuevo</x-button>
        </form>
    @endif

    <a href="{{ route('cliente.ingreso') }}" class="inline-flex min-h-touch items-center justify-center text-md font-semibold text-ink-2">Volver a ingresar</a>
</main>
