@props(['activa' => 'inicio'])

@php
    $tabs = [
        'inicio' => 'Inicio',
        'beneficios' => 'Beneficios',
        'historial' => 'Historial',
        'niveles' => 'Niveles',
    ];
@endphp

<nav class="flex gap-1 border-b border-line px-5">
    @foreach ($tabs as $clave => $texto)
        <span class="relative px-2 py-3 font-sans text-md font-semibold {{ $clave === $activa ? 'text-ink' : 'text-ink-2' }}">
            {{ $texto }}
            @if ($clave === $activa)
                <span class="absolute inset-x-2 bottom-0 h-0.5 rounded-full bg-brand"></span>
            @endif
        </span>
    @endforeach
</nav>
