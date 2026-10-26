{{-- Registro del cliente (E8-02, pantalla 1m). --}}
<main class="flex min-h-dvh flex-col gap-6 px-5 pb-8 pt-6">
    <x-logo-ar :grande="true" />

    <div class="flex flex-col gap-2">
        <h1 class="font-display text-2xl">Únete al Club</h1>
        <p class="text-md text-ink-2">Tus compras en los 15 comercios del grupo suman puntos para subir de nivel.</p>
    </div>

    <form wire:submit="submit" class="flex flex-1 flex-col gap-6" novalidate>
        <div class="flex flex-col gap-3.5">
            <x-documento-campos />

            <div class="flex flex-col gap-1.5">
                <x-field label="Nombre completo" name="nombre_completo" wire:model="form.nombreCompleto" autocomplete="name" />
                @error('form.nombreCompleto') <p class="text-sm text-danger" role="alert">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <x-field label="Fecha de nacimiento" type="date" name="fecha_nacimiento" wire:model="form.fechaNacimiento" autocomplete="bday" />
                @error('form.fechaNacimiento') <p class="text-sm text-danger" role="alert">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <x-field label="Celular" type="tel" name="celular" inputmode="numeric" placeholder="300 123 4567" wire:model="form.celular" autocomplete="tel-national" class="tabular-nums" />
                @error('form.celular') <p class="text-sm text-danger" role="alert">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <x-field label="Correo (opcional)" type="email" name="email" wire:model="form.email" autocomplete="email" />
                @error('form.email') <p class="text-sm text-danger" role="alert">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <x-field label="PIN de {{ config('registro.pin_digits') }} números" type="password" name="pin" inputmode="numeric" wire:model="form.pin" autocomplete="new-password" />
                <span class="text-sm font-normal text-ink-2">Lo usas para entrar. Sin números repetidos ni seguidos.</span>
                @error('form.pin') <p class="text-sm text-danger" role="alert">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <x-field label="Repite el PIN" type="password" name="pin_confirmation" inputmode="numeric" wire:model="form.pinConfirmation" autocomplete="new-password" />
                @error('form.pinConfirmation') <p class="text-sm text-danger" role="alert">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex flex-col gap-1.5">
            <x-checkbox wire:model.live="form.autorizacion">
                {{-- TODO(cliente): el enlace apunta a la política de datos cuando exista (E10-03). --}}
                Autorizo el tratamiento de mis datos personales según la
                <a href="#" class="font-bold text-brand underline">política de datos</a>
                del Grupo Aponte Rivera.
            </x-checkbox>
            @error('form.autorizacion') <p class="text-sm text-danger" role="alert">{{ $message }}</p> @enderror
        </div>

        <div class="flex-1"></div>

        <x-button type="submit" :disabled="! $form->autorizacion" ayuda="Marca la autorización para continuar">
            Crear mi cuenta
        </x-button>

        <p class="text-center text-md">
            ¿Ya tienes cuenta? <a href="{{ route('cliente.ingreso') }}" class="inline-flex min-h-touch items-center font-semibold text-brand">Ingresa</a>
        </p>
    </form>
</main>
