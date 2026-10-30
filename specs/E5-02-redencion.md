# E5-02 · Redención de beneficios y pantallas de validación del cajero

**Épica:** E5 · **Horas estimadas:** 10 · **Riesgo:** medio

## Objetivo

El cliente muestra el código de un beneficio (o da su documento), el cajero
lo valida y lo entrega. La redención queda registrada con su valor en pesos
y no toca los puntos.

## Fuera de alcance

- La pantalla del cliente que muestra el QR del beneficio (1o) y "Ya lo
  usaste este mes" (1p): E8-04. Aquí las pruebas emiten la credencial con
  `IssueCredencial` (E4-04).
- Firmar y rotar el QR: E4-04. Aquí cambia solo el momento en que se gasta
  el código de beneficio (decisión 2).
- Registrar la compra en la que se usó el beneficio. Es otro modo de la
  caja (E4-02) y no se enlaza con la redención.
- Anular una redención. La tabla es append-only y el issue no lo pide.
- Redimir desde el panel o desde la API pública (E9).
- El reporte de aporte por comercio (E10-02). Aquí solo se guarda el dato
  que lo alimenta (regla dura 3.11).
- Producto gratis como tipo de beneficio (§8).

## Reglas de negocio

1. **Redimir no toca los puntos** (regla dura 3.1). No hay movimiento en
   `movimientos_puntos`, y no cambian ni el saldo ni el nivel.
2. **Quién redime.** Un cajero activo, en la sede de su comercio (activa)
   y con su comercio activo, y solo beneficios de ese comercio. La
   redención guarda el comercio, la sede y el cajero (`validado_por`).
3. **Dos formas de llegar al beneficio:**
   - **Código.** El escáner lee el QR del beneficio, o el cajero digita su
     código corto. Lo valida `ValidateCredencial` con el propósito
     `beneficio`.
   - **Documento.** El cajero da el tipo y el número de documento (CC por
     defecto, regla dura 3.10). Ve los beneficios de su comercio para ese
     cliente, con el estado de E5-01, y solo puede elegir uno disponible.
     La búsqueda cuenta contra el mismo tope por minuto de la caja
     (`FindCliente`).
4. **Resultado del escaneo**, a pantalla completa:
   - **VÁLIDO (1k):** el beneficio está disponible para ese cliente hoy.
     Muestra el beneficio, su valor para el nivel del cliente, el texto de
     condiciones, el nombre del cliente y su nivel efectivo.
   - **YA USADO (1l):** el código ya se gastó, o el cliente agotó su límite
     en la ventana. Muestra cuándo y dónde se usó por última vez ("Se usó
     el 12 de septiembre, 7:42 p. m., en El Rancho de Javi · Sede
     principal") y cuándo vuelve (nada si es de por vida).
   - **NO VÁLIDO:** el código es inválido, venció, es de otro comercio o de
     otro propósito, o el beneficio está sin cupos, no disponible hoy o
     bloqueado. Muestra el mensaje de ese caso.
5. **El valor otorgado** (regla dura 3.11) se calcula con el nivel efectivo
   del cliente al confirmar:
   - Un beneficio de `monto_fijo` vale su monto.
   - En uno de `porcentaje`, el cajero escribe el **monto de la cuenta** y
     el valor es el porcentaje de ese monto, redondeado hacia abajo a pesos
     enteros (regla dura 3.9).
   - El monto de la cuenta usa el mismo máximo de dígitos de la caja.
   - Un valor de cero no se acepta: toda redención vale más de cero.
6. **Soporte.** Si el beneficio lo exige (Laura Rivera), el cajero escribe
   la referencia del soporte (número de contrato o de factura). Sin ella no
   se confirma. Se guarda sin espacios a los lados.
7. **Confirmar entrega es atómico.** En una sola transacción:
   1. bloquea la fila del beneficio;
   2. gasta el código si lo hay;
   3. vuelve a calcular el estado del beneficio para el cliente: activo,
      vigente, comercio activo, nivel, cupo global, límite por cliente y
      condiciones del día;
   4. inserta la redención.

   Si algo falla, nada queda escrito y el código sigue sin gastar.

8. **Sin condición de carrera:**
   - Dos confirmaciones del mismo código dan una sola redención: el código
     se gasta con un UPDATE condicionado (E4-04).
   - Dos confirmaciones que compiten por el último cupo global, o por el
     último uso de la ventana del cliente, se ordenan por el bloqueo del
     beneficio. La segunda ve "sin cupos" o "ya usado".
   - Nunca se verifica en código y se inserta después sin el bloqueo.
9. **Doble toque.** Cada intento de entrega lleva una referencia propia.
   Repetirla devuelve la redención ya hecha y no crea otra. Esto cubre la
   forma por documento, que no tiene código que gastar.
10. **Cancelar o salir no gasta nada.** El código sigue sirviendo mientras
    no venza.
11. **Ventana para confirmar.** El código se valida vigente al escanearlo.
    Después, el cajero tiene un margen para escribir el monto y confirmar:
    hasta N segundos después de que el código venció (N en el fixture).
    Pasado ese margen, la confirmación se rechaza como vencida y hay que
    escanear un código nuevo.
12. **Fechas.** La hora de la redención la pone la aplicación (`now()`,
    regla dura 3.13), y las ventanas de mes y año se cuentan en Bogotá,
    como en E5-01.

## Criterios de aceptación

- [ ] Dado un código vigente de un beneficio disponible, cuando el cajero
      del comercio lo escanea, entonces ve VÁLIDO con el beneficio, el
      valor de su nivel, las condiciones, el cliente y su nivel, y el
      código no se ha gastado.
- [ ] Cuando confirma la entrega, entonces se crea una redención con
      `valor_otorgado_cop` > 0, el comercio, la sede y el cajero. El código
      queda gastado, y el saldo y el nivel no cambian. No hay movimiento de
      puntos.
- [ ] Dado un código ya gastado, o un cliente que agotó su límite, entonces
      el cajero ve YA USADO con la fecha, el comercio y la sede del último
      uso, y cuándo vuelve.
- [ ] Dado un código vencido, de otro comercio o para identificar,
      entonces el cajero ve NO VÁLIDO y no se crea nada.
- [ ] Dos confirmaciones del mismo código (dos cajeros, o un doble toque)
      dan una sola redención.
- [ ] Con `cupo_global = 1` y dos confirmaciones simultáneas, solo se crea
      una redención; la otra ve "sin cupos".
- [ ] Con el límite por cliente en su último uso y dos confirmaciones
      simultáneas, solo se crea una redención.
- [ ] Cancelar en VÁLIDO no gasta el código. Una confirmación fuera de la
      ventana se rechaza sin gastarlo.
- [ ] Un beneficio que se desactiva entre el escaneo y la confirmación no
      se entrega.
- [ ] El valor otorgado de un porcentaje es el porcentaje del monto de la
      cuenta, redondeado hacia abajo. Un monto que da cero se rechaza.
- [ ] Un beneficio con soporte no se entrega sin la referencia del soporte.
- [ ] Por documento, el cajero ve solo los beneficios de su comercio, con
      su estado, y solo puede elegir los disponibles. El mismo número con
      otro tipo de documento es otro cliente.
- [ ] El tablero (E10-01) cuenta las redenciones y suma su valor otorgado
      por periodo y por comercio, con el alcance del usuario.
- [ ] E2E (celular): por documento, el cajero sembrado entrega el kilo de
      carne a un cliente recién registrado. Una segunda búsqueda muestra el
      beneficio como ya usado.

## Fixtures

- `tests/Fixtures/redencion-escenarios.json` (nuevo, en inglés): cajeros,
  clientes, valor otorgado, escaneos y confirmaciones con sus tiempos, cupo
  global, búsqueda por documento y soporte.
- `tests/Fixtures/beneficios-catalogo.json` y
  `tests/Fixtures/qr-escenarios.json` (ya en `main`): los beneficios y la
  vida del código.

## Datos y diseño (decisiones técnicas, pendientes de revisión)

### Tabla `redenciones`

Una migración nueva agrega cuatro columnas:

| Columna            | Tipo                 | Para qué                                                                                                                                                                                             |
| ------------------ | -------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `referencia`       | uuid, único          | El intento de entrega (regla 9). La tabla está vacía en todos los ambientes, así que entra como no nula.                                                                                             |
| `credencial_qr_id` | FK, nula, con índice | Con qué código se redimió. Nula si fue por documento. Responde exacto "dónde se usó este código". No es única: SQL Server admite un solo NULL en un índice único, y el uso único ya lo da el código. |
| `monto_cuenta_cop` | entero, nulo         | La base del porcentaje, para poder explicar el valor otorgado. Nulo en un monto fijo.                                                                                                                |
| `soporte`          | string 100, nulo     | La referencia del soporte (regla 6).                                                                                                                                                                 |

En el PR del código se actualiza la fila `redenciones` de CLAUDE.md §4.

### Actions (`app/Actions/Beneficios/`, invocables)

- **`CheckBeneficioCredencial`:** valida el código sin gastarlo y devuelve
  el resultado de la regla 4. Para "ya usado" busca la redención por
  `credencial_qr_id` o la última del cliente en la ventana.
- **`RedeemBeneficio`:** la entrega de la regla 7, dentro de
  `DB::transaction()`:
  1. `Beneficio::query()->lockForUpdate()` sobre la fila del beneficio. En
     SQL Server se traduce a `UPDLOCK`, así que sirve en los dos motores.
  2. Gasta el código con el UPDATE condicionado de E4-04.
  3. Cuenta las redenciones globales y las del cliente después del bloqueo.
  4. Inserta la redención.

  Si la referencia ya existe, devuelve esa redención.

- **`ValidateCredencial` (E4-04):** deja de gastar el código de
  beneficio. Ahora rechaza uno ya gastado como `usado` y no lo marca. El
  gasto pasa a `RedeemBeneficio` y se resuelve el `TODO(E5-02)`. Se
  ajustan las pruebas de E4-04 que esperaban el gasto al validar.

### Estado de un beneficio

`BeneficiosParaCliente` (E5-01) admite calcular el estado de un solo
beneficio. `RedeemBeneficio` lo llama después del bloqueo, para que el
estado se calcule con los conteos frescos. La lista por documento lo llama
filtrada al comercio del cajero.

### Caja (Livewire)

- El modo "Validar beneficio" se activa en el ModeSwitch y se resuelve su
  `TODO(E5-02)`.
- Pasos del modo: escáner → resultado (VÁLIDO / YA USADO / NO VÁLIDO) →
  monto de la cuenta y soporte si aplica → entregado.
- Por documento: buscar → lista → VÁLIDO.
- El componente solo maneja los pasos y llama a las Actions. La
  credencial, la referencia y el beneficio elegido van en propiedades
  `#[Locked]`.

### Tablero (E10-01)

`app/Tablero/BeneficiosDelPeriodo` cuenta las redenciones del periodo y
suma `valor_otorgado_cop`, por comercio o por sede según el alcance. Se
resuelven sus `TODO(E5-02)`.

### Prueba de concurrencia

Una segunda conexión toma el bloqueo del beneficio y la prueba verifica
que `RedeemBeneficio` espera hasta que se suelta. Para no esperar sin fin,
se usa la sentencia de tiempo de bloqueo de cada motor, solo dentro de la
ayuda de la prueba. Corre en PostgreSQL y SQL Server en el CI. Las
secuencias del fixture (segundo cajero, último cupo) se prueban además en
orden.

## Decisiones a confirmar en la revisión

Propuestas por el implementador el 2026-10-29.

1. **Monto de la cuenta en los porcentajes.** Todo el catálogo es de
   porcentaje, y la regla 3.11 pide pesos, así que el cajero escribe el
   monto de la cuenta después de VÁLIDO. El valor se redondea hacia abajo,
   a favor del comercio (supuesto). El diseño 1k no muestra este campo:
   se agrega con el teclado de 1c.
2. **El código se gasta al confirmar, no al escanear.** Así, cancelar no
   le quema el código al cliente, y una entrega que falla no deja el código
   gastado (el `TODO(E5-02)` de E4-04). El costo es que dos cajeros pueden
   ver VÁLIDO con el mismo código, pero solo uno lo entrega. El margen para
   confirmar es de **180 segundos** después del vencimiento (supuesto):
   el código vive 30 s y escribir el monto toma más que eso.
3. **Bloqueo de la fila del beneficio** en vez de un contador de cupo.
   Ordena con un solo mecanismo el cupo global y el límite por cliente, que
   no cabe en un UPDATE condicionado. Todas las entregas de un mismo
   beneficio esperan unos milisegundos entre sí, lo que basta con este
   volumen.
4. **El soporte es una referencia de texto** (número de contrato o de
   factura, hasta 100 caracteres), no un archivo. Pendiente de que el
   cliente confirme que le basta para Laura Rivera.
5. **La búsqueda por documento queda dentro de "Digitar código"**, como
   enlace "Buscar por documento", para mantener un solo botón en 1j.
6. **Una pantalla NO VÁLIDO** con el estilo de 1l (fondo `danger`) para los
   casos que el diseño no dibuja: vencido, inválido, sin cupos, no
   disponible hoy.
7. **El tablero se llena aquí**, como lo dejaron dicho E5-01 y E10-01.

## Reglas duras de CLAUDE.md que aplican

- 3.1 Usar un beneficio no descuenta puntos ni cambia el nivel.
- 3.7 (por analogía) Un doble toque no duplica: la referencia tiene un
  índice único en la base y no se valida en código.
- 3.9 Valor otorgado y monto de la cuenta en pesos enteros; ventanas en
  Bogotá.
- 3.10 Búsqueda por tipo y número de documento, normalizado.
- 3.11 Toda redención guarda su valor otorgado, mayor que cero.
- 3.12 Solo se acepta un código firmado y vigente al escanearlo.
- 3.13 Bloqueo y conteos con el query builder; hora puesta por la
  aplicación; nada de SQL nativo fuera de la ayuda de la prueba de
  concurrencia.

## Notas de interfaz

Usar la skill `club-ar-ui`. Toda zona táctil del cajero mide 64 px.

- **Escáner (1j):** escaneo continuo y un solo botón "Digitar código"
  (`hashtag`), sin linterna. Dentro del panel de digitar, el enlace
  "Buscar por documento".
- **VÁLIDO (1k):** fondo `success`, "VÁLIDO", beneficio, condiciones,
  cliente y nivel (TierBadge). Para un porcentaje, debajo van el monto de
  la cuenta y el valor resultante. Si hay soporte, va su campo. Termina en
  "Confirmar entrega" y "Cancelar".
- **YA USADO (1l):** fondo `danger`, "Se usó el …, en …", "Disponible de
  nuevo el …" y "Escanear otro".
- **NO VÁLIDO:** fondo `danger`, el mensaje del caso y "Escanear otro".
- **Entregado:** confirmación breve ("Beneficio entregado") y vuelta al
  escáner a los segundos de `auto_return_seconds`, como en 1g.
- Las pantallas de resultado aparecen sin animación larga (máximo 120 ms).

## Depende de

E5-01 (catálogo y estado), E4-04 (credencial) y E4-02 (caja), todos en
`main`. La usa E8-04 (uso del beneficio desde la PWA).
