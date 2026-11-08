# E8-03 · PWA: inicio y niveles

**Épica:** E8 · **Horas estimadas:** 12 · **Riesgo:** bajo

## Objetivo

Al entrar a la PWA, el cliente ve su nivel, cuánto le falta para el siguiente
y qué beneficios puede usar hoy. En la pantalla de niveles entiende cómo se
llega a cada uno.

## Fuera de alcance

- El catálogo completo y el uso del beneficio (QR, código, hora en vivo):
  E8-04. Desde el inicio solo se enlaza a ese catálogo.
- El historial y el perfil: E8-05. Mientras no exista el perfil, "Cerrar
  sesión" sigue en el inicio (ver regla 11).
- El QR personal: queda fuera del inicio por decisión de diseño (1f
  modificada).
- La compra de la suscripción: ya existe en `/membresia` (E6-01); aquí solo
  se enlaza.
- Calcular niveles, puntos o beneficios. Esta feature solo **lee** lo que ya
  calculan E3-03 (`nivel_efectivo` y sus fuentes), E3-01 (regla `niveles`) y
  E5-01 (`BeneficiosParaCliente`). No escribe nada en la base de datos.
- Avisos de descenso o de puntos por vencer: los manda E3-05 por E7-01.

## Reglas de negocio

1. **Nivel que se muestra.** Siempre `clientes.nivel_efectivo`, nunca una de
   sus fuentes por separado (regla dura 3.5). El color del bloque superior,
   los rombos y el nombre del nivel salen de él.
2. **Saludo.** "{primer nombre}, eres" y, debajo, el nombre del nivel. El
   primer nombre es la primera palabra de `nombre_completo`; sin nombre, el
   saludo queda en "Eres".
3. **Cuánto falta.** El nivel siguiente y los puntos que faltan los da
   `App\Niveles\ProximoNivel`, con la regla `niveles` vigente. Ningún umbral
   va escrito en la vista ni en el componente. En Diamante no hay cifra ni
   barra: se dice que es el nivel más alto.
4. **Barra de progreso.** Va del umbral del nivel efectivo al del siguiente,
   redondeada hacia abajo. Si el saldo está por debajo del umbral del nivel
   efectivo (porque el nivel viene de una membresía o de un evento), la barra
   arranca en 0. Los casos están en el fixture.
5. **De dónde viene el nivel.** Si `nivel_efectivo` es más alto que
   `nivel_por_puntos`, debajo del nivel se dice de dónde sale y hasta cuándo:
   "Por tu suscripción, hasta el 2 de diciembre" (membresía) o "Por tu
   compra, hasta el 3 de noviembre de 2027" (evento). Si las dos fuentes dan
   el mismo nivel, se muestra la que dura más. Las fechas usan
   `membresia_vigente_hasta` y `evento_vigente_hasta`.
6. **Para usar hoy.** Son los beneficios en estado `disponible` de
   `BeneficiosParaCliente::para($cliente)`: los bloqueados, usados, sin cupo o
   no disponibles hoy no aparecen aquí (sí en el catálogo de E8-04). El inicio
   muestra hasta el número de beneficios que dice el fixture
   (`home.today_list.max_items`). Si hay más, aparece el enlace "Ver los N"
   al catálogo. El orden es por nombre del comercio y luego por nombre del
   beneficio.
7. **Los puntos son estatus** (regla dura 3.1). Ningún texto habla de saldo,
   de canjear ni de gastar puntos. La pantalla de niveles conserva la frase
   del diseño: "Los beneficios no gastan puntos."
8. **Pantalla de niveles.** Hay cuatro tarjetas (TierCard), de Bronce a
   Diamante:
   - Bronce dice "Al registrarte"; las demás, su umbral ("500 pts").
   - Cada tarjeta dice cuántos beneficios vigentes recibe ese nivel ("9
     beneficios"). Un beneficio lo recibe un nivel cuando su
     `valores_por_nivel` le da un valor a ese nivel.
   - La tarjeta del nivel efectivo lleva "TU NIVEL".
   - Solo la tarjeta del nivel siguiente dice "Te faltan N pts".
9. **Diamante explica sus tres vías.** Su tarjeta dice: acumular el umbral de
   Diamante, suscribirse, o comprar un inmueble con la inmobiliaria del grupo
   (Diamante por el tiempo que diga `config/niveles.php`). Todo número sale
   de su fuente:
   - el umbral, de la regla `niveles`;
   - la duración del evento, de `event_rules.compra_inmueble.default_validity_months`;
   - el precio "desde", del plan activo más barato por mes de
     `planes_membresia`.

   El botón lleva a `/membresia`. Si el cliente ya tiene una membresía
   vigente, el botón dice "Ver mi suscripción".

10. **Pestañas.** Inicio · Beneficios · Historial · Niveles, como enlaces de
    texto bajo el encabezado, sin barra inferior. Una pestaña cuya pantalla
    todavía no existe (Beneficios hasta E8-04, Historial hasta E8-05) se
    muestra pero no es enlace. El componente lo decide con `Route::has()`, así
    no hay que volver a tocarlo cuando esas pantallas lleguen.
11. **Lo que había en el inicio provisional.** El enlace a Misiones (E3-06)
    pasa a una fila debajo de "Para usar hoy". "Cerrar sesión" queda al final
    del inicio, con `TODO(E8-05)`, hasta que el perfil lo reciba. El enlace a
    la suscripción pasa a la tarjeta Diamante de la pantalla de niveles.
12. **Estados.**
    - **Cargando:** la lista "Para usar hoy" se carga diferida (Livewire
      `lazy`) y mientras tanto muestra un skeleton sobre `sunken`. El bloque
      del nivel se pinta desde el servidor en la primera respuesta, para que
      el nivel nuevo se vea al instante (regla dura 3.6).
    - **Vacío:** sin beneficios disponibles hoy, se muestra el estado vacío
      "Hoy no tienes beneficios para usar" con el enlace "Ver cómo subir de
      nivel" a la pantalla de niveles.
    - **Error al cargar la lista:** el mensaje "No pudimos cargar tus
      beneficios" con el botón "Reintentar".
    - **Sin conexión:** la página "Sin conexión" del service worker (E8-01).
      Estas pantallas no se guardan en caché porque traen datos del cliente.
13. **Sin N+1.** El inicio hace un número fijo de consultas sin importar
    cuántos beneficios haya. Lo mismo la pantalla de niveles: el conteo por
    nivel sale de una sola consulta de beneficios vigentes.

## Criterios de aceptación

- [ ] Dado un cliente de cada nivel del fixture, cuando abre el inicio,
      entonces ve el color de su nivel, el saludo, el nivel siguiente y los
      puntos que le faltan, y la barra en el porcentaje esperado. Hay una
      prueba por cliente.
- [ ] Dado un cliente Diamante, cuando abre el inicio, entonces no ve cifra
      ni barra de progreso.
- [ ] Dado un cliente con nivel por membresía o por evento más alto que su
      nivel por puntos, cuando abre el inicio, entonces ve de dónde viene su
      nivel y hasta qué fecha.
- [ ] Dado un cliente con más beneficios disponibles que el máximo del
      fixture, cuando abre el inicio, entonces ve solo el máximo y el enlace
      "Ver los N". Los bloqueados y los usados no aparecen.
- [ ] Dado un cliente sin beneficios disponibles hoy, cuando abre el inicio,
      entonces ve el estado vacío con el enlace a niveles.
- [ ] Dado el cliente Plata del fixture, cuando abre la pantalla de niveles,
      entonces ve las cuatro tarjetas del fixture: "TU NIVEL" en Plata y "Te
      faltan 340 pts" solo en Oro.
- [ ] Dado un cambio de umbral en la regla `niveles`, cuando el cliente abre
      el inicio y la pantalla de niveles, entonces las cifras cambian sin
      tocar ninguna vista. La prueba crea una versión nueva de la regla.
- [ ] Dado un cliente con membresía vigente, cuando abre la pantalla de
      niveles, entonces el botón de Diamante dice "Ver mi suscripción" y
      lleva a `/membresia`.
- [ ] Dado el inicio con varios beneficios, cuando se cuenta lo que consulta,
      entonces el número de consultas no crece con la cantidad de beneficios.
- [ ] Dado un cliente sin sesión, cuando pide `/inicio` o `/niveles`,
      entonces va a `/ingresar`.
- [ ] Hay una prueba E2E del flujo principal en `tests/e2e/` (ingresar → ver
      el inicio → abrir Niveles), en celular a 360 px y en escritorio.
      Además se compara a mano contra `docs/diseno/reference/` (1f y 1r).

## Fixtures

`tests/Fixtures/inicio-niveles-escenarios.json`: clientes de cada nivel, de
membresía y de evento con lo que deben ver, el tope de "Para usar hoy" y las
tarjetas de la pantalla de niveles. Los umbrales vienen de la regla `niveles`
de `tests/Fixtures/reglas-validas.json`.

## Propuesta técnica (pendiente de revisión)

- `App\Livewire\Cliente\Inicio` (`/inicio`, reemplaza la vista provisional) y
  `App\Livewire\Cliente\Niveles` (`/niveles`). Los dos detrás de
  `auth:cliente`.
- `App\Livewire\Cliente\ParaUsarHoy`: el componente diferido de la lista.
- Una clase de lectura, `App\Niveles\ResumenNivel`, arma lo que pinta el
  bloque superior:
  - nivel efectivo y primer nombre;
  - nivel siguiente, puntos que faltan y porcentaje;
  - origen del nivel y su fecha.

  Usa `ProximoNivel` y `NivelCalculator::umbral`. Los componentes Livewire
  quedan sin lógica de negocio (sección 7 de `CLAUDE.md`).

- Se reutilizan los componentes de E8-01: `tier-card`, `progress-bar`,
  `benefit-list-item`, `tier-diamonds` y `empty-state`. `tab-nav` pasa a
  recibir enlaces. Nada de tokens nuevos.

## Decisiones a confirmar en la revisión

1. "Para usar hoy" muestra hasta 5 beneficios (supuesto del fixture) y los
   ordena por comercio y nombre. El diseño no define tope ni orden.
2. La tarjeta Diamante dice "la inmobiliaria del grupo" en lugar del nombre
   del comercio que trae el diseño, para no escribir en la vista el nombre de
   un comercio que puede cambiar.
3. La matrícula del colegio también da Oro o Diamante (issue #9), pero el
   diseño y el issue hablan de tres vías. Propuesta: no mencionarla en esta
   pantalla. Si el cliente la quiere, se agrega una línea en las tarjetas
   Oro y Diamante.
4. Las tarjetas no muestran el "Ej.: 2x1 en cerveza" del diseño: elegir un
   ejemplo por nivel sería un dato más para administrar. Se muestra solo el
   conteo de beneficios.
5. La línea de origen del nivel (regla 5) no está en el diseño. Se agrega
   porque, sin ella, un cliente Diamante por suscripción no entiende por qué
   sus puntos no cuadran con su nivel.
6. "Cerrar sesión" sigue en el inicio hasta que E8-05 traiga el perfil.

## Reglas duras de CLAUDE.md que aplican

- 3.1 Los puntos son estatus: nada de "canjear" ni de saldo en la interfaz.
- 3.5 El nivel efectivo son tres fuentes: se muestra `nivel_efectivo` y, si
  hace falta, de qué fuente sale; nunca se recalcula en la vista.
- 3.6 Ascenso síncrono: el bloque del nivel sale en la primera respuesta, sin
  caché ni carga diferida.
- 3.9 Tiempo: fechas en `America/Bogota` y formato colombiano.
- Sección 7: solo tokens, zonas táctiles de 44 px, mensajes en tuteo, una
  prueba E2E por pantalla nueva.

## Notas de interfaz

- Diseño: pantalla 2 (1f modificada, sin QR) y pantalla 6 (1r) en
  `docs/diseno/reference/`. Componentes TierHero, TierCard, ProgressBar,
  BenefitListItem, ClientHeader y TabNav de `docs/diseno/COMPONENTS.md`.
- TierHero: el bloque con el `bg` del nivel ocupa todo el ancho e incluye el
  encabezado y las pestañas. El nombre del nivel va en display 800 a 64 px
  con los rombos a la derecha. En Oro, las pestañas usan el `ink` del nivel.
  Diamante es el único invertido.
- La cifra de puntos que faltan va en display 700 a 40 px, con
  `tabular-nums` y formato colombiano (`1.500`).
- Skill `club-ar-ui`. Probar a 360 px.

## Depende de

E8-01 (componentes y PWA), E8-02 (sesión del cliente), E3-03 (nivel
efectivo), E3-01 (regla `niveles`), E5-01 (beneficios por cliente) y E6-01
(`/membresia` y planes).
