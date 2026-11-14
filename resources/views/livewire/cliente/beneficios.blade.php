{{-- Catálogo de beneficios del cliente (E8-04, pantalla 1n). --}}
<div class="flex flex-col">
    <x-client-header />
    <x-tab-nav activa="beneficios" />

    <main class="flex flex-col gap-5 pt-4 pb-6">
        <h1 class="sr-only">Beneficios</h1>

        @if ($catalogo->fichas !== [])
            <nav class="flex gap-2 overflow-x-auto px-4 pb-1" aria-label="Categorías">
                <x-chip :href="route('cliente.beneficios')" :activo="$catalogo->categoria === null">Todos</x-chip>
                @foreach ($catalogo->fichas as $slug => $nombre)
                    <x-chip
                        wire:key="ficha-{{ $slug }}"
                        :href="route('cliente.beneficios', ['categoria' => $slug])"
                        :activo="$catalogo->categoria === $slug"
                    >{{ $nombre }}</x-chip>
                @endforeach
            </nav>
        @endif

        <div class="flex flex-col gap-6 px-4">
            @if ($catalogo->disponibles !== [])
                <section class="flex flex-col gap-2" aria-labelledby="puedes-usarlos">
                    <div class="flex items-baseline justify-between px-1">
                        <h2 id="puedes-usarlos" class="text-lg">Puedes usarlos</h2>
                        <span class="text-sm font-semibold text-ink-2">{{ count($catalogo->disponibles) }} con {{ $nivel->getLabel() }}</span>
                    </div>
                    @foreach ($catalogo->disponibles as $f)
                        <x-benefit-row
                            wire:key="disponible-{{ $f->beneficioId() }}"
                            :comercio="$f->comercio()"
                            :beneficio="$f->nombre()"
                            :meta="$f->meta"
                            :href="route('cliente.beneficio', $f->beneficioId())"
                        />
                    @endforeach
                </section>
            @endif

            @if ($catalogo->porAhoraNo !== [])
                <section class="flex flex-col gap-2" aria-labelledby="por-ahora-no">
                    <h2 id="por-ahora-no" class="px-1 text-lg">Por ahora no</h2>
                    @foreach ($catalogo->porAhoraNo as $f)
                        <x-benefit-row
                            wire:key="por-ahora-{{ $f->beneficioId() }}"
                            :comercio="$f->comercio()"
                            :beneficio="$f->nombre()"
                            :meta="$f->meta"
                            :href="route('cliente.beneficio', $f->beneficioId())"
                        />
                    @endforeach
                </section>
            @endif

            @if ($catalogo->bloqueados !== [])
                <section class="flex flex-col gap-2" aria-labelledby="se-abren">
                    <h2 id="se-abren" class="px-1 text-lg">Se abren al subir de nivel</h2>
                    @foreach ($catalogo->bloqueados as $f)
                        <x-benefit-row
                            wire:key="bloqueado-{{ $f->beneficioId() }}"
                            estado="bloqueado"
                            :nivel="$f->item->nivelRequerido?->value"
                            :comercio="$f->comercio()"
                            :beneficio="$f->nombre()"
                            :meta="$f->meta"
                            :href="route('cliente.beneficio', $f->beneficioId())"
                        />
                    @endforeach
                </section>
            @endif

            @if ($catalogo->vacio())
                <x-empty-state
                    titulo="Todavía no hay beneficios aquí"
                    descripcion="Sube de nivel para abrir más beneficios en los comercios del Club."
                    icono="heroicon-o-gift"
                >
                    <x-slot:cta>
                        <x-button variant="enlace" :href="route('cliente.niveles')">Ver cómo subir de nivel</x-button>
                    </x-slot:cta>
                </x-empty-state>
            @endif
        </div>
    </main>
</div>
