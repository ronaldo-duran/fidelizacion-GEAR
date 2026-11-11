@props(['activa' => 'inicio', 'enNivel' => false])

{{--
    Pestañas del cliente (E8-03, regla 10). Una pestaña cuya pantalla todavía
    no existe se muestra pero no es enlace. Dentro del bloque del nivel
    (`en-nivel`) toman el color del texto del bloque, como en el diseño 1f.
--}}
@php
    $tabs = [
        'inicio' => ['Inicio', 'cliente.inicio'],
        'beneficios' => ['Beneficios', 'cliente.beneficios'],
        'historial' => ['Historial', 'cliente.historial'],
        'niveles' => ['Niveles', 'cliente.niveles'],
    ];
@endphp

<nav class="flex gap-1 border-b px-3 {{ $enNivel ? 'border-current/20' : 'border-line' }}" aria-label="Secciones">
    @foreach ($tabs as $clave => [$texto, $ruta])
        @php
            $esActiva = $clave === $activa;
            $existe = \Illuminate\Support\Facades\Route::has($ruta);
            // En el bloque del nivel todas van en el color del bloque: la activa se distingue por la línea (sin opacidad, regla 6 de club-ar-ui).
            $color = $enNivel ? '' : ($esActiva ? 'text-ink' : 'text-ink-2');
        @endphp
        <{{ $existe ? 'a' : 'span' }}
            @if ($existe) href="{{ route($ruta) }}" @endif
            @if ($esActiva) aria-current="page" @endif
            class="relative flex min-h-touch items-center px-2 font-sans text-md font-semibold {{ $color }}"
        >
            {{ $texto }}
            @if ($esActiva)
                <span class="absolute inset-x-2 bottom-0 h-0.5 rounded-full {{ $enNivel ? 'bg-current' : 'bg-brand' }}"></span>
            @endif
        </{{ $existe ? 'a' : 'span' }}>
    @endforeach
</nav>
