# E10-02 · Reportes exportables

**Épica:** E10 · **Horas estimadas:** 10 · **Riesgo:** medio

## Objetivo

El administrador del grupo, y cada administrador de comercio con lo suyo,
consulta en el panel cinco reportes de un rango de fechas y los descarga en
Excel o CSV: compras y puntos, aporte en beneficios, clientes por nivel,
membresías y comercios sin actividad.

## Fuera de alcance

- **Liquidación entre comercios** (§8). El reporte de aporte dice cuánto
  entregó cada comercio en beneficios; no calcula quién le debe a quién.
- El tablero del mes (E10-01). Los reportes reutilizan su alcance y su
  forma de contar, pero no cambian el tablero.
- Reportes programados o enviados por correo.
- Exportar la lista de clientes con sus datos personales: va con la
  consulta y exportación de datos de E10-03 (habeas data). Estos reportes
  son agregados y no traen documentos, nombres, celulares ni correos.
- Contabilidad o facturación electrónica (§8).

## Reglas de negocio

1. **Una página "Reportes"** en el panel, justo debajo del Tablero en la
   navegación, con un filtro común:
   - **Rango de fechas** `desde`–`hasta`, por días completos en
     `America/Bogota` (regla dura 3.9). Por defecto, el mes en curso hasta
     hoy. El rango no puede ser mayor que `settings.max_range_days` ni
     terminar en el futuro.
   - **Comercio**: solo para el administrador del grupo ("Todos" por
     defecto). El administrador de comercio no ve este filtro.
2. **Alcance** (`AlcanceTablero` de E10-01). El administrador de comercio
   solo ve su comercio, y sus filas son sus sedes en vez de los comercios.
   La descarga usa el mismo alcance que la pantalla: el alcance sale del
   usuario en el servidor, nunca de un parámetro de la petición.
3. **Cada reporte** se ve como tabla en la página y tiene dos botones:
   "Descargar Excel" (`.xlsx`) y "Descargar CSV" (UTF-8 con BOM, separado
   por punto y coma, para que Excel en español lo abra bien). El archivo
   trae el reporte, el rango y el alcance en el nombre (formato en el
   fixture), por ejemplo `compras-y-puntos-2026-10-01-a-2026-10-31-grupo.xlsx`,
   y los montos como
   números enteros, sin formato de texto.
4. **Compras y puntos** (por comercio, o por sede):
   - Todos los comercios activos (o sedes activas) aparecen, aunque estén
     en cero.
   - Compras registradas: las del rango que no están anuladas, incluidas
     las pendientes (como el KPI de E10-01, decisión 1).
   - De ellas, pendientes: sin cliente todavía.
   - Monto total de esas compras, en pesos.
   - Compras anuladas: compras del rango con `estado = anulada`.
   - Puntos de acumulación: suma de los movimientos `acumulacion` del
     comercio creados en el rango.
   - Puntos de promociones: suma de los movimientos `promocion` del
     comercio creados en el rango. Los de promociones del Club (sin
     comercio) van en una fila propia, "Club Aponte Rivera", solo para el
     administrador del grupo. Con filas por sede, las promociones del
     comercio, que no tienen sede, van en la fila "Promociones del
     comercio".
   - Puntos reversados: suma de los movimientos `reversa` del comercio
     creados en el rango (negativa).
   - Por sede, la acumulación y la reversa toman la sede de su compra
     (`transaccion_id`).
   - Neto: acumulación + promociones + reversas.
   - Fila de total.

   Los puntos se cuentan por la fecha del movimiento, no de la compra: así
   el total coincide con el libro (criterio de aceptación). Los puntos son
   estatus (regla dura 3.1): las columnas dicen "puntos otorgados", nunca
   "saldo" ni "canje".

5. **Aporte por comercio** (regla dura 3.11): por comercio (o sede) y
   beneficio, las redenciones del rango, cuántas, y la suma de
   `valor_otorgado_cop`. Subtotal por comercio y total general. Una nota en
   la página y en el archivo: "Valor en pesos de los beneficios
   entregados. No es una liquidación entre comercios."
6. **Clientes por nivel**:
   - Es la foto de **hoy** (no depende del rango): el nivel efectivo de
     cada cliente, con conteo y porcentaje.
   - Por cada nivel, de dónde sale, con una sola fuente por cliente en el
     orden de E10-01 (decisión 3): por puntos → por membresía → por evento.
     Las tres fuentes siguen separadas en el cliente (regla dura 3.5); esto
     solo decide la columna.
   - El administrador de comercio ve solo los clientes con al menos una
     compra acreditada en su comercio dentro del rango ("Clientes que
     compraron", como en E10-01).
7. **Membresías** (solo el administrador del grupo; la membresía es del
   programa, no de un comercio):
   - Activas hoy: clientes con un periodo de `membresias` que cubre hoy.
   - Nuevas en el rango: primer periodo del cliente que empieza dentro del
     rango.
   - Renovadas en el rango: periodos que empiezan dentro del rango de un
     cliente que ya tuvo uno antes.
   - Vencidas en el rango: clientes cuyo último periodo terminó dentro del
     rango sin otro que empiece después.
   - Por plan (mensual, anual), con el valor de los pagos aprobados en el
     rango (`pagos_membresia.monto_cop` con `aprobado_at` en el rango).
8. **Comercios sin actividad** (para el administrador de comercio, sedes
   sin actividad):
   - Comercios activos (o sedes activas) sin ninguna compra en el rango.
   - Por cada uno: la fecha de su última compra, o "Nunca", y los días
     que han pasado desde ella.
   - Ordenados del más inactivo al menos inactivo.
9. **Totales del libro.** Ninguna cifra sale de una columna derivada: los
   puntos salen de `movimientos_puntos`, los beneficios de `redenciones`,
   las compras de `transacciones` y las membresías de `membresias` y
   `pagos_membresia`. Solo "Clientes por nivel" lee `clientes`, porque el
   nivel efectivo es su foto materializada (regla dura 3.5).
10. **Las consultas viven fuera de la página**: una clase por reporte en
    `app/Reportes/`, con el rango y el alcance como parámetros, que
    devuelve las filas. La pantalla y las dos descargas usan las mismas
    filas.

## Criterios de aceptación

- [ ] Dado cada escenario del fixture, cuando se consulta su rango y
      alcance, entonces cada reporte da las filas y los totales esperados.
- [ ] Dado un rango, entonces el total de puntos de acumulación del
      reporte es igual a la suma de los movimientos `acumulacion` del libro
      en ese rango, y lo mismo para promociones y reversas.
- [ ] Dado un rango, entonces el total del aporte es igual a la suma de
      `valor_otorgado_cop` de las redenciones de ese rango.
- [ ] Dado un administrador de comercio, entonces cada reporte y cada
      descarga traen solo su comercio, aunque la petición mande otro
      comercio; y no ve el reporte de membresías.
- [ ] Dado un cajero o un usuario sin acceso al panel, entonces no abre la
      página ni descarga nada (403).
- [ ] Dado un rango mayor que el tope, o que termina en el futuro, o con
      `desde` después de `hasta`, entonces se rechaza con un mensaje.
- [ ] Dada una compra a las 23:30 del último día del rango en Bogotá,
      entonces cuenta en el rango (prueba de zona horaria).
- [ ] Dada una descarga en Excel y en CSV, entonces tienen las mismas filas
      que la pantalla, los montos son números y el CSV abre con tildes.
- [ ] Dado un comercio sin compras en el rango, entonces aparece en
      "Comercios sin actividad" con su última compra o "Nunca".
- [ ] Ningún reporte hace una consulta por fila (prueba que cuenta las
      consultas con 15 comercios).
- [ ] E2E (escritorio): el administrador del grupo abre Reportes, cambia el
      rango y descarga un Excel.

## Fixtures

`tests/Fixtures/reportes-escenarios.json` (nuevo, en inglés): el tope del
rango, el formato del nombre de archivo, los datos sembrados (comercios,
sedes, compras, movimientos, redenciones, clientes y membresías, con sus
fechas) y, por escenario, el rango, el alcance y las filas y totales
esperados de cada reporte. Los valores que el cliente no ha confirmado van
con `"_assumption": true`.

## Datos y diseño (decisiones técnicas, pendientes de revisión)

- **Consultas** (`app/Reportes/`): `RangoReporte` (desde y hasta en
  Bogotá, con su validación), `ComprasYPuntos`, `AportePorComercio`,
  `ClientesPorNivelReporte` (reutiliza la consulta de
  `App\Tablero\ClientesPorNivel` cuando el alcance es el grupo),
  `MembresiasReporte` y `ComerciosSinActividad`. Cada una devuelve una
  lista de filas con columnas fijas y una fila de total.
- **Agregados con el query builder**: `GROUP BY` y subconsultas, sin una
  consulta por fila y sin funciones de fecha del motor; los extremos del
  rango los pone la aplicación (regla dura 3.13).
- **Índices nuevos** (una migración):
  - `transacciones (comercio_id, created_at)`
  - `movimientos_puntos (comercio_id, created_at)`
  - `redenciones (comercio_id, created_at)`
  - `membresias (vigente_desde)` y `pagos_membresia (estado, aprobado_at)`
- **Descargas**: respuestas en _stream_ con `openspout/openspout` (ya
  instalado; se agrega a `require` con la versión del lock, como en
  E4-03), sin archivo en disco y sin cola. Son agregados de pocas filas
  (15 comercios o sus sedes, 4 niveles).
- **Página**: `App\Filament\Pages\Reportes`, con el filtro como formulario
  de Filament y una sección por reporte. Las descargas son rutas del panel
  protegidas por la misma autorización de la página.
- **Sin tablas nuevas.**

## Decisiones a confirmar en la revisión

Propuestas por el implementador (2026-11-11); el revisor las aprueba con
esta spec.

1. **Descarga directa con `openspout`, no la exportación nativa de
   Filament.** La de Filament exporta filas de un modelo en segundo plano,
   y para eso pide tres tablas más (`exports`, `failed_import_rows`,
   notificaciones) y avisa por notificación cuando el archivo está listo.
   Estos reportes son agregados de pocas filas, que se generan al
   instante. Las dos opciones son gratuitas. Si se prefiere la nativa, la
   alternativa es usarla solo para "Compras y puntos" y "Aporte".
2. **Rango de fechas por días**, no solo meses como el tablero, con un
   tope de 366 días (supuesto).
3. **Los puntos se cuentan por la fecha del movimiento**, para que el
   total cuadre con el libro aunque una compra pendiente se acredite en
   otro mes.
4. **Clientes por nivel es la foto de hoy**, como en el tablero. Una foto
   a una fecha pasada exigiría reconstruirla desde `cambios_nivel`, y el
   issue no la pide.
5. **Membresías solo para el administrador del grupo.**
6. **Sin datos personales** en ningún reporte; la exportación de clientes
   va en E10-03.
7. **CSV con punto y coma y BOM**, porque Excel en español usa la coma como
   separador decimal.

## Reglas duras de CLAUDE.md que aplican

- 3.1 Los puntos son estatus: "puntos otorgados", nunca saldo ni canje
- 3.4 Las cifras de puntos salen del libro, que solo se lee
- 3.5 Las tres fuentes del nivel siguen separadas; el reporte solo las lee
- 3.9 Rangos en `America/Bogota`; montos en pesos enteros
- 3.11 El aporte sale del valor otorgado de cada redención
- 3.13 Código agnóstico del motor

## Notas de interfaz

- Skill `club-ar-ui`: solo colores y fuentes de la marca en Filament;
  cifras tabulares con formato colombiano (`3.842`, `$19.500.000`) en
  pantalla. En los archivos, números sin formato.
- Textos: "Reportes", "Desde", "Hasta", "Comercio", "Todos", "Descargar
  Excel", "Descargar CSV", "Compras y puntos", "Aporte por comercio",
  "Clientes por nivel", "Membresías", "Comercios sin actividad", "Sedes sin
  actividad", "Nunca", "Total".
- Estado vacío por reporte: "No hay datos en este rango."
- Probado a 1024 px sin desbordarse: las tablas anchas se desplazan dentro
  de su sección.

## Depende de

E10-01 (panel, `AlcanceTablero`, `ClientesPorNivel`), E4-01
(`transacciones`), E3-02 (`movimientos_puntos`), E5-02 (`redenciones`),
E6-01 (`membresias`, `pagos_membresia`).
