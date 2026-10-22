# E4-01 · Registro de compras: acumulación y ascenso

**Épica:** E4 · **Horas estimadas:** 8 · **Riesgo:** alto

## Objetivo

Una sola Action de dominio que registra una compra y otorga los puntos
según las reglas vigentes, con el ascenso de nivel en la misma petición.
La usan el cajero (E4-02), la carga masiva (E4-03) y la API pública
(E9-01).

## Fuera de alcance

- La pantalla del cajero (E4-02), la carga masiva (E4-03) y los endpoints
  de la API con su header `Idempotency-Key` (E9-01). Aquí se define la
  Action que los tres llaman y la tabla que los protege.
- Identificar al cliente por QR (E4-04). Aquí el cliente llega por
  tipo y número de documento (CLAUDE.md §3.10) o por `cliente_id` ya
  resuelto.
- El registro del cliente (E8-02). Aquí solo se define la Action que el
  registro llama para acreditar sus compras pendientes.
- Los avisos al cliente (E7-01).

## Reglas de negocio

1. **Una compra, una vez** (regla dura 3.7): `transacciones` tiene una
   restricción única sobre `(comercio_id, referencia_externa)`. El cajero
   genera la referencia por cada intento de registro; un POS manda la suya.
   Si llega otra vez la misma referencia del mismo comercio con el mismo
   cliente y monto, se devuelve la compra ya registrada sin duplicar
   puntos; si los datos difieren, se rechaza por conflicto.
2. **Todo en una transacción de base de datos**: insertar la compra →
   calcular los puntos con las reglas vigentes → insertar los movimientos
   `acumulacion` con su snapshot (reglas duras 3.3 y 3.4) → actualizar el
   saldo → evaluar el ascenso (E3-03, regla dura 3.6). Si algo falla, no
   queda nada.
3. **Montos en pesos enteros** (regla dura 3.9), mayores que cero. El
   comercio y la sede deben estar activos y la sede debe pertenecer al
   comercio.
4. **Puntos por regla**: si la compra tiene bonificación, quedan dos
   movimientos: los puntos base con el snapshot de la regla de acumulación
   y los puntos extra con el snapshot de la regla de bonificación. Cada
   punto queda trazado a su regla. Si un tope recorta, se descuenta primero
   de los puntos extra.
5. **Compra pendiente**: si el tipo y número de documento no corresponden
   a ningún cliente,
   la compra se guarda como pendiente, sin puntos. El monto nunca se pierde
   (decisión de producto del diseño, 1h y 1i) y no tiene plazo para
   reclamarse.
6. **Acreditación de pendientes**: cuando el cliente se registra (E8-02),
   sus compras pendientes se acreditan una sola vez, en orden de fecha, con
   las reglas vigentes **en la fecha de la compra** (regla dura 3.3). Los
   puntos vencen contando desde esa fecha; si ya vencieron, la caducidad de
   esa noche los retira (E3-05).
7. **Anulación** (botón "Anular" de la confirmación 1g): se hace con la
   reversa del libro (E3-02), dentro de su plazo. Anular una compra
   pendiente solo la marca como anulada, porque no tiene puntos.
8. **Topes diarios y mensuales**: cuentan los puntos de acumulación que el
   cliente ya ganó ese día o ese mes, en `America/Bogota`.

## Criterios de aceptación

- [ ] Dados dos registros con la misma referencia del mismo comercio
      procesados al mismo tiempo, cuando ambos terminan, entonces existe
      una sola compra y los puntos se otorgaron una vez (lo garantiza la
      restricción de la base de datos; prueba de concurrencia).
- [ ] Dado un reintento con la misma referencia y los mismos datos, cuando
      llega, entonces devuelve la compra existente sin puntos nuevos; con
      datos distintos, entonces se rechaza por conflicto.
- [ ] Dada la misma referencia en dos comercios distintos, entonces se
      registran las dos.
- [ ] Dado un cliente que cruza un umbral con una compra, cuando termina la
      petición, entonces ya está en el nivel nuevo.
- [ ] Dada una compra con bonificación, entonces existen dos movimientos
      `acumulacion`, cada uno con el snapshot de su regla, y el saldo subió
      la suma.
- [ ] Dado un tope diario y compras anteriores del mismo día, cuando la
      compra lo supera, entonces solo se otorga lo que falta para el tope.
- [ ] Dado un tipo y número de documento sin cliente, cuando se registra la
      compra, entonces queda pendiente y no hay movimientos.
- [ ] Dado un cliente que se registra con compras pendientes, cuando se
      acreditan, entonces cada una se acredita una sola vez, con las reglas
      de su fecha de compra, aunque las reglas hayan cambiado después; dos
      acreditaciones simultáneas no duplican puntos.
- [ ] Dada una compra pendiente con un tipo de documento, cuando se
      registra un cliente con el mismo número y otro tipo, entonces no se le
      acredita.
- [ ] Dado un comercio o una sede inactivos, o una sede de otro comercio,
      cuando se registra una compra, entonces se rechaza.
- [ ] Dado un monto de cero, negativo o con decimales, entonces se
      rechaza.
- [ ] Dado un cajero de otra sede o de otro comercio, cuando intenta
      registrar, entonces recibe 403.
- [ ] Dada una compra dentro del plazo, cuando se anula, entonces existe
      la reversa y la compra queda anulada; una compra pendiente anulada no
      genera movimientos.
- [ ] Dada una compra que no alcanza un punto, entonces queda registrada
      sin movimientos.

## Fixtures

`tests/Fixtures/compras-escenarios.json` (nuevo, en inglés): reglas de
partida, clientes y escenarios de registro, repetición, concurrencia,
bonificación, topes, pendientes, validaciones, autorización y anulación.
Las reglas reutilizan las formas de `tests/Fixtures/reglas-validas.json`.

## Datos y diseño (decisiones técnicas, pendientes de revisión)

- **`transacciones`**: `id`, `cliente_id` (nullable mientras está
  pendiente), `tipo_documento_cliente` y `documento_cliente` (los que se
  digitaron; el número normalizado en mayúsculas y sin espacios, §3.10),
  `comercio_id`, `sede_id` (obligatoria para el cajero y la carga masiva;
  opcional en la API), `monto` (entero), `referencia_externa` (sin
  espacios y en minúsculas, regla 3.13), `idempotency_key` (nullable, la
  usa E9-01), `canal` (`cajero` | `carga_masiva` | `api`), `estado`
  (`acreditada` | `pendiente` | `anulada`), `acreditada_at`,
  `registrada_por` (nullable en la API), `created_at` puesto por la
  aplicación. **Restricción única `(comercio_id, referencia_externa)`.**
  Índice `(tipo_documento_cliente, documento_cliente, estado)` para
  buscar pendientes: las pendientes se acreditan por el par tipo + número,
  nunca por el número solo.
- **Llave foránea de E3-02**: esta feature agrega la FK de
  `movimientos_puntos.transaccion_id` a `transacciones.id`, que E3-02 dejó
  pendiente.
- **La compra no se edita**: solo cambia el `estado`, una vez, de
  `pendiente` a `acreditada` o a `anulada`, o de `acreditada` a `anulada`.
  El modelo rechaza cualquier otro cambio.
- **Repetición**: ante la violación de la restricción única, se lee la
  compra existente y se comparan cliente (o tipo y número de documento) y
  monto: iguales →
  se devuelve; distintos → conflicto.
- **Acreditación sin duplicados**: cada pendiente se pasa a `acreditada`
  con un UPDATE condicionado a `estado = pendiente`; si afecta cero filas,
  otra acreditación ya la tomó y se salta. Todo con la fila del cliente
  bloqueada (`lockForUpdate`), como el libro (E3-02).
- **Cálculo**: `PuntosCalculator` de E3-04, con las versiones de las
  reglas vigentes en la fecha de la compra y el historial real del cliente
  para los topes. Si el código de E3-04 no se ha construido cuando empiece
  esta feature, se construye aquí según la spec de E3-04. Los supuestos de
  cálculo de E3-04 (redondeo, bonificaciones que no se multiplican entre
  sí, orden de los topes) siguen marcados `TODO(cliente)` y deben
  confirmarse antes de otorgar puntos reales.
- **Reglas en una fecha**: un scope `vigenteEn($fecha)` en el modelo
  `Regla` (E3-01) devuelve la versión que regía en ese instante.
- **Actions** (`app/Actions/Compras/`, invocables):
  - `RegisterCompra`: la de las reglas 1 a 5 y 8. Devuelve la compra, los
    puntos (base, extra, tope) y el nivel antes y después.
  - `CreditPendingCompras`: la llama el registro del cliente (E8-02) en su
    misma transacción.
  - `AnnulCompra`: la anulación, que usa `ReverseTransaccion` (E3-02).
  - Los puntos entran solo por `RecordAcumulacion` (E3-02) y el nivel solo
    por `RaiseNivelEfectivo` (E3-03).
- **Autorización** en las Actions: el cajero registra solo en su sede; el
  administrador de comercio, en cualquier sede de su comercio; el
  administrador del grupo, en cualquiera. La API se autoriza en E9-01. La
  anulación sigue la autorización de la reversa de E3-02.

## Decisiones a confirmar en la revisión

Propuestas por el implementador (2026-10-14); el revisor las aprueba con
esta spec.

1. Una compra con bonificación deja dos movimientos `acumulacion`, uno por
   regla, y los topes recortan primero los puntos extra.
2. Las compras pendientes se acreditan con las reglas de la fecha de
   compra y sus puntos vencen desde esa fecha.
3. Las compras pendientes no tienen plazo para reclamarse.
4. Una referencia repetida con los mismos datos devuelve la compra
   existente; con datos distintos, conflicto.
5. Si `PuntosCalculator` (E3-04) no existe todavía, se construye aquí.

## Reglas duras de CLAUDE.md que aplican

- 3.3 Snapshot de regla en cada movimiento
- 3.4 Libro inmutable
- 3.6 Ascenso síncrono
- 3.7 Idempotencia por restricción de base de datos
- 3.9 Pesos enteros, `America/Bogota`
- 3.13 Código agnóstico del motor

## Depende de

E3-01 (reglas), E3-02 (libro de puntos y reversa), E3-03 (ascenso), E3-04
(`PuntosCalculator`), E2-01 (roles y alcance por sede), E2-02 (comercios y
sedes activos).
