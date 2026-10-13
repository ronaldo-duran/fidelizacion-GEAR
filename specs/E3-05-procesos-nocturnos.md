# E3-05 · Procesos automáticos nocturnos

**Épica:** E3 · **Horas estimadas:** 12 · **Riesgo:** alto

## Objetivo

Procesos programados que mantienen el programa al día sin intervención:
caducidad de puntos, avisos de descenso, recálculo del nivel cuando vencen
puntos, membresías o eventos, y verificación de que los saldos cuadran.

## Fuera de alcance

- Enviar los avisos por correo o in-app (E7-01). Aquí solo se registra qué
  aviso corresponde a quién; E7-01 los envía.
- Cambiar el estado de las membresías y cobrarlas (E6-01). Aquí solo se
  recalcula el nivel cuando `membresia_vigente_hasta` ya pasó.
- La lógica del nivel efectivo y la protección (E3-03). Aquí se llama a
  `ReevaluateNivelEfectivo`.
- Pantallas para ver o relanzar procesos.

## Reglas de negocio

1. **Vencimiento por punto, con corte diario** (issue #1, decisiones 3 y
   4; CLAUDE.md §9): cada lote de puntos vence a los
   `vigencia_puntos_meses` de la regla de acumulación desde su fecha
   (`vigencia_hasta` de E3-02). Cada noche se caduca lo que venció hasta
   ese día.
2. **Un lote** es todo movimiento positivo: una `acumulacion` o un
   `ajuste_manual` positivo. Los ajustes positivos también vencen, con la
   vigencia de la regla de acumulación vigente en la fecha del ajuste.
3. **Qué descuenta cada movimiento negativo**: una `reversa` descuenta el
   lote de su propia compra; un `ajuste_manual` negativo descuenta primero
   los lotes que vencen antes (FIFO); una `caducidad` descuenta los lotes
   que caducó.
4. La caducidad nunca borra: inserta movimientos `caducidad` negativos a
   través de `RecordCaducidad` (E3-02), uno por cada regla y versión de los
   lotes que vencen, con su snapshot. Los lotes que vienen de un ajuste
   positivo se caducan sin regla y con motivo.
5. **Descenso** (issue #1, decisión 4): un cliente solo baja cuando vencen
   puntos y perderlos le baja la categoría, siempre en la evaluación
   nocturna, nunca en una compra (regla dura 3.6). La protección la define
   `proteccion_meses` de la regla `niveles` (hoy 0).
6. **Avisos de descenso**: para cada cantidad de días de `avisos_dias_antes`
   de la regla `niveles` (hoy 30, 7 y 1), se registra un aviso al cliente
   cuyos puntos vencerán en exactamente esos días **solo si** perderlos lo
   bajaría de categoría, contando las otras fuentes que sigan vigentes ese
   día (membresía o evento). Si no baja, no hay aviso.
7. **Vencimiento de membresías y eventos**: cuando `membresia_vigente_hasta`
   o `evento_vigente_hasta` ya pasó, se recalcula el nivel desde las otras
   fuentes vigentes (E3-03).
8. **Verificación de saldos**: cada noche se recalcula el saldo de cada
   cliente desde cero (E3-02). Una diferencia se registra y genera una
   alerta; nunca se corrige sola.
9. **Idempotencia**: correr los procesos dos veces la misma noche, o volver
   a correr una noche que falló, produce el mismo resultado, sin duplicar
   movimientos ni avisos.
10. Los procesos corren con el scheduler en hora de `America/Bogota` y se
    ejecutan en Horizon.

## Criterios de aceptación

- [ ] Dado un lote que venció ayer, cuando corre la caducidad, entonces
      existe un movimiento `caducidad` por sus puntos restantes, con la
      regla y versión del lote, y el saldo bajó en esa cantidad.
- [ ] Dado que la caducidad ya corrió esta noche, cuando corre otra vez,
      entonces no se inserta ningún movimiento nuevo.
- [ ] Dado un ajuste negativo anterior, cuando vencen lotes, entonces solo
      caduca lo que el ajuste no consumió, empezando por los lotes más
      viejos.
- [ ] Dada una compra revertida, cuando vence su lote, entonces no caduca
      nada de ese lote.
- [ ] Dado un ajuste manual positivo, cuando pasa su vigencia, entonces
      caduca sin regla y con motivo.
- [ ] Dado un cliente cuyos puntos que vencen lo bajan de Oro a Plata,
      cuando corre la evaluación nocturna después de la caducidad,
      entonces queda en Plata y el cambio queda registrado (E3-03).
- [ ] Dado un cliente en protección, cuando corre la evaluación, entonces
      no baja.
- [ ] Dado un cliente cuyos puntos vencen en 30, 7 o 1 días y perderlos lo
      baja de categoría, cuando corre el proceso, entonces se registra un
      aviso con esos días; si no lo baja, o una membresía o un evento
      vigentes lo sostienen, no se registra aviso.
- [ ] Dado que el aviso ya se registró, cuando el proceso corre de nuevo,
      entonces no se duplica.
- [ ] Dada una membresía que venció ayer, cuando corre el proceso,
      entonces el cliente queda en el nivel de sus otras fuentes vigentes.
- [ ] Dado cualquier cliente después de la caducidad, cuando se recalcula
      el saldo desde cero, entonces coincide con el materializado; si no
      coincide, se registra una alerta y el saldo no se toca.
- [ ] Dado que una noche no corrió, cuando corre la siguiente, entonces
      caduca también lo que venció la noche perdida.

## Fixtures

`tests/Fixtures/caducidad-escenarios.json` (nuevo, en inglés): lotes,
movimientos negativos, fecha de corte, caducidades esperadas por regla y
versión, avisos esperados y saldos finales. Los umbrales y los días de
aviso salen de la regla `niveles` de `reglas-validas.json`.

## Datos y diseño (decisiones técnicas, pendientes de revisión)

- **Cálculo de lotes** (`app/Puntos/LoteCalculator`, clase pura): recibe
  los movimientos de un cliente y una fecha de corte, y devuelve el
  remanente de cada lote aplicando las reglas 2 y 3. Lo usan la caducidad
  y los avisos.
- **Idempotencia por recálculo**: la caducidad caduca el remanente de los
  lotes vencidos. Como cada `caducidad` ya descontó lo que caducó, una
  segunda corrida encuentra remanente cero. Todo ocurre con la fila del
  cliente bloqueada (`lockForUpdate`), igual que E3-02. No hace falta un
  índice único parcial (3.13).
- **`avisos_descenso`** (tabla nueva): `id`, `cliente_id`,
  `fecha_vencimiento`, `dias_antes`, `puntos_que_vencen`, `nivel_actual`,
  `nivel_resultante`, `enviado_at` (nullable, lo llena E7-01),
  `created_at`. Restricción única `(cliente_id, fecha_vencimiento,
dias_antes)`: un aviso repetido choca en la base de datos y se ignora.
- **Jobs** (`app/Jobs/Nocturno/`), en este orden cada noche:
  1. `ExpirePuntos`: caducidad.
  2. `ReevaluateNiveles`: llama a `ReevaluateNivelEfectivo` (E3-03) para
     los clientes con caducidad esa noche o con membresía o evento
     vencidos.
  3. `RegisterAvisosDescenso`: avisos de descenso.
  4. `VerifySaldos`: verificación de saldos con `RecalculateSaldo` (E3-02).
     Las diferencias van al canal de log de alertas.
- **Programación**: `routes/console.php`, `dailyAt` a la hora definida en
  `config/nocturno.php`, con `timezone('America/Bogota')`,
  `withoutOverlapping()` y `onOneServer()`. Cada job recibe la fecha de
  corte, recorre los clientes con `chunkById` y procesa cada cliente en su
  propia transacción, en la cola `nocturno` de Horizon.
- **Noche perdida**: como la caducidad toma todo lo vencido hasta la fecha
  de corte, la noche siguiente recupera lo pendiente. Los avisos de una
  noche perdida no se reenvían tarde.
- **Ajuste a E3-02**: el `ajuste_manual` positivo guarda `vigencia_hasta`,
  y la `caducidad` de un lote de ajuste va sin regla y con motivo. Se
  actualiza la tabla de signos y campos de la spec E3-02 (PR #57).
- **CLAUDE.md §3.5 y §3.6** se actualizan en este PR: la protección sale de
  la regla `niveles` y los avisos de `avisos_dias_antes` (issue #1,
  decisión 4).

## Decisiones a confirmar en la revisión

Propuestas por el implementador (2026-10-13); el revisor las aprueba con
esta spec.

1. Vencimiento por punto con corte diario, en lugar de año calendario.
   Cierra el pendiente interno de CLAUDE.md §9.
2. Los ajustes negativos descuentan primero los lotes más viejos (FIFO).
3. Los ajustes manuales positivos vencen como una acumulación.
4. Los avisos se registran en `avisos_descenso` y E7-01 los envía.
5. CLAUDE.md §3.5 y §3.6 se actualizan en este PR.

## Reglas duras de CLAUDE.md que aplican

- 3.3 Snapshot de regla en cada caducidad
- 3.4 Libro inmutable: la caducidad inserta, nunca borra
- 3.5 Tres fuentes separadas
- 3.6 Descenso solo nocturno
- 3.9 `America/Bogota` para el corte diario
- 3.13 Código agnóstico del motor

## Depende de

E3-02 (libro, `RecordCaducidad`, `RecalculateSaldo`), E3-03
(`ReevaluateNivelEfectivo`), E3-01 (reglas de acumulación y de niveles).
