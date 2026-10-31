{{-- ModeSwitch (pantalla 8): registrar compra (E4-02) o validar beneficio (E5-02). --}}
@props(['activo' => 'registrar'])

<nav class="mx-4 mt-3 grid grid-cols-2 gap-1 rounded-md bg-sunken p-1" aria-label="Modo de la caja">
    @foreach (['registrar' => ['Registrar compra', 'heroicon-o-plus-circle', 'caja.inicio'], 'validar' => ['Validar beneficio', 'heroicon-o-qr-code', 'caja.beneficios']] as $modo => [$texto, $icono, $ruta])
        <a
            href="{{ route($ruta) }}"
            @class([
                'flex h-13 items-center justify-center gap-2 rounded-md text-base font-bold',
                'bg-cashier-ink text-white' => $activo === $modo,
                'text-cashier-ink-2' => $activo !== $modo,
            ])
            @if ($activo === $modo) aria-current="page" @endif
        >
            <x-dynamic-component :component="$icono" class="size-5.5" />
            {{ $texto }}
        </a>
    @endforeach
</nav>