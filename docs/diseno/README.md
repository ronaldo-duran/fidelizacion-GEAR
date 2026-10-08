# Handoff: Club Aponte Rivera, identidad y pantallas

## Overview
Club Aponte Rivera es el programa de fidelización del Grupo Aponte Rivera: 15 comercios (restaurantes como El Rancho de Javi, heladerías, Cervecería BBC, Colegio Semillitas del Futuro e Inmobiliaria Laura Rivera). Los clientes suman puntos con sus compras, suben de nivel (Bronce, Plata, Oro, Diamante) y usan beneficios que no gastan puntos. Hay tres superficies:
- **PWA del cliente** (móvil, web).
- **Cajero** (celular del comercio): registrar compras y validar beneficios.
- **Administración** (Filament): login y tablero del grupo.

## About the Design Files
Los archivos de `reference/` son **referencias de diseño hechas en HTML**: muestran el aspecto y el comportamiento esperados, no son código de producción. La tarea es **recrearlos en el entorno del repositorio** (Laravel + Filament para el admin; el frontend que use la PWA) con sus patrones y librerías. Si no hay frontend definido para la PWA, elige el más adecuado y consúltalo antes.

- `reference/Club Aponte Rivera - Mockups PDF.dc.html`: **pantallas aprobadas** (6 láminas). Esta es la referencia principal.
- `reference/Club Aponte Rivera.dc.html`: lienzo de exploración con tokens y todas las variantes (1a…1z). Útil para ver estados extra: vacío (1u, 1v), cargando (1w), sin conexión (1x) y cédula no encontrada (1h, 1i). Las variantes no aprobadas (1a, 1b, 1d, 1e) son solo contexto.

Para abrirlos: sirve la carpeta `reference/` con cualquier servidor estático (`npx serve reference`) y abre el archivo; necesitan `support.js` y `deck-stage.js` al lado.

## Fidelity
**Alta fidelidad.** Colores, tipografía, espaciado y textos son finales; recréalos exactamente. Lo único provisional es el logo (placeholder "AR"), que llegará después y solo debería requerir cambiar `--color-brand` y el componente LogoAR.

## Pantallas aprobadas

Numeración del brief original → id en el lienzo.

### 8 · Cajero (prioridad)
Viewport 390×844. Detalle de componentes en `COMPONENTS.md` → Cajero.
- **Registrar compra (1c, modificada):** header de comercio/caja → ModeSwitch → monto grande centrado → teclado 3×4 (con "000") → "¿Quién es el cliente?" → [Cédula] [Escanear QR]. **No mostrar los puntos que suma.**
- **Validar beneficio, escáner (1j, modificada):** ModeSwitch en "Validar beneficio" → visor de cámara → un único botón "Digitar código". **Sin linterna.**
- **Beneficio válido (1k):** pantalla verde completa, "VÁLIDO", beneficio, condiciones, cliente y nivel, "Confirmar entrega" / "Cancelar".
- **Beneficio ya usado (1l):** pantalla roja completa, "YA USADO", cuándo y dónde se usó, cuándo vuelve, "Escanear otro".

### 1 · Registro (1m)
Nombre completo, número de cédula, celular, autorización de datos (checkbox con enlace a la política). El botón "Crear mi cuenta" queda deshabilitado hasta marcar la autorización, con la ayuda "Marca la autorización para continuar".

### 2 · Inicio (1f, modificada)
Bloque superior con el color del nivel actual (en el ejemplo, Oro, `#f4c542`) que incluye header y pestañas; "Julián, eres **Oro**" en 64px; rombos del nivel. Debajo: "860 puntos más para Diamante" + barra de progreso, y la lista "Para usar hoy" con el estilo de lista de 1e (filas de 56 px con icono). **Sin QR en el inicio por ahora.**

### 3 · Catálogo (1n)
Chips de categoría (Todos, Restaurantes, Heladerías, Cervecerías, Colegio). Secciones "Puedes usarlos" y "Se abren al subir de nivel". Los bloqueados no se atenúan: llevan la pastilla del nivel con candado y "Te faltan N pts".

### 4 · Uso del beneficio (1o) y ya usado este mes (1p)
1o: bloque verde "LISTO PARA USAR" con QR, código (BBC-4821), hora en vivo con segundos (impide usar capturas), nombre y nivel. 1p: "Ya lo usaste este mes", fecha y sede, "Vuelve el 1 de octubre", y otros beneficios para hoy.

### 5 · Historial (1q)
Puntos de los últimos 12 meses, aviso de puntos por vencer y movimientos agrupados por mes. Las devoluciones van en rojo con signo menos.

### 6 · Niveles (1r) y suscripción Diamante (1s)
Los 4 niveles como tarjetas con su color; el actual está marcado "TU NIVEL". Diamante explica sus tres vías: 3.000 pts, suscripción o compra de inmueble con Inmobiliaria Laura Rivera. 1s: plan mensual ($49.900) o anual ($499.000), aviso de que los puntos siguen contando y "Continuar al pago".

### 7 · Perfil (1t)
Datos (cédula enmascarada), avisos por WhatsApp (toggles), documentos (reglamento, política de datos) y cerrar sesión.

### 10 · Login (1y)
Para cajeros y administradores. Usuario, contraseña con mostrar/ocultar, "Mantener la sesión en este celular", "Ingresar", "Olvidé mi contraseña".

### 9 · Tablero del grupo (1z)
1024 px, sobre Filament. Sidebar, selector de periodo, 4 KPI, distribución de clientes por nivel (barra apilada) y tabla de comercios por actividad; los comercios sin actividad se resaltan.

## Interactions & Behavior
- **Cajero, registrar:** el monto se escribe con el teclado ("000" agrega tres ceros). Luego Cédula abre el teclado de cédula y Escanear QR abre la cámara. Si la cédula no existe, el monto queda guardado y hay tres salidas: guardar pendiente y seguir, registrar al cliente ahora (el cliente escanea un QR de registro) o corregir la cédula (ver 1h, 1i). La confirmación a pantalla completa vuelve sola al inicio en 5 s y permite anular la compra (1g).
- **Cajero, validar:** escaneo continuo; al leer, muestra la pantalla de resultado. "Confirmar entrega" registra el uso.
- **Uso de beneficio (cliente):** la hora avanza cada segundo; el QR/código expira y se renueva en el servidor.
- **Registro:** validar la cédula (solo dígitos, de 6 a 10) y el celular colombiano (10 dígitos que empiezan por 3).
- **Estados:** cargando = skeleton en `--color-sunken` (1w); vacío con CTA (1u, 1v); sin conexión = franja oscura superior + tarjeta con "Reintentar" (1x).
- **Transiciones:** sobrias, de 150 a 200 ms ease-out en cambios de pestaña y de estado. Las pantallas de resultado del cajero aparecen sin animación larga (máximo 120 ms).

## State Management (mínimo)
- Cliente: `user`, `tier`, `points12m`, `pointsToNext`, `nextTier`, `benefits[]` (estado: disponible / bloqueado / usado este mes, con `requiredTier` y `nextAvailableAt`), `history[]`, `expiringPoints`.
- Cajero: `mode` (registrar | validar), `amount`, `customerLookup` (idle | found | not_found), `pendingPurchase`, `scanResult` (valid | used | invalid).
- Admin: periodo seleccionado, KPI, distribución por nivel y ranking de comercios.

## Design Tokens
En `tokens/tokens.css` (variables CSS), `tokens/tokens.json` y `tokens/tailwind.preset.js` (también sirve para el tema de Filament). Resumen:
- Marca `#1f5c4f` (strong `#163f37`, soft `#e2ede8`). Tinta `#231d18` / `#5c534b`. Papel `#faf7f2`. Línea `#e7e0d6`.
- Éxito `#0f6b3a`, peligro `#a31d15`, aviso `#9a4f00` / `#fff6e6`. Tinta del cajero `#0f0d0b`, teclas `#f1ede7`.
- Niveles: Bronce `#ecdcc8`/`#5a3a1e`; Plata `#dde3e6`/`#2e3a42`; Oro `#f4c542`/`#3d2a00`; Diamante `#1c1a36`/`#f5f1ff` con acento `#9d8cff`.
- Tipografía: Bricolage Grotesque (display) y Figtree (UI). Escala: 128 / 56 / 32 / 22 / 17 / 15 / 13 / 12.
- Espaciado: 4, 8, 12, 16, 20, 24, 32, 48. Radios: 6, 8, 14, 20, 24, 999.
- Toque: 44 px (cliente/admin), 64 px (cajero).

## Supuestos de negocio (confirmar)
- 1 punto por cada $1.000. Los puntos cuentan durante 12 meses móviles.
- Umbrales: Plata 500, Oro 1.500, Diamante 3.000.
- La suscripción solo existe para Diamante ($49.900 al mes o $499.000 al año).
- Las cifras del tablero y los nombres de comercio Heladería La Nevera, Heladería Copo, Heladería Polar y Restaurante Fogón son de ejemplo.

## Assets
- Fuentes: Google Fonts (Bricolage Grotesque y Figtree).
- Iconos: Heroicons v2 (MIT), https://heroicons.com. En Laravel: `blade-ui-kit/blade-heroicons` (Filament ya los incluye).
- Logo: pendiente; se usa el placeholder "AR".
- Los QR de los mockups son ilustrativos; generarlos con una librería real.

## Files
- `PROMPT.md`: prompt inicial para pegar en Claude Code.
- `.claude/skills/club-ar-ui/SKILL.md`: skill con las reglas de UI (copiar a `.claude/skills/` del repo).
- `COMPONENTS.md`: especificación de componentes.
- `tokens/`: tokens en CSS, JSON y preset de Tailwind.
- `reference/`: mockups en HTML (`support.js` y `deck-stage.js` son los runtimes necesarios para abrirlos).
