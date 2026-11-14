@props(['nivel' => null])

{{-- Encabezado del cliente: logo a la izquierda; avisos y enlace al perfil (E8-05) a la derecha. Con `nivel`, el logo toma los colores del bloque del nivel (inicio). --}}
@php
    $cliente = auth('cliente')->user();
    $noLeidos = $cliente === null ? 0 : app(\App\Notificaciones\AvisosDelCliente::class)->noLeidos((int) $cliente->getAuthIdentifier());
@endphp
<header {{ $attributes->merge(['class' => 'flex items-center justify-between px-5 pb-1 pt-3']) }}>
    <x-logo-ar :nivel="$nivel" />
    <div class="flex items-center gap-1">
        @if ($cliente !== null)
            <a
                href="{{ route('cliente.avisos') }}"
                class="relative flex size-11 items-center justify-center rounded-full"
                aria-label="{{ $noLeidos > 0 ? "Avisos, {$noLeidos} sin leer" : 'Avisos' }}"
            >
                <x-heroicon-o-bell class="size-7" />
                @if ($noLeidos > 0)
                    <span class="absolute right-0.5 top-0.5 flex min-w-5 items-center justify-center rounded-full bg-brand px-1 text-xs font-bold text-paper tabular-nums">{{ $noLeidos > 9 ? '9+' : $noLeidos }}</span>
                @endif
            </a>
        @endif
        @if ($cliente !== null)
            <a href="{{ route('cliente.perfil') }}" class="flex size-11 items-center justify-center rounded-full" aria-label="Mi perfil">
                <x-heroicon-o-user-circle class="size-7" />
            </a>
        @endif
    </div>
</header>
