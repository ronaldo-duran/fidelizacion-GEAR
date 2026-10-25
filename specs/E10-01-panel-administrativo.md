# E10-01 · Panel administrativo: tema, ingreso y tablero del grupo

**Épica:** E10 · **Horas estimadas:** 12 · **Riesgo:** medio

## Objetivo

El panel de Filament se ve como el diseño aprobado (pantallas 10 · 1y y
9 · 1z) y el administrador del grupo abre un tablero con la salud del
programa en el mes: clientes, compras, beneficios entregados, clientes
por nivel y qué comercios están registrando.

## Fuera de alcance

- El ingreso del cajero a su pantalla del celular (E4-02). El cajero no
  entra al panel (E2-01); si la pantalla del cajero reutiliza este diseño,
  lo decide E4-02.
- Los recursos que aún no existen (Clientes, Transacciones, Beneficios,
  Membresías): aquí se reserva su lugar en la navegación; cada uno llega
  con su issue.
- Las redenciones y su valor (E5-02, regla dura 3.11). Mientras no existan,
  los KPI de beneficios muestran cero.
- Reportes descargables y el reporte de aporte por comercio (E10-02).
- El servidor de correo de producción: lo configura E1-02 con la cuenta
  del cliente.

## Reglas de negocio

1. **Ingreso al panel** (1y): correo y contraseña, con mostrar/ocultar la
   contraseña, "Mantener la sesión en este celular" (recordarme) y
   "Olvidé mi contraseña". Solo entran los roles con acceso y cuentas
   activas (E2-01); el mensaje de error no dice si el correo existe.
2. **Olvidé mi contraseña**: el restablecimiento por correo de Filament
   (gratuito, sin plugins). El enlace vence según la configuración de
   Laravel y responde igual exista o no el correo.
3. **Navegación** (1z), en este orden: Tablero · Clientes · Transacciones ·
   Beneficios · Comercios (comercios, sedes, categorías) · Niveles y reglas
   (reglas, simulador, eventos calificados) · Membresías. Usuarios va al
   final, en "Configuración". Un grupo sin recursos todavía no se muestra.
4. **Periodo del tablero**: un mes calendario en `America/Bogota` (regla
   dura 3.9), comparado con el mes anterior ("Septiembre 2026 · vs.
   agosto"). Se elige entre los últimos meses (cuántos, en el fixture); por
   defecto, el mes en curso.
5. **Los cuatro KPI del periodo** (cómo se cuenta cada uno vive en el
   fixture, con sus escenarios):
   - **Clientes inscritos**: total al cierre del periodo y "+N nuevos en
     el mes".
   - **Transacciones**: compras del mes que no están anuladas, con la
     variación frente al mes anterior.
   - **Beneficios entregados**: redenciones del mes y cuántas por cada 100
     compras.
   - **Valor de beneficios**: suma del valor otorgado de las redenciones
     del mes, con su variación. Una subida se pinta como aviso (`warn`):
     es costo para los comercios.
6. **Variación** frente al mes anterior: porcentaje redondeado; si el mes
   anterior fue cero, no hay porcentaje y se muestra la cifra absoluta
   (`+12`). Los formatos exactos están en el fixture.
7. **Clientes por nivel**: la foto de hoy por nivel efectivo (no depende
   del periodo), con conteo y porcentaje, y el origen de los Diamante.
   Un Diamante cuenta en una sola fuente, en este orden: por puntos, por
   suscripción, por inmueble (las tres fuentes siguen separadas en el
   cliente, regla dura 3.5; esto solo decide en qué columna del tablero
   se cuenta).
8. **Comercios por actividad**: por comercio activo, compras, beneficios,
   valor de beneficios del mes y la variación de compras; ordenados por
   compras. Un comercio activo sin compras en los últimos N días (N en el
   fixture) se resalta con "Sin registros hace N días". Se muestran los
   primeros del orden y todos los resaltados; "Ver los 15 comercios"
   despliega el resto.
9. **Administrador de comercio**: el mismo tablero, filtrado a su
   comercio. Los KPI cuentan solo sus compras y redenciones; "Clientes
   inscritos" pasa a ser "Clientes que compraron" en el mes; la tabla
   muestra sus sedes en vez de los comercios. Nunca ve cifras de otro
   comercio.
10. **Las consultas viven fuera de los widgets**: una clase de consulta por
    bloque del tablero (`app/Tablero/`), con el periodo y el alcance del
    usuario como parámetros. Los widgets solo pintan.
11. **Mientras E5-02 no exista**, la consulta de beneficios devuelve cero
    con un `TODO(E5-02)`; nunca se consulta una tabla que no existe.

## Criterios de aceptación

- [ ] Dada la pantalla de ingreso, entonces tiene el placeholder del logo,
      "Ingreso al sistema", correo, contraseña con mostrar/ocultar,
      "Mantener la sesión en este celular", "Ingresar", "Olvidé mi
      contraseña" y la franja con los 4 colores de nivel.
- [ ] Dado un cajero o una cuenta inactiva, cuando ingresa, entonces no
      entra y el mensaje no revela si el correo existe.
- [ ] Dado "Olvidé mi contraseña" con un correo existente o no, entonces
      la respuesta es la misma y solo el existente recibe el enlace.
- [ ] Dada la navegación, entonces los grupos y su orden son los de 1z y
      no aparece un grupo vacío.
- [ ] Dado cada escenario del fixture, cuando se abre el tablero de su
      periodo, entonces los cuatro KPI, las variaciones, los clientes por
      nivel, el origen de los Diamante y la tabla de comercios coinciden
      con lo esperado.
- [ ] Dado un comercio activo sin compras en los últimos N días, entonces
      aparece resaltado con "Sin registros hace N días".
- [ ] Dado un mes anterior en cero, entonces la variación muestra la cifra
      absoluta y no un porcentaje.
- [ ] Dado un administrador de comercio, entonces el tablero solo cuenta
      su comercio y la tabla muestra sus sedes.
- [ ] Dado que aún no hay redenciones (E5-02), entonces los KPI de
      beneficios muestran cero y el tablero carga sin errores.
- [ ] El tablero carga a 1024 px y el ingreso a 360 px sin desbordarse,
      con su prueba E2E.

## Fixtures

`tests/Fixtures/tablero-escenarios.json` (nuevo, en inglés): meses que se
pueden elegir, días sin compras para resaltar, cuántos comercios se
muestran antes de "Ver los N", cómo se cuenta cada KPI, orden del origen
de los Diamante, formatos de la variación y escenarios con compras,
anulaciones, clientes y niveles sembrados, para el administrador del grupo
y para el de un comercio. Los valores que el cliente no ha confirmado van
con `"_assumption": true`.

## Datos y diseño (decisiones técnicas, pendientes de revisión)

- **Ingreso**: página de ingreso propia que extiende la de Filament
  (`App\Filament\Pages\Auth\Login`), con su vista para el placeholder, el
  subtítulo y la franja de niveles. La contraseña con `revealable()`, el
  recordarme con la etiqueta del diseño y `passwordReset()` activado en el
  panel.
- **Tema**: solo colores y fuentes de la marca (`theme.css`); sidebar de
  208 px, ítem activo en `brand-soft`/`brand-strong`; cifras de los KPI en
  `font-display` tabular. Sin la escala de tamaños de la PWA.
- **Navegación**: grupos registrados en `AdminPanelProvider` con su orden
  (`navigationGroups`) y `navigationSort` en cada recurso.
- **Tablero**: página `Dashboard` propia con el selector de periodo y
  cuatro widgets (KPI, clientes por nivel, comercios por actividad).
- **Consultas** (`app/Tablero/`): `Periodo` (mes y mes anterior en
  Bogotá), `AlcanceTablero` (grupo o un comercio), `KpisDelPeriodo`,
  `ClientesPorNivel`, `ActividadDeComercios`, `BeneficiosDelPeriodo`
  (cero hasta E5-02). Query builder, agregados por `GROUP BY` sin N+1, y
  fechas puestas por la aplicación (regla dura 3.13).
- **Sin tablas nuevas.**

## Decisiones a confirmar en la revisión

Propuestas por el implementador (2026-10-24); el revisor las aprueba con
esta spec.

1. "Transacciones" cuenta las compras del mes no anuladas, incluidas las
   pendientes de un cliente aún sin registrar: miden la actividad del
   comercio.
2. "Clientes por nivel" es la foto de hoy, no la del cierre del periodo.
3. Origen de los Diamante con una sola fuente por cliente, en el orden
   puntos → suscripción → inmueble.
4. Resaltar un comercio activo tras 7 días sin compras (supuesto en el
   fixture; el diseño muestra 9 como ejemplo).
5. El administrador de comercio ve "Clientes que compraron" y la tabla por
   sedes.
6. "Olvidé mi contraseña" por correo con el restablecimiento de Filament;
   necesita el correo de producción (E1-02).
7. El subtítulo del ingreso del diseño ("Cajeros y administradores…")
   queda como "Administradores del Grupo Aponte Rivera" mientras el cajero
   no use esta pantalla (E4-02).

## Reglas duras de CLAUDE.md que aplican

- 3.5 Las tres fuentes del nivel siguen separadas; el tablero solo las lee.
- 3.9 Periodos en `America/Bogota`; montos en pesos enteros.
- 3.11 El valor de beneficios sale del valor otorgado de cada redención.
- 3.13 Código agnóstico del motor: agregados con el query builder, sin
  funciones de fecha del motor.

## Notas de interfaz

- Skill `club-ar-ui`: solo colores y fuentes de la marca en Filament;
  cifras con formato colombiano (`3.842`, `$19,5 M`, `+8 % vs. agosto`).
- Textos del diseño: "Ingreso al sistema", "Mantener la sesión en este
  celular", "Ingresar", "Olvidé mi contraseña", "Tablero del grupo",
  "Clientes por nivel", "Comercios por actividad", "Sin registros hace N
  días", "Ver los 15 comercios".

## Depende de

E2-01 (roles y alcance, en `main`). Los KPI se completan con E3-02 y
E4-01 (en `main`) y E5-02 (redenciones).
