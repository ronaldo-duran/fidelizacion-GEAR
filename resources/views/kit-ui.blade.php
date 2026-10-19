@php
    $niveles = ['bronce', 'plata', 'oro', 'diamante'];
@endphp

<x-layouts.cliente title="Kit de interfaz">
    <x-client-header />
    <x-tab-nav activa="inicio" />

    <main class="flex flex-col gap-10 px-5 py-6">
        <p class="text-sm text-ink-2">
            Catálogo de las primitivas compartidas (E8-01). Solo visible en local y staging.
            Compáralo contra <span class="font-mono">docs/diseno/reference</span> a 360&nbsp;px.
        </p>

        {{-- Niveles --}}
        <section class="flex flex-col gap-4">
            <h2 class="text-overline uppercase text-ink-2">Niveles</h2>

            <div class="flex flex-wrap items-center gap-3">
                @foreach ($niveles as $nivel)
                    <x-tier-badge :nivel="$nivel" />
                @endforeach
            </div>

            <div class="flex flex-wrap items-center gap-4">
                @foreach ($niveles as $nivel)
                    <x-tier-diamonds :nivel="$nivel" />
                @endforeach
            </div>

            <div class="flex flex-col gap-2">
                <x-tier-card nivel="bronce" meta="0 a 499 pts" />
                <x-tier-card nivel="plata" meta="500 a 1.499 pts" />
                <x-tier-card nivel="oro" :actual="true" meta="1.500 a 2.999 pts" />
                <x-tier-card nivel="diamante" meta="3.000 pts, suscripción o inmueble" />
            </div>
        </section>

        {{-- Progreso --}}
        <section class="flex flex-col gap-4">
            <h2 class="text-overline uppercase text-ink-2">Progreso</h2>
            <div class="flex flex-col gap-2">
                <p class="text-md"><span class="font-bold">340 puntos más</span> y llegas a Oro</p>
                <x-progress-bar :porcentaje="70" relleno="ink" />
            </div>
            <x-progress-bar :porcentaje="40" relleno="brand" />
        </section>

        {{-- Botones --}}
        <section class="flex flex-col gap-4">
            <h2 class="text-overline uppercase text-ink-2">Botones</h2>
            <x-button variant="primario">Crear mi cuenta</x-button>
            <x-button variant="primario" :disabled="true" ayuda="Marca la autorización para continuar">Crear mi cuenta</x-button>
            <x-button variant="secundario">Corregir cédula</x-button>
            <div class="flex items-center gap-4">
                <x-button variant="enlace">Olvidé mi contraseña</x-button>
                <x-button variant="destructivo">Cerrar sesión</x-button>
            </div>
        </section>

        {{-- Campos --}}
        <section class="flex flex-col gap-4">
            <h2 class="text-overline uppercase text-ink-2">Campos</h2>
            <x-field label="Nombre completo" name="nombre" placeholder="Como aparece en tu cédula" />
            <x-field label="Número de cédula" name="cedula" inputmode="numeric" placeholder="Solo dígitos" />
            <x-checkbox name="autorizacion">
                Autorizo el tratamiento de mis datos según la
                <a href="#" class="font-semibold text-brand">política de datos</a>.
            </x-checkbox>
            <x-toggle label="Avisos por correo" :on="true" />
            <x-toggle label="Novedades del programa" :on="false" />
        </section>

        {{-- Chips --}}
        <section class="flex flex-col gap-4">
            <h2 class="text-overline uppercase text-ink-2">Filtros</h2>
            <div class="flex gap-2 overflow-x-auto">
                <x-chip :activo="true">Todos</x-chip>
                <x-chip>Restaurantes</x-chip>
                <x-chip>Heladerías</x-chip>
                <x-chip>Cervecerías</x-chip>
                <x-chip>Colegio</x-chip>
            </div>
        </section>

        {{-- Beneficios --}}
        <section class="flex flex-col gap-4">
            <h2 class="text-overline uppercase text-ink-2">Beneficios</h2>
            <x-benefit-row
                comercio="Cervecería BBC"
                beneficio="20% en cerveza de línea básica"
                meta="Solo martes · 1 por visita"
                icono="heroicon-o-beaker"
            />
            <x-benefit-row
                comercio="El Rancho de Javi"
                beneficio="40% en el kilo de carne"
                estado="bloqueado"
                nivel="oro"
                :faltan="640"
                icono="heroicon-o-fire"
            />
            <div class="flex flex-col">
                <h3 class="pb-1 text-md font-bold">Para usar hoy</h3>
                <x-benefit-list-item comercio="Peluquería Monarca" titulo="70% en tu servicio" icono="heroicon-o-scissors" />
                <x-benefit-list-item comercio="Cervecería BBC" titulo="20% en cerveza" icono="heroicon-o-beaker" />
            </div>
        </section>

        {{-- Avisos --}}
        <section class="flex flex-col gap-4">
            <h2 class="text-overline uppercase text-ink-2">Avisos</h2>
            <x-notice tipo="aviso"><span class="font-bold">180 puntos</span> dejan de contar el 31 de octubre.</x-notice>
            <x-notice tipo="info">Tus puntos siguen contando mientras tengas la suscripción.</x-notice>
        </section>

        {{-- Estados --}}
        <section class="flex flex-col gap-4">
            <h2 class="text-overline uppercase text-ink-2">Estados</h2>

            <div class="flex flex-col gap-3 rounded-md border border-line bg-surface p-3">
                <x-skeleton class="h-4 w-1/3" />
                <x-skeleton class="h-6 w-2/3" />
                <x-skeleton class="h-10 w-full" />
            </div>

            <x-empty-state
                titulo="Aún no tienes movimientos"
                descripcion="Cuando compres en un comercio del grupo, tus puntos aparecen aquí."
            >
                <x-slot:cta>
                    <x-button variant="secundario">Ver comercios</x-button>
                </x-slot:cta>
            </x-empty-state>
        </section>

        {{-- Formato colombiano --}}
        <section class="flex flex-col gap-2">
            <h2 class="text-overline uppercase text-ink-2">Formato colombiano</h2>
            <p class="text-md tabular-nums">Monto: <span class="font-bold">@pesos(85000)</span></p>
            <p class="text-md tabular-nums">Puntos: <span class="font-bold">@puntos(1160)</span></p>
            <p class="text-md tabular-nums">Hora: <span class="font-bold">@hora(now()->setTime(19, 42))</span></p>
        </section>
    </main>
</x-layouts.cliente>
