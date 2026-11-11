# E8-04 · PWA: catálogo y uso del beneficio

**Épica:** E8 · **Horas estimadas:** 12 · **Riesgo:** medio

## Objetivo

El cliente explora los beneficios de todos los comercios, ve cuáles puede
usar hoy y cuáles se abren al subir de nivel, y muestra en caja un código
que cambia solo para usar uno.

## Fuera de alcance

- Calcular el estado de un beneficio. Lo da `BeneficiosParaCliente` (E5-01);
  aquí solo se lee.
- Firmar, rotar y validar el código: `IssueCredencial` y
  `ValidateCredencial` (E4-04). Registrar la redención y su valor otorgado:
  `RedeemBeneficio` (E5-02). Esta feature no escribe redenciones.
- El QR personal para identificarse al registrar una compra: queda fuera de
  la PWA por ahora (decisión de diseño del inicio, E8-03).
- Agregar la fecha de nacimiento: el perfil es de E8-05. Aquí solo se dice
  que falta.
- Buscar beneficios por texto, favoritos o mapa de sedes.
- Avisos de beneficios nuevos: E7-01.

## Reglas de negocio

1. **Los puntos son estatus** (regla dura 3.1). Usar un beneficio no gasta
   puntos. Ningún texto dice "canjear", "costo", "precio en puntos" ni
   "saldo". El verbo es **usar**.
2. **Una sola fuente de estado.** El estado, el valor para el nivel, el
   nivel que lo abre, los puntos que faltan y la fecha en que vuelve salen de
   `BeneficiosParaCliente::para()` con el nivel efectivo del cliente (regla
   dura 3.5). Ninguna vista ni componente vuelve a calcular nada de eso.
3. **Catálogo (`/beneficios`).** Lista los beneficios que
   `BeneficiosParaCliente` devuelve para el cliente hoy, en tres secciones.
   Una sección sin beneficios no se muestra:
   - **"Puedes usarlos"**: los `disponible`. Al lado del título, "N con
     {nivel}". Cada fila dice el valor para su nivel ("20 % de descuento") y,
     si el nivel siguiente da más, "Con Oro, 40 %".
   - **"Por ahora no"**: los `usado`, `sin_cupos` y `no_disponible_hoy`. Cada
     fila dice por qué, con los textos del fixture: "Ya lo usaste este mes ·
     Vuelve el 1 de diciembre", "Se acabaron los cupos", "Disponible el 15 de
     marzo de 2027" o "Necesitamos tu fecha de nacimiento".
   - **"Se abren al subir de nivel"**: los `bloqueado`, con la pastilla del
     nivel que los abre, el candado y "Te faltan N pts". **No se atenúan**
     (`club-ar-ui`, regla 7).
4. **Orden.** Dentro de "Puedes usarlos" y "Por ahora no", por nombre del
   comercio y luego del beneficio, como "Para usar hoy" en E8-03. En "Se
   abren al subir de nivel", primero el nivel más cercano y luego el mismo
   orden.
5. **Chips de categoría.** "Todos" y una ficha por cada categoría que tenga
   al menos un beneficio en el catálogo de ese cliente, por nombre de la
   categoría. Las fichas salen de la tabla `categorias` por el comercio del
   beneficio; ninguna categoría va escrita en la vista. Elegir una filtra las
   tres secciones y queda en la URL (`?categoria=cerveceria`). Una categoría
   que no existe o no tiene beneficios muestra "Todos".
6. **Detalle (`/beneficios/{beneficio}`).** Encabezado con atrás y el nombre
   del comercio, el nombre del beneficio y su `condiciones_texto`. Lo que
   sigue depende del estado:
   - **Disponible (1o):** el bloque "LISTO PARA USAR" con el QR, el código
     corto, la hora en vivo, el nombre del cliente y su nivel. Debajo: "Muestra
     esta pantalla al cajero. Él escanea el código y listo."
   - **Usado (1p):** "Ya lo usaste este mes" (o "este año", o "Ya lo usaste"
     si el límite es de por vida), la fecha y el lugar del último uso
     ("3 de noviembre · Restaurante Sabor Caribe · Sede principal") y, si
     vuelve, la pastilla "Vuelve el 1 de diciembre". Debajo, "Otros para hoy".
   - **Sin cupos o no disponible hoy:** el motivo de la regla 3, sin QR, y
     "Otros para hoy".
   - **Bloqueado:** la pastilla del nivel que lo abre, "Te faltan N pts" y el
     enlace "Ver cómo subir de nivel" a `/niveles`. Sin QR.
7. **Quién puede abrir un detalle.** Solo un beneficio que
   `BeneficiosParaCliente::deBeneficio()` devuelve para ese cliente. Uno
   inactivo, vencido, de un comercio inactivo o que su nivel no recibe da
   404, igual que un id que no existe. Nunca se emite un código para un
   beneficio que no está `disponible`.
8. **El código que se muestra** (regla dura 3.12):
   - Se emite con `IssueCredencial`, propósito `beneficio`, con el beneficio
     y su comercio. Nunca se reutiliza ni se guarda en el navegador.
   - Se renueva solo, sin recargar la página, cada `refresh_seconds` de
     `tests/Fixtures/qr-escenarios.json`, antes de que venza.
   - El anterior deja de validar cuando vence (`ttl_seconds` más la
     tolerancia de reloj de E4-04). No se anula al emitir el nuevo, porque un
     cajero que ya lo escaneó tiene el margen de E5-02 (regla 11) para
     confirmar.
   - Solo se renueva con la pestaña visible. Al volver a ella, se pide uno
     nuevo de inmediato.
   - Si al renovar el beneficio dejó de estar disponible (por ejemplo, el
     cajero acaba de entregarlo), la pantalla pasa al estado nuevo sin QR.
9. **Hora en vivo.** "Hoy · 7:42:18 p. m.", con segundos, en
   `America/Bogota` (regla dura 3.9) y formato colombiano. Arranca con la
   hora del servidor y avanza en el navegador, para que una captura de
   pantalla se delate.
10. **Sin conexión o código vencido.** Si el navegador pierde la conexión, o
    la hora de vencimiento del código pasa sin que llegue uno nuevo, el QR y
    el código corto se ocultan. En su lugar: "Sin conexión. Tu código aparece
    cuando vuelvas a tener señal." Nunca queda a la vista un código vencido
    como si sirviera. El service worker no guarda estas pantallas (E8-01).
11. **Otros para hoy.** Los `disponible` del cliente, sin el beneficio que se
    está viendo, en el orden de la regla 4, hasta el máximo del fixture
    (`detail.others_today.max_items`).
12. **Enlaces que se activan.** La pestaña Beneficios y "Ver los N" del inicio
    (E8-03) empiezan a enlazar solos, porque usan `Route::has()`. Cada fila de
    "Para usar hoy" lleva a su detalle.
13. **Estados.**
    - **Cargando:** el catálogo se pinta en la primera respuesta. En el
      detalle, mientras llega el primer código, un skeleton sobre `sunken`
      del tamaño del QR.
    - **Vacío:** un cliente sin ningún beneficio en el catálogo, o una
      categoría sin beneficios disponibles, ve "Todavía no hay beneficios
      aquí" con el enlace a `/niveles`.
    - **Error al emitir el código:** "No pudimos generar tu código" con
      "Reintentar".
14. **Sin N+1.** El catálogo hace un número fijo de consultas sin importar
    cuántos beneficios, comercios o categorías haya. Cada renovación del
    código hace un número fijo de consultas.

## Criterios de aceptación

- [ ] Dado el cliente Plata del fixture en el día del fixture, cuando abre
      `/beneficios`, entonces ve las tres secciones con los beneficios, el
      orden y los textos del fixture, y "3 con Plata".
- [ ] Los bloqueados muestran la pastilla del nivel con candado y "Te faltan
      N pts" del fixture, sin opacidad.
- [ ] Dadas las categorías del fixture, entonces las fichas son "Todos" más
      las que tienen beneficios; elegir "Cervecería" deja solo los de esa
      categoría y una sección vacía no se muestra.
- [ ] Dado un beneficio disponible, cuando el cliente abre su detalle,
      entonces ve el QR, el código corto, la hora en vivo, su nombre y su
      nivel, y existe una credencial de propósito `beneficio` para ese
      beneficio y su comercio.
- [ ] Cuando pasa el intervalo de renovación, entonces el componente emite
      una credencial nueva con otro código corto, sin recargar. La anterior
      se rechaza como vencida al pasar su vida más la tolerancia.
- [ ] Dado un detalle abierto, cuando el cajero entrega ese beneficio y el
      límite del cliente se agota, entonces la siguiente renovación muestra
      "Ya lo usaste este mes" y no emite otra credencial.
- [ ] Dado un beneficio usado, bloqueado, sin cupos o no disponible hoy,
      cuando el cliente abre su detalle, entonces ve el texto del fixture y no
      se emite ninguna credencial.
- [ ] Dado un beneficio inactivo, de un comercio inactivo o que el nivel del
      cliente no recibe, cuando el cliente pide su detalle, entonces recibe 404.
- [ ] Dado el navegador sin conexión, o un código vencido sin renovar,
      entonces el QR y el código se ocultan y se ve el aviso de la regla 10
      (E2E).
- [ ] Ningún texto de las dos pantallas contiene "canjear", "costo" ni
      "saldo".
- [ ] El catálogo hace el mismo número de consultas con 3 que con 12
      beneficios.
- [ ] Dado un cliente sin sesión, cuando pide `/beneficios` o un detalle,
      entonces va a `/ingresar`.
- [ ] E2E en celular a 360 px: inicio → Beneficios → detalle de un
      disponible con QR y hora en vivo → el QR cambia solo. Se compara a mano
      contra `docs/diseno/reference/` (1n, 1o y 1p).

## Fixtures

`tests/Fixtures/catalogo-pwa-escenarios.json`: el día, el cliente Plata, un
catálogo de prueba con un beneficio en cada estado, las secciones y fichas
esperadas, el filtro por categoría, los textos de cada estado y el máximo de
"Otros para hoy". Los tiempos del código vienen de
`tests/Fixtures/qr-escenarios.json` y los umbrales, de la regla `niveles`.

## Propuesta técnica (pendiente de revisión)

- **Rutas** detrás de `auth:cliente`: `cliente.beneficios` (`/beneficios`)
  y `cliente.beneficio` (`/beneficios/{beneficio}`).
- **Livewire:**
  - `App\Livewire\Cliente\Beneficios`: el catálogo, con
    `#[Url] categoria`.
  - `App\Livewire\Cliente\UsarBeneficio`: el detalle. El id del beneficio
    va en una propiedad `#[Locked]`. `wire:poll.visible` con
    `refresh_seconds` llama a `renovar()`.
- **Lectura:** `App\Beneficios\CatalogoCliente` arma las secciones, las
  fichas y "Otros para hoy" a partir de `BeneficiosParaCliente`. Los textos
  de estado salen de un solo lugar, compartido por las filas y el detalle.
- **Código:** `renovar()` llama a `BeneficiosParaCliente::deBeneficio()` y,
  solo si está `disponible`, a `IssueCredencial`. El SVG sale de
  `QrRenderer` (E4-04). Al componente le llegan solo el SVG, el código corto
  y el vencimiento: el token no queda en ninguna propiedad pública.
- **Hora y vencimiento en el navegador:** Alpine recibe la hora del servidor
  y el vencimiento en milisegundos, calcula el desfase con el reloj del
  teléfono y oculta el código al pasar el vencimiento o con el evento
  `offline`.
- **Componentes:** se reutilizan `benefit-row`, `benefit-list-item`, `chip`,
  `tier-badge`, `skeleton` y `empty-state`. `chip` pasa a aceptar `href`.
  `benefit-row` muestra el motivo de "Por ahora no" en su meta y pasa a ser
  enlace en los disponibles. El único token nuevo es el del punto vivo
  (decisión 7).
- Las filas vencidas de `credenciales_qr` ya las purga
  `PurgeCredenciales` cada noche (E3-05): una pantalla abierta crea una cada
  `refresh_seconds`.

## Decisiones a confirmar en la revisión

1. **Sección "Por ahora no".** El diseño solo trae "Puedes usarlos" y "Se
   abren al subir de nivel". Los usados, sin cupos y no disponibles hoy
   necesitan un lugar: si no se listan, el cliente cree que desaparecieron.
2. **Fichas de categoría.** El diseño fija cinco fichas en plural
   (Restaurantes, Heladerías…). Se propone sacarlas de `categorias` con el
   nombre que tengan allí, en singular ("Restaurante"), y solo las que
   tengan beneficios. Así una categoría nueva aparece sola. Si el cliente
   quiere el plural, se agrega un campo a `categorias`.
3. **El código anterior no se anula al renovar** (regla 8): sigue sirviendo
   hasta vencer, unos 35 segundos. Anularlo dejaría sin margen al cajero que
   ya lo escaneó. El criterio del issue "el anterior deja de validar" se
   cumple al vencer.
4. **Máximo de "Otros para hoy":** 3 (supuesto del fixture). El diseño
   muestra dos.
5. **El valor en cada fila** usa el mismo texto que ve el cajero ("20 % de
   descuento"), más "Con Oro, 40 %" si el nivel siguiente da más. El diseño
   no define la meta de la fila.
6. **Sin fecha de nacimiento**, la fila y el detalle dicen "Necesitamos tu
   fecha de nacimiento" sin enlace, hasta que E8-05 traiga el perfil
   (`TODO(E8-05)`).
7. **Un token nuevo, `--color-live`** (`#7fe0a8`), para el punto vivo junto
   a la hora. Ningún token actual da un verde claro que contraste sobre
   `brand`. Se agrega en `resources/css/design/tokens.css` y en
   `docs/diseno/tokens/tokens.css`.

## Reglas duras de CLAUDE.md que aplican

- 3.1 Los puntos son estatus: usar no gasta puntos ni se dice "canjear".
- 3.5 Se lee el nivel efectivo, nunca una de sus fuentes.
- 3.9 La hora en vivo y las fechas en `America/Bogota`, formato colombiano.
- 3.11 El valor otorgado lo guarda E5-02 al entregar; esta pantalla no
  escribe redenciones.
- 3.12 QR firmado, que rota y nunca queda a la vista vencido.
- 3.13 La hora la pone la aplicación (`now()`).
- Sección 7: lógica en clases de lectura y Actions, componentes delgados,
  zonas táctiles de 44 px, tuteo, una prueba E2E por pantalla nueva.

## Notas de interfaz

- Diseño: pantallas 3 (1n) y 4 (1o, 1p) en `docs/diseno/reference/`.
  Componentes BenefitRow, Chip de filtro, BenefitRedeem y BenefitUsed de
  `docs/diseno/COMPONENTS.md`.
- Las fichas van en una fila con scroll horizontal, de 44 px de alto. La
  activa en `ink` con texto blanco.
- BenefitRedeem: bloque `brand`, radio 24. QR de 200 px sobre blanco con
  radio 16, que se lee a medio metro. Código en mono 700 a 30 px. El punto
  vivo verde va junto a la hora, con el token de la decisión 7; nada de hex
  en la vista.
- BenefitUsed: bloque `sunken`, radio 24, círculo blanco de 64 con el check
  y la pastilla blanca "Vuelve el…" con `calendar`.
- Skill `club-ar-ui`. Probar a 360 px.

## Depende de

E8-01 (componentes y PWA), E8-02 (sesión del cliente), E8-03 (pestañas e
inicio), E5-01 (estado de los beneficios), E4-04 (credencial y QR) y E5-02
(redención y último uso).
