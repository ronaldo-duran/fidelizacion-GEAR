{{-- "Para usar hoy" del inicio (E8-03, regla 6): solo los disponibles hoy. --}}
<section class="flex flex-col gap-1">
    <div class="flex items-center justify-between pb-1">
        <h2 class="text-lg">Para usar hoy</h2>
        @if ($total > count($beneficios))
            @if (\Illuminate\Support\Facades\Route::has('cliente.beneficios'))
                <a href="{{ route('cliente.beneficios') }}" class="flex min-h-touch items-center text-md font-semibold text-brand">Ver los {{ $total }}</a>
            @else
                <span class="flex min-h-touch items-center text-md font-semibold text-ink-2">Ver los {{ $total }}</span>
            @endif
        @endif
    </div>

    @forelse ($beneficios as $item)
        <x-benefit-list-item
            wire:key="hoy-{{ $item->beneficio->id }}"
            :comercio="$item->beneficio->comercio?->razon_social"
            :titulo="$item->beneficio->nombre"
            :href="route('cliente.beneficio', $item->beneficio->id)"
        />
    @empty
        <x-empty-state
            titulo="Hoy no tienes beneficios para usar"
            descripcion="Sube de nivel para abrir más beneficios en los comercios del Club."
            icono="heroicon-o-gift"
        >
            <x-slot:cta>
                <x-button variant="enlace" :href="route('cliente.niveles')">Ver cómo subir de nivel</x-button>
            </x-slot:cta>
        </x-empty-state>
    @endforelse
</section>
