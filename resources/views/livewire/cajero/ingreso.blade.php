{{-- Ingreso del cajero (E4-02, diseño 1y). Los administradores entran por el panel. --}}
<main class="flex min-h-dvh flex-col justify-center gap-6 px-6 py-8">
    <x-logo-ar :grande="true" />

    <div class="flex flex-col gap-1.5">
        <h1 class="font-display text-2xl">Ingreso a la caja</h1>
        <p class="text-md text-ink-2">Cajeros de los comercios del Grupo Aponte Rivera</p>
    </div>

    <form wire:submit="submit" class="flex flex-col gap-6" novalidate>
        <div class="flex flex-col gap-3.5">
            <div class="flex flex-col gap-1.5">
                <x-field label="Correo" type="email" name="email" wire:model="email" autocomplete="username" autocapitalize="none" />
                @error('email') <p class="text-sm text-danger" role="alert">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-col gap-1.5" x-data="{ visible: false }">
                <label class="flex flex-col gap-1.5">
                    <span class="text-md font-semibold">Contraseña</span>
                    <span class="flex h-input items-center rounded-md border border-field bg-surface pl-4 pr-1 focus-within:border-brand">
                        <input
                            :type="visible ? 'text' : 'password'"
                            type="password"
                            name="password"
                            wire:model="password"
                            autocomplete="current-password"
                            class="h-full min-w-0 flex-1 bg-transparent text-lg font-normal text-ink outline-none"
                        >
                        <button
                            type="button"
                            x-on:click="visible = ! visible"
                            class="grid size-touch place-items-center text-ink-2"
                            :aria-label="visible ? 'Ocultar la contraseña' : 'Mostrar la contraseña'"
                            aria-label="Mostrar la contraseña"
                        >
                            <x-heroicon-o-eye class="size-5" x-show="! visible" />
                            <x-heroicon-o-eye-slash class="size-5" x-show="visible" x-cloak />
                        </button>
                    </span>
                </label>
                @error('password') <p class="text-sm text-danger" role="alert">{{ $message }}</p> @enderror
            </div>

            <x-checkbox wire:model="recordar">Mantener la sesión en este celular</x-checkbox>
        </div>

        <x-button type="submit">Ingresar</x-button>
    </form>

    <p class="text-center text-md text-ink-2">¿Olvidaste tu contraseña? Pídele una nueva al administrador de tu comercio.</p>
</main>
