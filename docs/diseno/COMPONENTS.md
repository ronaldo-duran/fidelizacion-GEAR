# Componentes: Club Aponte Rivera

Medidas en px. Colores con nombres de `tokens/tokens.css`. Iconos: Heroicons v2 (outline 24 por defecto; `solid` donde se indica).

---

## Primitivas compartidas

### LogoAR (placeholder hasta recibir el logo)
- 32×32 (40×40 en registro), radio 8 (10 en registro), fondo `ink`, texto "AR" en `paper`, display 700 12px (14px en registro).
- Al lado: "Club Aponte Rivera", ui 700 15px.

### TierDiamonds
- 4 cuadrados rotados 45°, 9–12 px, separados 4–6 px.
- Llenos = número de nivel (Bronce 1, Plata 2, Oro 3, Diamante 4). Vacíos = borde 1.5px del `ink` del nivel, opacidad 0.45.
- En Diamante todos se rellenan con `tier-diamante-accent`.

### TierBadge (pastilla)
- Pastilla de radio 999, padding 3–4 / 10–12, ui 700 13–16px, `bg`/`ink` del nivel.
- Variante "TU NIVEL": fondo `ink` del nivel, texto blanco, 11px 700.

### TierCard (fila en pantalla Niveles)
- Radio 16, padding 12/14, `bg`/`ink` del nivel. Nombre en display 700 20px, meta en 13px.
- Nivel actual: `box-shadow: 0 0 0 2px <ink del nivel>` + TierBadge "TU NIVEL".
- Diamante: `inset 0 0 0 2px #9d8cff`, nombre 24px, e incluye texto de las tres vías y botón claro (48 de alto, radio 12, fondo `#f5f1ff`, texto `#1c1a36`).

### ProgressBar
- Pista 8–10 px de alto, radio la mitad, `sunken`. Relleno `ink` (cliente) o `brand`.
- Encima: "**340 puntos más** y llegas a Oro" (15–16px, cifra en 700).
- Variante recorrido de niveles: 3 segmentos (gap 4) + fila de 4 etiquetas (Bronce, Plata, Oro, Diamante, 12px 600 `ink-2`; el nivel actual en `ink`).

### ClientHeader + TabNav (web, no barra inferior nativa)
- Header: padding 12/12/4/20, LogoAR a la izquierda, botón de perfil 44×44 (`user-circle` 28) a la derecha.
- Tabs: Inicio · Beneficios · Historial · Niveles. ui 600 15px, padding 12/8, `ink-2`; activa en `ink` con `box-shadow: inset 0 -2px 0 brand`. Borde inferior 1px `line`.
- En el inicio Oro (fondo amarillo) los tabs usan el `ink` del nivel, inactivos a opacidad 0.8, borde `rgba(61,42,0,.2)`.

### BenefitRow (catálogo, listas)
- Tarjeta `surface`, borde 1px `line`, radio 14, padding 12, gap 12, alto mínimo 44.
- Icono en cuadro 44×44 radio 10: disponible = `brand-soft`/`brand`; bloqueado = `bg`/`ink` del nivel que lo abre.
- Texto: comercio (13px 600 `ink-2`), beneficio (15px 700, lh 1.25), meta (13px `ink-2`).
- Disponible: `chevron-right` 20 al final.
- Bloqueado: el contenido NO se atenúa. Debajo del título: pastilla del nivel con `lock-closed` 12 + "Te faltan N pts" (13px 600 `ink-2`).

### BenefitListItem (versión sin tarjeta, usada en Inicio)
- Fila de 56 de alto, divisor 1px `line`, icono 40×40 radio 10 `brand-soft`. Título 15px 700, comercio 13px `ink-2`.

### Chip de filtro
- 44 de alto, padding 0/16, radio 999. Activo: fondo `ink`, texto blanco 700 14px. Inactivo: borde 1.5px `#d6cfc5`, 600 14px. Scroll horizontal.

### TextField
- Etiqueta arriba (15px 600, gap 6). Campo 52 de alto, radio 12, borde 1.5px `field`, fondo blanco, texto 17px 400.
- Foco: borde 2px `brand`. Placeholder `#8a8178`.

### Checkbox
- 26×26 (24 en login), radio 6. Sin marcar: borde 2px `ink-2`. Marcado: fondo `brand`, check blanco 16.

### Toggle
- 48×28, radio 14. On `brand`, off `#d6cfc5`, perilla blanca 22.

### Button
- Primario: 56 de alto, radio 14, `brand`, texto blanco ui 700 18px.
- Deshabilitado: fondo `#e4ded5`, texto `#7d746b`, más una línea de ayuda debajo (14px `ink-2`): "Marca la autorización para continuar".
- Secundario: borde 1.5px `ink`, 48 de alto, radio 12.
- Enlace: 600 15px `brand`, alto mínimo 44.
- Destructivo de texto: 700 15px `danger`.

### Notice
- Aviso: `warn-soft`, borde 1px `warn-line`, radio 14, padding 12/14, icono 22, texto 14px en `#6b3b00`, frase clave en 700.
- Info: `brand-soft`, texto `brand-strong`, icono `information-circle`.

---

## Cajero (pantalla 8): móvil, prioridad absoluta en velocidad

Tinta `cashier-ink`. Toda zona táctil ≥ 64 px. Una mano, de pie, con luz de local.

### CashierHeader
- Comercio y sede (13px 600 `cashier-ink-2`) sobre caja y cajero (15px 700). Menú `bars-3` 26 en 44×44.

### ModeSwitch (segmentado)
- Contenedor `sunken`, radio 14, padding 4, gap 4, 2 columnas.
- Opción 52 de alto, radio 11, 700 16px, icono 22. Activa: fondo `cashier-ink`, texto blanco.
- Opciones: "Registrar compra" (`plus-circle`) · "Validar beneficio" (`qr-code`).

### AmountDisplay (aceptado: 1c, monto primero)
- Centrado. Etiqueta "Monto de la compra" 15px 700 `cashier-ink-2`, cifra display 700 60px tabular, tracking -0.02em.
- **No mostrar los puntos que suma** (decisión del cliente).

### Keypad
- Grilla 3×4, gap 8, teclas 60–64 de alto, radio 14, fondo `cashier-key`, dígito ui 600 28px.
- Última fila: "000" (600 24px) · "0" · borrar (`backspace` 28, sin fondo).

### IdentifyActions
- Etiqueta "¿Quién es el cliente?" 14px 700 centrada.
- Dos botones de 64, radio 16, fondo `cashier-ink`, texto blanco 700 18px: "Cédula" (`identification`) y "Escanear QR" (`camera`). Cédula es el método por defecto.

### Scanner (Validar beneficio, 1j)
- Visor con margen 16, radio 20, fondo `#1b1916`. Marco de 240 con 4 esquinas (5px blanco, radio 12) y línea de escaneo 3px `#5fd08f`.
- Texto "Apunta al código del cliente" 18px 700 blanco.
- Abajo, un solo botón a todo el ancho: "Digitar código" (`hashtag`). **Sin linterna.**

### ResultScreen (pantalla completa, para girar el celular hacia el cliente)
- Válido (1k): fondo `success`, `check-circle` solid 96, "VÁLIDO" text-3xl, beneficio display 700 34px, condiciones 18px, nombre del cliente 24px 700 + TierBadge. CTA blanco de 64 "Confirmar entrega" + enlace "Cancelar".
- Ya usado (1l): fondo `danger`, `x-circle` solid 96, "YA USADO", beneficio 28px, "Se usó el 3 de septiembre, 7:42 p. m., en Cervecería BBC Norte." 19px, "Disponible de nuevo el 1 de octubre" 700. CTA blanco "Escanear otro".

---

## Cliente (PWA)

### TierHero (inicio aceptado: 1f sin QR)
- Bloque superior a todo el ancho con `bg` del nivel, que contiene ClientHeader y TabNav.
- "Julián, eres" 15px 600 + nombre del nivel display 800 64px/0.95, tracking -0.02em. TierDiamonds 12px a la derecha. Padding inferior 28.
- **QR personal fuera del inicio por ahora.**
- Debajo: cifra display 700 40px "860" + "puntos más para **Diamante**" 16px, y ProgressBar 8px.
- Luego "Para usar hoy" (16px 700) con BenefitListItem (estilo de 1e).

### BenefitRedeem (4, 1o)
- Header con atrás 44×44 + nombre del comercio 17px 700.
- Título display 700 32px + condiciones 15px `ink-2`.
- Bloque `brand`, radio 24, padding 20: "LISTO PARA USAR" display 800 22px con `check-badge` solid; QR 200 en blanco con radio 16; código mono 700 30px tracking .08em (p. ej. BBC-4821); punto vivo verde `#7fe0a8` + hora en vivo tabular ("Hoy · 7:42:18 p. m.") que evita capturas; nombre y nivel.
- Ayuda centrada: "Muestra esta pantalla al cajero. Él escanea el código y listo."

### BenefitUsed (1p)
- Bloque `sunken`, radio 24: círculo blanco 64 con check `brand`, "Ya lo usaste este mes" display 700 24px, fecha y sede, pastilla blanca "Vuelve el 1 de octubre" con `calendar`.
- Debajo, "Otros para hoy" con BenefitRow.

### HistoryList (5, 1q)
- Resumen: "Puntos de los últimos 12 meses", cifra display 700 40px.
- Notice de vencimiento: "180 puntos dejan de contar el 31 de octubre."
- Agrupar por mes (overline). Fila de 56: comercio 15px 700, fecha + tipo 13px, puntos 700 17px tabular; `success-ink` si suma, `danger` si es devolución (−).

### Subscription (1s)
- Tarjeta Diamante, opciones de plan con radio (seleccionado: borde 2.5px `brand`, radio relleno con borde de 7px), Notice info, CTA "Continuar al pago · $49.900" y "Tarjeta de crédito, débito o PSE".

### Profile (7, 1t)
- Avatar 56 con iniciales en `bg`/`ink` del nivel. Grupos con overline: Mis datos (cédula enmascarada •••• 6789), Avisos por WhatsApp (toggles), Documentos. "Cerrar sesión" en `danger`.

---

## Administración

### Login (10, 1y)
- Placeholder de logo 120×56 punteado. Título "Ingreso al sistema" display 700 30px. Usuario, contraseña con botón de ojo 44×44, "Mantener la sesión en este celular", CTA "Ingresar", "Olvidé mi contraseña".
- Franja inferior de 6px con los 4 colores de nivel.

### Dashboard (9, 1z): sobre Filament
- Sidebar de 208, fondo blanco, item de 40, radio 8; activo `brand-soft`/`brand-strong` 700.
- Ítems: Tablero, Clientes, Transacciones, Beneficios, Comercios, Niveles y reglas, Membresías.
- Selector de periodo: "Septiembre 2026 · vs. agosto".
- 4 KPI (tarjeta radio 14, padding 16): Clientes inscritos, Transacciones, Beneficios entregados, Valor de beneficios. Cifra display 700 30px tabular, delta 13px 600 (verde, o `warn` cuando sube el costo).
- Clientes por nivel: barra apilada de 28 alto, radio 8, gap 2, colores de nivel + leyenda con conteo y %. Desglose del origen de Diamante (puntos / suscripción / inmueble).
- Tabla "Comercios por actividad": cabecera `#f6f2ec` overline; filas de 44 tabulares. Comercio sin actividad resaltado con fondo `warn-soft` y "Sin registros hace N días" en `warn-ink`.
