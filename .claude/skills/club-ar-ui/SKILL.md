---
name: club-ar-ui
description: Identidad visual y reglas de interfaz del Club Aponte Rivera (programa de fidelización). Úsala siempre que crees o modifiques vistas, componentes Blade/Livewire/React, el tema de Filament o estilos de la PWA del cliente, del cajero o del panel admin.
---

# Club Aponte Rivera: reglas de UI

Antes de escribir UI, lee `docs/diseno/COMPONENTS.md`, `docs/diseno/README.md` y `docs/diseno/NOTAS.md` (puntos del diseño que chocan con el contrato). Los HTML en `docs/diseno/reference/` son la verdad visual (`npx serve docs/diseno/reference` y abrir en el navegador para comparar).

## Dónde viven los tokens en el código

- `resources/css/design/tokens.css`: colores y fuente display, compartidos por la PWA, el cajero y Filament. Es la traducción a Tailwind v4 de `docs/diseno/tokens/tokens.css`; si uno cambia, el otro también.
- `resources/css/app.css`: build de la PWA y el cajero. Agrega la escala tipográfica, radios y alturas táctiles (`text-2xl`, `rounded-md`, `h-touch`, `h-touch-cashier`, `h-input`, `h-btn`).
- `resources/css/filament/admin/theme.css`: tema del panel. Solo toma colores y fuentes; **no** aplicar la escala de la PWA en Filament.
- `app/Providers/Filament/AdminPanelProvider.php`: paleta primaria de Filament anclada a `--color-brand`.
- Nombres de clase: `bg-brand`, `text-ink-2`, `bg-tier-oro-bg`, `text-tier-oro-ink`, `text-cashier-ink`, `bg-cashier-key`, `font-display`. Para pintar por nivel dinámico usa `style="background: var(--color-tier-{{ $nivel }}-bg)"` (las variables siempre existen en `:root`).
- Fuentes auto-alojadas con Fontsource (`resources/css/design/fuentes.css`); no agregar `<link>` a Google Fonts.
- Los umbrales de nivel de `tokens.json` son supuestos de diseño, no reglas: los números de negocio viven en `tests/Fixtures/`.

## Reglas duras

1. **Nunca hardcodees colores, radios ni tamaños.** Usa las variables CSS o las clases del preset de Tailwind (`bg-brand`, `text-ink-2`, `rounded-md`, `bg-tier-oro-bg`…).
2. **Solo dos familias:** Bricolage Grotesque (`font-display`) para títulos, cifras y nombres de nivel; Figtree (`font-sans`) para todo lo demás. Códigos de beneficio en mono.
3. **Un solo color de marca:** `--color-brand`. Debe poder reteñirse cambiando solo esa variable (y `-strong` / `-soft`).
4. **Niveles sin metalizados ni degradados.** La jerarquía sale del contraste creciente (Bronce → Plata → Oro → Diamante) y de 1 a 4 rombos (TierDiamonds). Diamante es el único invertido (fondo oscuro con acento `#9d8cff`).
5. **Zonas táctiles:** ≥ 44 px en cliente y admin; ≥ 64 px en todo el flujo del cajero.
6. **Contraste mínimo AA (4.5:1) en texto.** No uses opacidad para "apagar" texto; usa `ink-2`.
7. **Beneficios bloqueados no se atenúan.** Se muestran completos, con la pastilla del nivel que los abre (candado de 12) y "Te faltan N pts".
8. **Cajero:** fondo blanco, tinta `cashier-ink`, una acción por pantalla, sin elementos decorativos. Resultados (registrada / válido / ya usado) a pantalla completa en `success` o `danger` para girar el celular hacia el cliente.
9. **Navegación del cliente:** pestañas de texto bajo el encabezado (Inicio · Beneficios · Historial · Niveles). Sin barra inferior estilo app nativa.
10. **Iconos:** Heroicons v2 outline 24 (solid solo en íconos de resultado 96 y badges). Nada de emoji.
11. **Cifras:** `font-variant-numeric: tabular-nums`. Formato colombiano: `$85.000`, `1.160 pts`, `7:42 p. m.`, `$19,5 M`.
12. **Layout con flex/grid + gap**, no márgenes sueltos entre hermanos.

## Stack de la interfaz

- PWA del cliente y cajero: Blade + Livewire 4 + Alpine + Tailwind v4 (build de `resources/css/app.css`).
- Administración: Filament 5. Sin plugins de pago.
- Iconos: Heroicons v2 con `blade-ui-kit/blade-heroicons` (Filament ya lo trae): `<x-heroicon-o-qr-code class="size-6" />`.

## Decisiones de producto ya cerradas (no reabrir)

- Cajero: al abrir, selector "Registrar compra" / "Validar beneficio". Registrar = **monto primero** (teclado con 000), luego identificar: Cédula (por defecto) o Escanear QR. **No mostrar los puntos que suma** en esa pantalla.
- Escáner de validar beneficio: solo "Digitar código" como alternativa. **Sin linterna.**
- Inicio del cliente: bloque superior con el color del nivel, nombre del nivel enorme, puntos que faltan y "Para usar hoy" como lista. **Sin QR personal en el inicio por ahora.**
- Uso de beneficio: QR + código alfanumérico + **hora en vivo** (segundos) para impedir capturas.
- Cliente no registrado en caja: el monto nunca se pierde. Opciones: guardar pendiente y seguir, registrar ahora, corregir cédula.

## Textos

Español de Colombia, tuteo, frases cortas y concretas. Sin signos de exclamación en estados de sistema. Usa las cadenas exactas de `COMPONENTS.md` y los HTML de referencia.

## Checklist antes de entregar una vista

- [ ] Solo tokens, sin hex sueltos
- [ ] Zonas táctiles cumplen 44/64
- [ ] Estados: cargando (skeleton en `sunken`), vacío, error con "Reintentar"
- [ ] Probado a 360 px de ancho (cliente/cajero) y 1024 px (admin)
- [ ] Comparado contra el HTML de referencia
