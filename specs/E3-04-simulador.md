# E3-04 · Simulador de reglas

**Épica:** E3 · **Horas estimadas:** 14 · **Riesgo:** medio

## Objetivo

El administrador del grupo prueba un cambio de reglas antes de guardarlo:
para un comercio y un conjunto de compras de ejemplo, ve cuántos puntos
otorgarían las reglas vigentes y el borrador, y qué regla y versión los
produjo.

## Fuera de alcance

- Registrar compras y escribir puntos (E4-01). El simulador nunca toca
  `movimientos_puntos`, `clientes` ni `transacciones`.
- Niveles: el simulador no calcula a qué nivel llegaría un cliente
  (E3-03). Solo se simulan reglas de acumulación y bonificación.
- Guardar el borrador. Guardar sigue en el recurso de E3-01 ("Nueva
  versión").

## Reglas de negocio

1. La simulación es de solo lectura: nunca escribe en la base de datos.
2. El borrador es una definición de regla que no se ha guardado. Se valida
   con el mismo validador de E3-01 antes de simular; un borrador inválido
   muestra los errores de validación y no se simula nada.
3. El simulador compara, compra por compra, los puntos con las reglas
   vigentes hoy contra los puntos con el borrador reemplazando su regla.
4. Cada línea del resultado muestra los puntos base, la bonificación
   aplicada (si la hay), el tope aplicado (si lo hay), los puntos finales,
   y el código y la versión de la regla que los produjo. El borrador se
   marca como "borrador" en lugar de un número de versión.
5. El cálculo de puntos es el mismo que usará E4-01 para registrar compras
   reales, así que una simulación predice exactamente lo que otorgaría una
   compra real (ver Decisiones).

## Cálculo (supuestos pendientes de confirmar con el cliente)

El cliente confirmó las tasas (1 punto por cada $1.000, issue #1), pero no
cómo se combinan bonificaciones y topes. Hasta que lo haga, el cálculo
sigue estos supuestos, cada uno marcado con `TODO(cliente)` en el código y
listado en el issue de decisiones del cliente:

1. **Puntos base** = `floor(monto / moneda_por_punto) × puntos_por_unidad`
   de la categoría del comercio. Una categoría sin tasa da 0.
2. **Bonificaciones**: entre las reglas `bonificacion` activas cuyas
   condiciones se cumplen todas, solo aplica el `multiplicador` más alto;
   las bonificaciones no se multiplican entre sí.
3. **Orden**: puntos base → bonificación → `topes.puntos_por_transaccion`
   → `topes.puntos_por_dia` → `topes.puntos_por_mes`.
4. **Topes diarios y mensuales** cuentan los puntos de las compras de
   ejemplo anteriores en la misma simulación, como si todas fueran de un
   mismo cliente. El simulador no lee el historial real de clientes.

Los números de cada escenario viven en el fixture, no aquí.

## Criterios de aceptación

- [ ] Dada cualquier simulación, cuando corre, entonces no se inserta,
      actualiza ni borra ninguna fila en ninguna tabla (prueba explícita).
- [ ] Dado un borrador válido, cuando se simula, entonces cada línea
      muestra los puntos finales y la regla y versión aplicadas, con
      "borrador" para el borrador.
- [ ] Dado un borrador inválido, cuando se simula, entonces se muestran
      los errores de validación del esquema y no se calcula ningún
      resultado.
- [ ] Dados los escenarios del fixture, cuando se simulan, entonces los
      puntos coinciden con los esperados para las reglas vigentes y para el
      borrador.
- [ ] Dada una compra que cumple las condiciones de dos bonificaciones,
      cuando se simula, entonces solo aplica el multiplicador más alto.
- [ ] Dados topes por transacción, por día y por mes, cuando las compras
      de ejemplo los superan, entonces cada línea muestra el tope que la
      limitó.
- [ ] Dado un usuario que no es administrador del grupo, cuando abre el
      simulador, entonces recibe 403.

## Fixtures

`tests/Fixtures/simulador-escenarios.json` (nuevo): compras de ejemplo,
las reglas vigentes y un borrador, con los puntos esperados y la regla
aplicada por compra. Las definiciones de reglas reutilizan las formas de
`tests/Fixtures/reglas-validas.json`.

## Datos y diseño (decisiones técnicas, pendientes de revisión)

- **Sin migraciones.** El simulador lee `reglas`, `comercios` y
  `categorias` y no escribe nada.
- **`PuntosCalculator`** (`app/Reglas/`): una clase pura, sin acceso a la
  base de datos. Recibe la compra (monto, categoría del comercio, comercio,
  sede, fecha y hora) y las definiciones de reglas a usar, y devuelve un
  desglose (base, bonificación, tope, puntos finales, regla y versión).
  E4-01 la reutiliza para registrar compras reales; este issue la
  construye y la prueba.
- **Action `SimulateReglas`** (`app/Actions/Reglas/`, invocable): carga las
  reglas vigentes, valida el borrador con `ReglaDefinitionValidator`,
  reemplaza la regla del borrador en una copia del conjunto y corre
  `PuntosCalculator` sobre las compras de ejemplo con el conjunto vigente
  y con el del borrador. Corre dentro de una transacción que siempre se
  revierte, como segunda barrera además de la prueba explícita.
- **Página del panel** en "Niveles y reglas": elegir la regla a cambiar (su
  versión vigente llena el borrador con el formulario guiado de E3-01, o
  con el editor JSON para quien tiene ese permiso), elegir un comercio,
  escribir las compras de ejemplo (monto, fecha y hora, sede opcional) y
  ver una tabla con los puntos vigentes y los del borrador lado a lado, y
  la diferencia.
- **Autorización**: la misma del motor de reglas (`ReglaPolicy::viewAny`,
  solo el administrador del grupo).

## Decisiones a confirmar en la revisión

Propuestas por el implementador (2026-10-13); el revisor las aprueba con
esta spec.

1. La calculadora de puntos se construye aquí, como clase pura, y E4-01 la
   reutiliza. El issue dice que el simulador reutiliza un evaluador de
   E3-01, pero E3-01 no calcula puntos a propósito, y E4-01 viene después.
2. El borrador es una definición sin guardar, no una versión guardada:
   E3-01 no tiene estado de borrador, y agregarlo cambiaría su versionado.
3. Los supuestos de cálculo de arriba (redondeo, bonificaciones que no se
   multiplican entre sí, orden de los topes, topes contados dentro de la
   muestra) van al cliente como `TODO(cliente)` y se confirman antes de que
   E4-01 registre puntos reales.

## Reglas duras de CLAUDE.md que aplican

- 3.1 Los puntos son estatus, no moneda
- 3.8 Esquema JSON cerrado: el borrador pasa por el mismo validador
- 3.9 Pesos y puntos enteros, `America/Bogota` para los cortes de día y mes

## Depende de

E3-01 (`reglas`, validador y formulario guiado), E2-02 (categorías de
comercio).
