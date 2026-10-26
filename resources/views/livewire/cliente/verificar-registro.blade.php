{{-- Verificación del celular del registro (E8-02 con E8-06). --}}
<main class="flex min-h-dvh flex-col gap-6 px-5 pb-8 pt-6">
    <x-logo-ar :grande="true" />

    @if ($pendiente === null)
        <div class="flex flex-col gap-4">
            <h1 class="font-display text-2xl">Tu registro venció</h1>
            <p class="text-md text-ink-2">Pasó mucho tiempo sin verificar el celular. Empieza de nuevo; tus datos no se guardaron.</p>
            <x-button :href="route('cliente.registro')">Empezar de nuevo</x-button>
        </div>
    @else
        <div class="flex flex-col gap-2">
            <h1 class="font-display text-2xl">Verifica tu celular</h1>
            <p class="text-md text-ink-2">
                Te enviamos un código por SMS al celular que termina en
                <span class="font-bold tabular-nums text-ink">{{ substr($pendiente->celular, -4) }}</span>.
            </p>
        </div>

        <form wire:submit="verify" class="flex flex-1 flex-col gap-6" novalidate>
            <div class="flex flex-col gap-1.5">
                <x-field
                    label="Código del SMS"
                    name="codigo"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    wire:model="codigo"
                    class="font-mono tracking-widest tabular-nums"
                />
                @error('codigo') <p class="text-sm text-danger" role="alert">{{ $message }}</p> @enderror
                @if ($aviso)
                    <p class="text-sm text-ink-2" role="status">{{ $aviso }}</p>
                @endif
            </div>

            <div class="flex-1"></div>

            <x-button type="submit">Verificar y crear mi cuenta</x-button>
        </form>

        <div class="flex flex-col items-center gap-1">
            <button type="button" wire:click="resend" class="min-h-touch font-sans text-md font-semibold text-brand">
                Enviar otro código
            </button>
            <a href="{{ route('cliente.registro') }}" class="inline-flex min-h-touch items-center text-md font-semibold text-ink-2">
                Corregir mis datos
            </a>
        </div>
    @endif
</main>
