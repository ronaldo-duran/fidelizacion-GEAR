# E3-02 · Libro de puntos

**Épica:** E3 · **Horas estimadas:** 16 · **Riesgo:** alto

## Objetivo

Registrar todo movimiento de puntos de forma inmutable y auditable, con
el saldo del cliente siempre consistente.

## Fuera de alcance

- Calcular cuántos puntos da una compra, y la tabla `transacciones` con su
  idempotencia (E4-01 y E9-01, regla dura 3.7).
- Pantalla de cajero (E4-02) y cualquier otra interfaz de movimientos.
- El job nocturno de caducidad y la decisión de cómo se corta el
  vencimiento (E3-05). Aquí solo se garantiza que el libro admite un
  movimiento de `caducidad`.
- Niveles del cliente (columnas `nivel_*`, E3-03).
- Redenciones de beneficios: viven en `redenciones` (E5-02) y nunca tocan
  los puntos (regla dura 3.1).

## Reglas de negocio

1. `movimientos_puntos` es append-only: nunca UPDATE ni DELETE.
2. Todo movimiento guarda `regla_id` y `regla_version` aplicadas
   (snapshot, regla dura 3.3), salvo `ajuste_manual`, que no tiene regla y
   en cambio exige un motivo y el usuario que lo hizo.
3. El saldo en `clientes` se actualiza dentro de la misma transacción de
   base de datos que inserta el movimiento.
4. Anular una compra inserta un movimiento `reversa` negativo que
   referencia la transacción original. El movimiento original no se toca.
5. La reversa solo se admite dentro del plazo de reversa después de la
   compra. La duración del plazo vive en un fixture y en configuración,
   nunca como número suelto en el código.
6. El saldo recalculado desde cero debe coincidir siempre con el saldo
   materializado.
7. Tipos de movimiento: `acumulacion`, `caducidad`, `ajuste_manual`,
   `reversa`. **Se elimina `redencion`** del enum (ver Decisiones).

## Criterios de aceptación

- [ ] Dado un movimiento insertado, cuando se intenta actualizarlo o
      borrarlo por el modelo, entonces la operación falla.
- [ ] Dada una acumulación de N puntos, cuando se completa, entonces el
      saldo del cliente aumentó exactamente N dentro de la misma
      transacción.
- [ ] Dada una transacción anulada, cuando se procesa la reversa,
      entonces existe un movimiento negativo que la referencia, el
      original sigue intacto y el saldo refleja la resta.
- [ ] Dada una transacción por fuera del plazo de reversa, cuando se
      intenta revertir, entonces se rechaza y no se inserta nada.
- [ ] Dada una transacción ya revertida, cuando se intenta revertir de
      nuevo, entonces se rechaza.
- [ ] Dado un ajuste manual, cuando se registra, entonces guarda el motivo
      y el usuario, y un ajuste manual sin motivo se rechaza.
- [ ] Dado un cliente con movimientos de todos los tipos, cuando se
      recalcula el saldo desde cero, entonces coincide con el saldo
      materializado.
- [ ] Dado un fallo a mitad de la operación, cuando se hace rollback,
      entonces no queda ni movimiento ni cambio de saldo.
- [ ] Dado un movimiento que dejaría el saldo bajo cero, cuando se
      registra, entonces se rechaza, salvo la reversa, que se topa en el
      saldo actual (ver Reversa).
- [ ] Dada la reversa de una compra cuyos puntos ya consumió en parte un
      movimiento negativo posterior, cuando se procesa, entonces resta solo
      el saldo actual, deja el saldo en cero y registra el monto topado con
      su motivo.
- [ ] Dadas dos reversas de la misma transacción procesadas al mismo
      tiempo, cuando ambas terminan, entonces existe un solo movimiento de
      reversa y el saldo se restó una sola vez.
- [ ] Dadas dos acumulaciones del mismo cliente procesadas al mismo
      tiempo, cuando ambas terminan, entonces el saldo es la suma de las
      dos.
- [ ] Dado un ajuste manual hecho por un administrador de comercio o un
      cajero, cuando se registra, entonces se rechaza con 403 y no se
      inserta nada.
- [ ] Dada una reversa hecha por el administrador del comercio de la
      transacción, entonces se acepta; hecha por el administrador de otro
      comercio o por un cajero, entonces se rechaza con 403.
- [ ] Dado un movimiento cuyo signo o campos no corresponden a su tipo
      (una `acumulacion` con puntos negativos, una `acumulacion` sin
      `comercio_id` o sin snapshot de regla, una `reversa` sin `motivo`,
      cero puntos), cuando se registra, entonces se rechaza.

## Fixtures

`tests/Fixtures/movimientos-escenarios.json` — se actualiza en este PR de
spec: claves y textos en inglés (los campos de movimiento conservan el
nombre de su columna), la nota pendiente sobre `redencion` reemplazada por
la decisión, `reversal_window_days`, y casos para: reversa en el último
día del plazo (aceptada), doble reversa, ajuste sin motivo, movimiento que
dejaría el saldo negativo, reversa topada en el saldo, reversas y
acumulaciones simultáneas, autorización por rol y comercio, y movimientos
cuyo signo o campos no corresponden a su tipo.

## Datos y migraciones (decisiones técnicas, pendientes de revisión)

- **`clientes`** (tabla base; la crea este issue): `id`, `documento`
  (índice único, nunca llave primaria — regla dura 3.10; se guarda sin
  espacios), `celular`, `email` (nullable, en minúsculas — 3.13),
  `saldo_puntos` (entero, por defecto 0), timestamps. Las columnas
  `nivel_*` las agrega E3-03; los campos de registro y acceso, E8-02.
- **`movimientos_puntos`**: `id`, `cliente_id`, `comercio_id` (obligatorio
  en `acumulacion` y `reversa`, que lo copia del original; nulo en los
  demás tipos), `transaccion_id` (nullable), `tipo`, `puntos` (entero con
  signo, nunca 0), `regla_id` (nullable, FK a `reglas`), `regla_version`
  (nullable), `vigencia_hasta` (nullable), `motivo` (nullable),
  `usuario_id` (nullable, FK a `users`), `created_at` puesto por la
  aplicación. Índices: `(cliente_id, created_at)` y `(transaccion_id)`.
- **`transaccion_id` todavía sin llave foránea**: `transacciones` es de
  E4-01, que agrega la restricción en su propia migración. Mientras tanto,
  la reversa encuentra la acumulación original por `transaccion_id` en el
  libro y usa el `created_at` de ese movimiento como fecha de la compra.
- **Signo y campos por tipo** (los verifican las Actions y el modelo):

  | `tipo`          | `puntos` | `regla_*`            | `comercio_id`        | `transaccion_id` | `motivo` / `usuario_id`                        |
  | --------------- | -------- | -------------------- | -------------------- | ---------------- | ---------------------------------------------- |
  | `acumulacion`   | > 0      | obligatorio          | obligatorio          | obligatorio      | opcional                                       |
  | `reversa`       | < 0      | copiado del original | copiado del original | obligatorio      | `motivo` obligatorio, `usuario_id` obligatorio |
  | `caducidad`     | < 0      | obligatorio          | —                    | —                | opcional                                       |
  | `ajuste_manual` | ≠ 0      | nulo                 | —                    | —                | ambos obligatorios                             |

- **Reversa**: revierte los puntos netos de la transacción (la suma de sus
  acumulaciones), una sola vez; una segunda reversa se rechaza. Si un
  movimiento negativo posterior (un ajuste o una caducidad) ya se llevó
  parte de esos puntos, la reversa se topa en el saldo actual: resta solo
  lo que queda, el saldo termina en cero y el movimiento registra el monto
  topado con un motivo que lo dice. Una compra anulada nunca deja puntos y
  el saldo nunca queda negativo.
- **`vigencia_hasta`** de una acumulación = fecha del movimiento + el
  `vigencia_puntos_meses` de la regla (vigencia por punto, como está
  redactado CLAUDE.md §9). E3-05 decide si el corte es diario o mensual;
  eso no cambia lo que se guarda aquí.
- **Concurrencia**: todo movimiento bloquea la fila del cliente
  (`lockForUpdate`) antes de leer el saldo, así los movimientos de un
  mismo cliente se serializan. Ese bloqueo es también lo que hace segura
  la verificación de "ya revertida"; no se usa un índice único parcial
  porque no es portable (3.13).
- **Saldo**: se actualiza con un incremento atómico dentro de la
  transacción, nunca leer-modificar-escribir sin el bloqueo. El saldo no
  puede bajar de cero: se rechaza cualquier movimiento que lo llevaría
  ahí, salvo la reversa, que se topa (ver Reversa).
- **Recálculo**: una Action devuelve la suma de `puntos` de un cliente y si
  coincide con `saldo_puntos`. La verificación nocturna que la usa es de
  E3-05.
- **Actions** (`app/Actions/Puntos/`, invocables): `RecordAcumulacion`,
  `ReverseTransaccion`, `RecordAjusteManual`, `RecordCaducidad`,
  `RecalculateSaldo`. Nada más escribe en `movimientos_puntos`.
- **Autorización** en las Actions: `ajuste_manual` solo para el
  administrador del grupo. `reversa` para el administrador del grupo o el
  administrador del comercio de la transacción. `acumulacion` y
  `caducidad` son operaciones del sistema, autorizadas por quien las
  llama (E4-01, E3-05).
- **Plazo de reversa** en `config/puntos.php` (`reversal_window_days`): la
  aplicación lo necesita en ejecución y producción no incluye `tests/`, así
  que una prueba verifica que coincide con el valor del fixture. El plazo
  cuenta días calendario en `America/Bogota`, no instantes: la reversa se
  admite mientras la fecha de hoy sea igual o anterior a la fecha de la
  compra más `reversal_window_days`. Una compra de hace 30 días se acepta a
  cualquier hora; una de hace 31, no.

## Decisiones a confirmar en la revisión

Propuestas por el implementador (2026-10-12); el revisor las aprueba con
esta spec.

1. Se elimina `redencion` de los tipos de movimiento (regla dura 3.1: un
   beneficio nunca cuesta puntos, y las redenciones tienen su propia
   tabla). Cierra la pregunta abierta del issue #8 y de CLAUDE.md §4.
2. `transacciones` queda en E4-01; E3-02 deja `transaccion_id` sin llave
   foránea hasta entonces.
3. El saldo nunca baja de cero: se rechazan los movimientos que lo harían,
   y la reversa se topa en el saldo actual.
4. Ajustes manuales: solo el administrador del grupo. Reversas: el
   administrador del grupo o el administrador del comercio de la
   transacción.

## Reglas duras de CLAUDE.md que aplican

- 3.1 Los puntos son estatus, no moneda
- 3.3 Snapshot de regla
- 3.4 Libro inmutable
- 3.9 Pesos enteros, `America/Bogota`
- 3.10 `documento` es índice único, nunca llave primaria
- 3.13 Código agnóstico del motor

## Depende de

E3-01 (tabla `reglas` para el snapshot), E2-01 (roles), E2-02
(`comercios`).
