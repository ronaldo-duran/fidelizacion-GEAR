{{-- Encabezado del cliente: logo a la izquierda, acceso al perfil a la derecha. --}}
<header {{ $attributes->merge(['class' => 'flex items-center justify-between px-5 pb-1 pt-3']) }}>
    <x-logo-ar />
    <button type="button" class="flex size-11 items-center justify-center rounded-full" aria-label="Perfil">
        <x-heroicon-o-user-circle class="size-7" />
    </button>
</header>
