# E4-03 · Carga masiva de compras por archivo

**Épica:** E4 · **Horas estimadas:** 16 · **Riesgo:** medio

## Objetivo

Un comercio sube un CSV o un Excel con las ventas del periodo, revisa línea
por línea qué va a pasar con cada una antes de escribir nada y, al
confirmar, las compras se registran en segundo plano con la misma Action de
la caja. Al final descarga un reporte con el resultado de cada línea.

## Fuera de alcance

- Calcular puntos o tocar el libro. Cada línea se registra con
  `RegisterCompra` (E4-01), que es la única que escribe compras y, por
  medio de `RecordAcumulacion` (E3-02), movimientos (CLAUDE.md §5,
  Reparto).
- Anular compras por archivo. Una compra cargada se anula una por una
  desde el panel (E10-06), con la reversa del libro.
- Cargar clientes. Un documento sin cliente deja la compra pendiente, como
  en la caja (E4-01, regla 5); el cliente se registra en la PWA (E8-02).
- Integrarse con un punto de venta: para eso está la API (E9-01, §8).
- Migrar datos históricos de clientes (§8). La ventana de fechas de la
  regla 4 impide usar la carga para eso.
- Leer fotos de facturas (OCR, §8).

## Reglas de negocio

1. **Quién carga.** El administrador de comercio carga compras de su
   comercio, en cualquiera de sus sedes. El administrador del grupo elige
   el comercio al subir el archivo. El cajero no entra al panel. La
   autorización de cada línea es la de `RegisterCompra`
   (`TransaccionPolicy::register`).
2. **El archivo.**
   - Formatos: CSV (UTF-8, separado por coma o por punto y coma) y Excel
     `.xlsx`. De un Excel se lee solo la primera hoja.
   - La primera fila son los encabezados, en este orden: `tipo_documento`,
     `documento`, `fecha`, `hora`, `monto`, `referencia`, `sede`. Si los
     encabezados no coinciden, el archivo completo se rechaza con un error
     que nombra las columnas que faltan o sobran.
   - Topes de tamaño y de líneas en el fixture (`limits.max_file_kb`,
     `limits.max_lines`). Un archivo sin líneas se rechaza.
   - Las filas completamente vacías se ignoran y no cuentan.
3. **Plantilla descargable.** Un `.xlsx` generado para el comercio:
   - hoja "Compras", solo con los encabezados;
   - hoja "Sedes", con el código (`id`), el nombre y si está activa cada
     sede del comercio, para copiar el código en la columna `sede`.
4. **Cada columna se valida así** (paso 1, sin escribir compras ni
   puntos):
   - **`tipo_documento`**: uno de `TipoDocumento` (`CC`, `CE`, `PPT`, `PA`,
     `DEX`), sin distinguir mayúsculas. Obligatorio: nunca se supone `CC`
     (regla dura 3.10).
   - **`documento`**: obligatorio; se normaliza con
     `Cliente::normalizeDocumento`.
   - **`fecha`**: `AAAA-MM-DD`, o la fecha nativa de una celda de Excel.
     No puede ser futura ni anterior a `limits.max_days_back` días
     (contados desde hoy en `America/Bogota`).
   - **`hora`**: opcional, `HH:MM` de 24 horas. Sin hora, la compra queda a
     las 00:00 de su fecha. Una fecha y hora de hoy no puede ser posterior
     al momento de la validación.
   - **`monto`**: pesos enteros mayores que cero y hasta `limits.max_amount`
     (regla dura 3.9). Se acepta un número entero, o un texto con solo
     dígitos y, opcionalmente, `$` al inicio y puntos de miles en grupos de
     tres (`85000`, `$85.000`). Se rechazan las comas, los decimales y
     cualquier otro texto. En un Excel, un número con parte decimal
     distinta de cero se rechaza.
   - **`referencia`**: obligatoria. Se normaliza como en `NuevaCompra`
     (sin espacios y en minúsculas, regla 3.13). Si se repite dentro del
     mismo archivo, las líneas repetidas después de la primera son error.
   - **`sede`**: el código de una sede activa del comercio de la carga.
5. **Resultado de la validación por línea:**

   | Estado          | Cuándo                                                                 | Al confirmar                                   |
   | --------------- | ---------------------------------------------------------------------- | ---------------------------------------------- |
   | `lista`         | Todo válido y el documento es de un cliente                            | Se registra y suma puntos                      |
   | `pendiente`     | Todo válido y el documento no es de ningún cliente                     | Se registra pendiente, sin puntos (E4-01, r.5) |
   | `ya_registrada` | Ya existe esa referencia en el comercio con el mismo documento y monto | Nada: no suma de nuevo                         |
   | `error`         | Cualquier regla de la 4 falla, o la referencia existe con otros datos  | Se salta                                       |

   Cada línea con `error` lleva un mensaje en español que dice qué columna
   corregir. Una línea puede tener varios errores; se muestran todos.

6. **Vista previa.** Antes de confirmar, el administrador ve el conteo por
   estado y la lista de las líneas con error (número de línea, columna y
   mensaje). Puede descargar esa lista. Si no hay líneas `lista` ni
   `pendiente`, no hay botón para confirmar.
7. **Confirmar** (paso 2):
   - Solo quien subió la carga, o un administrador del grupo, la confirma,
     y solo una vez: el cambio de estado `validada → procesando` es un
     UPDATE condicionado al estado; si afecta cero filas, otro ya la
     confirmó.
   - Se procesan solo las líneas `lista` y `pendiente`, en una cola
     (Horizon), en orden de fecha y hora y después de número de línea.
   - Cada línea llama a `RegisterCompra` con `canal = carga_masiva`,
     la sede de la línea, `registrada_por` = quien confirmó y la fecha y
     hora de la línea (regla 8). Cada línea es su propia transacción: un
     error en una no detiene ni deshace las demás.
   - La carga debe confirmarse dentro de `limits.confirm_within_hours`
     después de validada; si no, vence y hay que subir el archivo otra vez.
8. **La fecha de la compra es la de la línea.** `RegisterCompra` recibe
   una fecha opcional; si llega, la compra se guarda con ese `created_at`.
   Así, como ya pasa con las compras pendientes (E4-01, regla 6):
   - las reglas que aplican son las vigentes en esa fecha (regla dura 3.3);
   - los topes diarios y mensuales cuentan ese día y ese mes;
   - los puntos vencen contando desde esa fecha.

   Los movimientos del libro conservan su propio `created_at` (el momento
   del registro), igual que la acreditación de una pendiente. La caja y la
   API no mandan fecha: para ellas no cambia nada.

9. **Al procesar se vuelve a validar.** Entre la vista previa y el
   proceso pudo cambiar algo (una sede desactivada, un cliente que se
   registró, otra carga con la misma referencia). El resultado final de
   cada línea es el que diga `RegisterCompra` en ese momento:

   | Resultado    | Cuándo                                                            |
   | ------------ | ----------------------------------------------------------------- |
   | `acreditada` | Compra nueva de un cliente; trae los puntos otorgados             |
   | `pendiente`  | Compra nueva sin cliente                                          |
   | `repetida`   | `RegisterCompra` devolvió una compra existente (`repetida: true`) |
   | `conflicto`  | `CompraEnConflicto`: la referencia existe con otros datos         |
   | `error`      | `ValidationException` o `AuthorizationException`, con su mensaje  |

10. **Subir el mismo archivo otra vez no duplica nada.** Lo garantiza la
    restricción única `(comercio_id, referencia_externa)` de E4-01 (regla
    dura 3.7), no una revisión en código: en la vista previa esas líneas
    salen `ya_registrada`, y si dos cargas con las mismas referencias se
    procesan a la vez, una línea de las dos queda `repetida`.
11. **Ascenso.** Cada línea pasa por el ascenso síncrono de
    `RegisterCompra` (regla dura 3.6). La carga nunca baja a nadie de
    nivel.
12. **Reporte final.** Al terminar, la carga muestra el conteo por
    resultado y ofrece un CSV con todas las líneas: las columnas del
    archivo, el resultado, los puntos otorgados (vacío si no aplica) y el
    mensaje de error. El CSV lleva BOM de UTF-8 para que Excel muestre las
    tildes. Los puntos son estatus (regla dura 3.1): el reporte no habla
    de saldo ni de canje.
13. **El archivo original no se guarda.** Se lee al subirlo, sus líneas
    quedan en la base y el archivo se borra (Ley 1581: un solo lugar con
    los documentos de los clientes, el mismo que ya tiene `transacciones`).
    La plantilla y los reportes se generan al descargarlos.
14. **Progreso.** La pantalla de una carga en proceso muestra cuántas
    líneas van y se actualiza sola. Si el proceso falla a mitad de camino,
    el reintento de la cola sigue con las líneas que no tienen resultado;
    las que ya se registraron salen `repetida` si se procesan otra vez, sin
    sumar puntos.

## Criterios de aceptación

- [ ] Dado un archivo válido, cuando se valida, entonces no existe
      ninguna compra ni movimiento nuevo y cada línea tiene su estado.
- [ ] Dado el mismo archivo subido y procesado dos veces, cuando termina
      la segunda carga, entonces los saldos de los clientes son los mismos
      que después de la primera, y todas sus líneas quedaron
      `ya_registrada` en la vista previa.
- [ ] Dadas dos cargas con las mismas referencias procesadas al mismo
      tiempo, entonces cada compra existe una vez y los puntos se otorgaron
      una vez (prueba de concurrencia).
- [ ] Dado un archivo con una línea con error en medio, cuando se procesa,
      entonces las demás líneas se registran.
- [ ] Dado un documento sin cliente, entonces la línea sale `pendiente` y
      la compra queda pendiente, sin movimientos.
- [ ] Dado el mismo número de documento con otro tipo, entonces es otro
      cliente (regla dura 3.10).
- [ ] Dada una fecha pasada dentro de la ventana, cuando se procesa,
      entonces la compra tiene ese `created_at`, se calculó con la versión
      de la regla vigente ese día (aunque la regla haya cambiado después)
      y sus puntos vencen contando desde esa fecha.
- [ ] Dada una fecha futura o anterior a la ventana, entonces la línea es
      error.
- [ ] Cada caso de monto, documento, referencia, sede, fecha y hora del
      fixture da el estado y el mensaje esperados, en CSV y en Excel.
- [ ] Dados encabezados equivocados, un archivo vacío, uno más grande que
      el tope o con más líneas que el tope, entonces se rechaza completo
      sin crear la carga.
- [ ] Dado un administrador de comercio, entonces solo carga en su
      comercio y solo ve sus cargas; el administrador del grupo ve todas.
- [ ] Dada una carga ya confirmada, cuando se confirma otra vez (también
      dos confirmaciones a la vez), entonces se procesa una sola vez.
- [ ] Dada una carga validada hace más de `limits.confirm_within_hours`,
      entonces ya no se puede confirmar.
- [ ] Dada una sede desactivada entre la validación y el proceso, entonces
      sus líneas terminan en `error` y las demás se registran.
- [ ] Dado un archivo de 5.000 líneas, entonces se valida y se procesa
      completo, y el reporte trae las 5.000.
- [ ] Al terminar la validación, el archivo subido ya no existe en el
      disco.
- [ ] Ninguna clase de la carga escribe en `movimientos_puntos` ni en
      `transacciones` sin pasar por `RegisterCompra` (prueba de
      arquitectura).
- [ ] E2E (escritorio): el administrador de comercio sembrado descarga la
      plantilla, sube un CSV con una línea válida, una pendiente y una con
      error, ve la vista previa, confirma y descarga el reporte.

## Fixtures

`tests/Fixtures/carga-masiva-escenarios.json` (nuevo, en inglés):

- `limits`: tamaño, líneas, ventana de fechas, tope de monto, plazo para
  confirmar y tamaño de los lotes de la cola, con `_assumption` donde el
  cliente no lo ha confirmado.
- `headers`: los encabezados en orden.
- `lines`: un caso por regla de validación, con la línea tal como viene en
  el archivo y el estado y el mensaje esperados.
- `processing`: los resultados esperados al procesar (regla 9).

Los comercios, las sedes y las reglas son los de
`tests/Fixtures/compras-escenarios.json`; los clientes y las compras que ya
existen van en este fixture.

## Datos y diseño (decisiones técnicas, pendientes de revisión)

### Tablas nuevas

- **`cargas_compras`**: `id`, `comercio_id`, `nombre_archivo` (el nombre
  original, solo para mostrarlo), `formato` (`csv` | `xlsx`), `estado`
  (`validada` | `procesando` | `terminada` | `vencida`), `lineas_total`, los
  conteos por estado de la vista previa y por resultado del proceso,
  `subida_por`, `confirmada_por`, `confirmada_at`, `terminada_at`,
  `created_at`, `updated_at`. Índice `(comercio_id, created_at)`.
- **`cargas_compras_lineas`**: `id`, `carga_id`, `numero_linea` (la fila
  del archivo), las columnas leídas como texto tal como venían
  (`tipo_documento`, `documento`, `fecha`, `hora`, `monto`, `referencia`,
  `sede`), los valores normalizados (`tipo_documento_normalizado`,
  `documento_normalizado`, `fecha_compra`, `monto_cop`, `referencia_externa`,
  `sede_id`), `estado_validacion`, `errores` (JSON: lista de `{columna,
mensaje}`), `resultado` (nulo hasta procesarla), `transaccion_id` (nulo
  si no hubo compra), `puntos_otorgados`, `mensaje_resultado`,
  `procesada_at`. Restricción única `(carga_id, numero_linea)` e índice
  `(carga_id, resultado)`.
- Las dos tablas solo cambian por estas Actions; no hay edición en el
  panel.

### Cambio en E4-01 (riesgo alto, revisión de Ronaldo)

- `NuevaCompra` recibe un `?CarbonImmutable $fecha` opcional, al final de
  su constructor. `RegisterCompra` lo pasa como `created_at` de la
  transacción; si es nulo, sigue usando `now()`. Nada más cambia:
  `AcreditarCompra` ya calcula con `$transaccion->created_at`.
- `RegisterCompra` rechaza una `fecha` futura con `ValidationException`,
  para que ningún canal la use mal aunque la carga ya la valide.

### Lectura de archivos

- `openspout/openspout` lee CSV y `.xlsx` con poca memoria, línea por
  línea. **Ya está instalado** (lo trae Filament para exportar); se agrega
  a `require` de `composer.json` con la versión del lock, porque el
  código lo usa directo. No entra ningún paquete nuevo.
- El separador del CSV se detecta en la fila de encabezados (`,` o `;`).
  Si el archivo trae BOM, se quita.

### Actions y jobs

- `app/Actions/CargaMasiva/`, invocables:
  - `ValidateCargaCompras`: lee el archivo, valida cada línea (regla 4 y
    5), guarda la carga y sus líneas en una transacción y borra el
    archivo. Las búsquedas a la base van por lotes (clientes por
    `(tipo_documento, documento)`, transacciones por referencias, sedes del
    comercio) para no hacer una consulta por línea.
  - `ConfirmCargaCompras`: autoriza, pasa la carga a `procesando` con el
    UPDATE condicionado y despacha los jobs.
  - `ProcessLineaCarga`: registra una línea con `RegisterCompra` y guarda
    su resultado. Si la línea ya tiene resultado, no hace nada.
- `app/Jobs/ProcessCargaCompras`: toma un lote de líneas sin resultado
  (`limits.batch_size`), las procesa una por una y, si quedan, se
  despacha otra vez. Al no quedar ninguna, cierra la carga (`terminada`,
  conteos finales).
- `app/CargaMasiva/`: lector del archivo, validador de una línea y el
  generador del reporte y de la plantilla.

### Panel (Filament)

- Recurso "Cargas de compras", en el grupo de Compras, con:
  - lista: fecha, comercio, archivo, estado y conteos;
  - crear: subir el archivo (y elegir el comercio, si es administrador del
    grupo) y el enlace a la plantilla;
  - ver: la vista previa o el resultado, con "Confirmar" y las descargas.
- La política `CargaComprasPolicy` usa `AlcancePanel` como las demás.
- Las descargas son respuestas en _stream_, sin archivo en disco.

### Prueba de concurrencia

Igual que en E4-01: dos cargas con las mismas referencias, procesadas en
procesos separados sobre PostgreSQL. Se verifica que cada compra existe una
vez y que el saldo subió una vez.

## Decisiones a confirmar en la revisión

Propuestas por el implementador (2026-11-11); el revisor las aprueba con
esta spec.

1. **La fecha de la línea es la fecha de la compra** (regla 8), con
   ventana de `limits.max_days_back` días hacia atrás (supuesto: 30, igual
   que el plazo de la reversa). Sin esto, una venta del día 1 cargada el
   día 20 se calcularía con las reglas del día 20 y sus puntos vencerían 20
   días tarde. La ventana impide cargar historia vieja (§8).
2. **Sin hora, 00:00.** El cliente puede perder hasta un día de vigencia
   de esos puntos; a cambio, una fecha de hoy nunca queda en el futuro.
3. **La sede va por su código**, no por el nombre, porque los nombres
   pueden repetirse dentro de un comercio y el código no. La plantilla
   trae la lista.
4. **El archivo original se borra** después de validar (regla 13).
5. **El tipo de documento es obligatorio** en cada línea; una línea sin
   tipo es error, nunca `CC` por defecto.
6. **Topes** (supuestos, en el fixture): 5.000 líneas por archivo (el
   tamaño que pide el issue), 2 MB, monto hasta el de la API
   (`999.999.999.999`), 24 horas para confirmar y lotes de 500 líneas.
7. **`openspout/openspout` pasa a dependencia directa** con la versión que
   ya está en el lock.

## Reglas duras de CLAUDE.md que aplican

- 3.1 Los puntos son estatus: ni el reporte ni la pantalla hablan de saldo
- 3.3 Snapshot: reglas de la fecha de la compra
- 3.4 Libro inmutable: solo `RegisterCompra` escribe
- 3.6 Ascenso síncrono por línea; la carga no hace descensos
- 3.7 Idempotencia por la restricción única de `transacciones`
- 3.9 Pesos enteros; fechas en `America/Bogota`
- 3.10 Tipo y número de documento, normalizados
- 3.13 Código agnóstico del motor (ninguna consulta nativa; los conteos
  con el query builder)

## Notas de interfaz

- No hay diseño propio en `docs/diseno/`: se usan los componentes de
  Filament con el tema del panel (skill `club-ar-ui`), sin colores sueltos.
- Textos en español de Colombia con tuteo. Los estados se muestran como
  "Lista", "Quedará pendiente", "Ya registrada" y "Con error"; los
  resultados como "Registrada", "Pendiente", "Ya existía", "En conflicto"
  y "Con error".
- La vista previa muestra primero los conteos y después la tabla de
  errores, paginada.

## Depende de

E4-01 (`RegisterCompra`, `NuevaCompra`, restricción única), E2-01 (roles y
alcance del panel), E2-02 (comercios y sedes), E10-01 (panel), E1-02 (cola
con Horizon).
