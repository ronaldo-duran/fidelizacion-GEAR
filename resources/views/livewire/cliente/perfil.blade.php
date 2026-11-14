{{-- Perfil del cliente (E8-05, pantalla 1t). Avisos por correo: WhatsApp está fuera de alcance (§8). --}}
<div class="flex flex-col">
    @php
        $nivel = $perfil->nivel;
        $fila = 'flex min-h-13 items-center justify-between gap-3 px-3.5';
    @endphp

    <header class="flex items-center gap-1 px-1 pt-3">
        <a href="{{ route('cliente.inicio') }}" class="flex size-11 items-center justify-center" aria-label="Volver al inicio">
            <x-heroicon-o-arrow-left class="size-6" />
        </a>
        <h1 class="text-lg font-bold">Mi perfil</h1>
    </header>

    <main class="flex flex-col gap-5 px-4 pt-3 pb-6">
        <div class="flex items-center gap-3.5 px-1">
            <span
                class="flex size-14 shrink-0 items-center justify-center rounded-full font-display text-xl font-bold"
                style="background: var(--color-tier-{{ $nivel }}-bg); color: var(--color-tier-{{ $nivel }}-ink)"
                aria-hidden="true"
            >
                @if ($perfil->iniciales)
                    {{ $perfil->iniciales }}
                @else
                    <x-heroicon-o-user class="size-7" />
                @endif
            </span>
            <div class="flex min-w-0 flex-col">
                <p class="font-display text-xl font-bold">{{ $perfil->nombre }}</p>
                @if ($perfil->nivelDesde)
                    <p class="text-sm font-normal text-ink-2">{{ $perfil->nivelDesde }}</p>
                @endif
            </div>
        </div>

        <section class="flex flex-col gap-1.5" aria-labelledby="mis-datos">
            <h2 id="mis-datos" class="px-1 text-overline uppercase text-ink-2">Mis datos</h2>
            <div class="flex flex-col divide-y divide-line rounded-md border border-line bg-surface">
                <div class="{{ $fila }}">
                    <span class="text-md text-ink-2">Documento</span>
                    <span class="text-md font-semibold">{{ $perfil->documento }}</span>
                </div>
                <div class="{{ $fila }}">
                    <span class="text-md text-ink-2">Celular</span>
                    <span class="text-md font-semibold tabular-nums">{{ $perfil->celular ?? '—' }}</span>
                </div>

                @foreach ([
                    'email' => ['Correo', $perfil->email, 'guardarCorreo', 'email', 'tu@correo.com'],
                    'fechaNacimiento' => ['Fecha de nacimiento', $perfil->fechaNacimiento, 'guardarFecha', 'date', null],
                ] as $campo => [$etiqueta, $valor, $accion, $tipo, $ejemplo])
                    @if ($editando === $campo)
                        <form wire:submit="{{ $accion }}" wire:key="editar-{{ $campo }}" class="flex flex-col gap-3 p-3.5">
                            <x-field :label="$etiqueta" :type="$tipo" :placeholder="$ejemplo" wire:model="{{ $campo }}" autofocus />
                            @error($campo) <p class="text-sm text-danger" role="alert">{{ $message }}</p> @enderror
                            @if ($campo === 'fechaNacimiento')
                                <p class="text-sm font-normal text-ink-2">Una vez guardada no la puedes cambiar desde aquí.</p>
                            @endif
                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" wire:click="cancelar" class="h-touch rounded-md border border-ink text-md font-bold">Cancelar</button>
                                <button type="submit" wire:loading.attr="disabled" wire:target="{{ $accion }}" class="h-touch rounded-md bg-brand text-md font-bold text-white disabled:bg-sunken disabled:text-ink-2">Guardar</button>
                            </div>
                        </form>
                    @elseif ($valor !== null && $campo === 'fechaNacimiento')
                        <div class="{{ $fila }}" wire:key="ver-{{ $campo }}">
                            <span class="text-md text-ink-2">{{ $etiqueta }}</span>
                            <span class="text-md font-semibold">{{ $valor }}</span>
                        </div>
                    @else
                        <button type="button" wire:click="editar('{{ $campo }}')" wire:key="ver-{{ $campo }}" class="{{ $fila }} text-left">
                            <span class="text-md text-ink-2">{{ $etiqueta }}</span>
                            @if ($valor !== null)
                                <span class="flex min-w-0 items-center gap-1.5 text-md font-semibold">
                                    <span class="truncate">{{ $valor }}</span>
                                    <x-heroicon-o-chevron-right class="size-4 shrink-0" />
                                </span>
                            @else
                                <span class="text-md font-semibold text-brand">Agregar</span>
                            @endif
                        </button>
                    @endif
                @endforeach
            </div>
        </section>

        <section class="flex flex-col gap-1.5" aria-labelledby="avisos-correo">
            <h2 id="avisos-correo" class="px-1 text-overline uppercase text-ink-2">Avisos por correo</h2>
            @unless ($perfil->tieneCorreo)
                <p class="px-1 text-sm font-normal text-ink-2">Agrega tu correo para recibir avisos</p>
            @endunless
            <div class="flex flex-col divide-y divide-line rounded-md border border-line bg-surface">
                @foreach ($perfil->avisos as $evento => $aviso)
                    <div class="flex min-h-14 items-center justify-between gap-3 px-3.5" wire:key="aviso-{{ $evento }}">
                        <span id="aviso-{{ $evento }}" class="text-md font-semibold">{{ $aviso['texto'] }}</span>
                        <button
                            type="button"
                            role="switch"
                            aria-checked="{{ $aviso['encendido'] ? 'true' : 'false' }}"
                            aria-labelledby="aviso-{{ $evento }}"
                            wire:click="cambiarAviso('{{ $evento }}', {{ $aviso['encendido'] ? 'false' : 'true' }})"
                            wire:loading.attr="disabled"
                            wire:target="cambiarAviso"
                            @disabled(! $perfil->tieneCorreo)
                            class="flex h-11 shrink-0 items-center"
                        >
                            <span class="relative h-7 w-12 rounded-full transition-colors {{ $aviso['encendido'] ? 'bg-brand' : 'bg-field' }}">
                                <span class="absolute top-0.5 size-6 rounded-full bg-white transition-all {{ $aviso['encendido'] ? 'right-0.5' : 'left-0.5' }}"></span>
                            </span>
                        </button>
                    </div>
                @endforeach
            </div>
            @error('avisos') <p class="px-1 text-sm text-danger" role="alert">{{ $message }}</p> @enderror
        </section>

        <section class="flex flex-col gap-1.5" aria-labelledby="documentos">
            <h2 id="documentos" class="px-1 text-overline uppercase text-ink-2">Documentos</h2>
            <div class="flex flex-col divide-y divide-line rounded-md border border-line bg-surface">
                @foreach ($perfil->documentos as $texto => $url)
                    @php $icono = $loop->first ? 'heroicon-o-document-text' : 'heroicon-o-shield-check'; @endphp
                    @if ($url !== '')
                        <a href="{{ $url }}" target="_blank" rel="noopener" class="flex min-h-13 items-center gap-3 px-3.5">
                            @svg($icono, 'size-5 shrink-0')
                            <span class="flex-1 text-md font-semibold">{{ $texto }}</span>
                            <x-heroicon-o-chevron-right class="size-4 shrink-0" />
                        </a>
                    @else
                        <div class="flex min-h-13 items-center gap-3 px-3.5">
                            @svg($icono, 'size-5 shrink-0')
                            <span class="flex-1 text-md font-semibold">{{ $texto }}</span>
                            <span class="text-sm text-ink-2">Disponible pronto</span>
                        </div>
                    @endif
                @endforeach
                @if ($perfil->misDatos)
                    <a href="{{ $perfil->misDatos }}" class="flex min-h-13 items-center gap-3 px-3.5">
                        <x-heroicon-o-identification class="size-5 shrink-0" />
                        <span class="flex-1 text-md font-semibold">Consultar o eliminar mis datos</span>
                        <x-heroicon-o-chevron-right class="size-4 shrink-0" />
                    </a>
                @endif
            </div>
        </section>

        <form method="POST" action="{{ route('cliente.salir') }}" class="px-1">
            @csrf
            <button type="submit" class="flex min-h-touch items-center text-md font-bold text-danger">Cerrar sesión</button>
        </form>
    </main>
</div>
