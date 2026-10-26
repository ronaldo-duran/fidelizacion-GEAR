{{-- Ingreso del cliente (E8-02). El diseño no trae esta pantalla: sigue la estructura del login 1y. --}}
<main class="flex min-h-dvh flex-col gap-6 px-5 pb-8 pt-6">
    <x-logo-ar :grande="true" />

    <div class="flex flex-col gap-2">
        <h1 class="font-display text-2xl">Ingresa al Club</h1>
        <p class="text-md text-ink-2">Con tu documento y tu PIN.</p>
    </div>

    <form wire:submit="submit" class="flex flex-1 flex-col gap-6" novalidate>
        <div class="flex flex-col gap-3.5">
            <x-documento-campos />

            <div class="flex flex-col gap-1.5">
                <x-field label="PIN" type="password" name="pin" inputmode="numeric" wire:model="form.pin" autocomplete="current-password" />
                @error('form.pin') <p class="text-sm text-danger" role="alert">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex-1"></div>

        <x-button type="submit">Ingresar</x-button>
    </form>

    <div class="flex flex-col items-center gap-1">
        <a href="{{ route('cliente.recuperar-pin') }}" class="inline-flex min-h-touch items-center text-md font-semibold text-brand">Olvidé mi PIN</a>
        <p class="text-center text-md">
            ¿Aún no eres parte? <a href="{{ route('cliente.registro') }}" class="inline-flex min-h-touch items-center font-semibold text-brand">Únete al Club</a>
        </p>
    </div>
</main>
