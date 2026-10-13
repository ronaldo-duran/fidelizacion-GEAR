# E3-03 · Nivel efectivo del cliente

**Épica:** E3 · **Horas estimadas:** 10 · **Riesgo:** alto

## Objetivo

Calcular el nivel de cada cliente a partir de sus tres fuentes posibles
(puntos, membresía, evento calificado), con ascenso inmediato y descenso
controlado.

## Fuera de alcance

- Notificaciones de cambio de nivel y avisos de descenso (E7-01).
- El job nocturno en sí, su programación y la caducidad de puntos (E3-05).
  Esta feature entrega la Action que el job llama.
- Comprar o renovar una membresía (E6-01). Aquí solo se leen
  `nivel_por_membresia` y `membresia_vigente_hasta`.
- Pantallas del cliente que muestran el nivel (E8-03).

## Reglas de negocio

1. El nivel efectivo es el más alto entre `nivel_por_puntos`,
   `nivel_por_membresia` y `nivel_por_evento`, considerando solo los
   vigentes.
2. Las tres fuentes se guardan en campos separados. Nunca se colapsan.
3. El ascenso se evalúa de forma síncrona en la petición que registra la
   compra, registra un evento o activa una membresía. El cliente ve su
   nivel nuevo al instante.
4. El descenso solo ocurre en el job nocturno, nunca en una petición de
   usuario.
5. Protección: cuando el nivel por puntos cae por debajo del nivel efectivo
   actual, `proteccion_hasta` queda en hoy más los `proteccion_meses` de la
   regla `niveles` vigente, y el cliente no baja antes de esa fecha. El
   cliente confirmó 0 meses (issue #1, decisión 4; E3-01), así que hoy la
   bajada ocurre esa misma noche; el mecanismo se mantiene para que el
   valor pueda cambiar sin tocar código.
6. Al vencerse una membresía o un evento, el cliente cae al nivel que le
   corresponda por sus otras fuentes vigentes, no al nivel de entrada.
7. `nivel_por_puntos` sale de `saldo_puntos` y de los umbrales de la regla
   `niveles` vigente (E3-01). Los puntos vencen a través del libro (E3-02,
   E3-05); solo se baja si perder esos puntos baja de categoría (issue #1,
   decisión 4).
8. Eventos calificados (issue #9, decisiones del cliente del 2026-10-10):
   - Cada evento es su propia fila en `eventos_calificados`, con su
     soporte y su propia fecha de fin (`vigente_hasta`).
   - **Compra de inmueble** → Diamante mientras el evento esté vigente. Su
     vigencia por defecto la define la configuración (cliente: 12 meses) y
     no otorga puntos.
   - **Matrícula en el colegio** → depende de cuántas matrículas vigentes
     tenga el cliente: la relación entre cantidad y nivel es configuración
     (cliente: 1 o 2 hijos → Oro, 3 o más → Diamante). La fecha de fin de
     cada matrícula se captura al registrarla, porque la define el colegio.
   - Varios eventos del mismo tipo no suman niveles. `nivel_por_evento` es
     el nivel más alto que dan los eventos vigentes, y
     `evento_vigente_hasta` es la fecha hasta la que ese nivel está
     garantizado: en inmuebles, la fecha de fin más lejana; en matrículas,
     la fecha en que la cantidad bajaría del mínimo del nivel (con tres
     matrículas, el Diamante dura hasta la tercera fecha de fin más
     lejana). La evaluación nocturna recalcula ambos.
   - Si un niño se retira del colegio, el evento se cierra: su fecha de fin
     pasa a la fecha de cierre, con un motivo, y el cambio queda en
     auditoría.
9. Todo cambio de `nivel_efectivo` queda registrado con su fecha, el nivel
   anterior, el nuevo y el motivo.

## Criterios de aceptación

- [ ] Dado un cliente Oro por puntos y Diamante por membresía vigente,
      cuando se consulta su nivel, entonces es Diamante.
- [ ] Dado ese mismo cliente, cuando vence la membresía y corre la
      evaluación nocturna, entonces queda en Oro, no en Bronce.
- [ ] Dado un cliente que cruza el umbral de Oro con una compra, cuando
      termina la petición, entonces su nivel efectivo ya es Oro.
- [ ] Dado un cliente cuyo saldo cae bajo el umbral, cuando corre la
      evaluación nocturna dentro del periodo de protección, entonces no
      baja.
- [ ] Dado ese mismo cliente pasada la protección, cuando corre la
      evaluación nocturna, entonces baja y queda registrado el cambio con su
      fecha.
- [ ] Dado `proteccion_meses: 0`, cuando el saldo cae bajo el umbral,
      entonces el cliente baja en la siguiente evaluación nocturna.
- [ ] Dada una compra de inmueble vigente, cuando se calcula el nivel,
      entonces es Diamante aunque los puntos digan Bronce.
- [ ] Dadas dos matrículas vigentes, entonces `nivel_por_evento` es Oro;
      dadas tres, es Diamante; cuando se cierra una de las tres, vuelve a
      Oro en la siguiente evaluación.
- [ ] Ningún camino de código baja el nivel dentro de una petición HTTP.

## Fixtures

`tests/Fixtures/niveles-escenarios.json` — se actualiza en este PR de
spec: claves y textos en inglés, la protección tomada de la regla, la
configuración de eventos (`event_rules`) y casos nuevos para matrículas,
cierre de una matrícula, varios inmuebles y protección de cero meses.

## Datos y migraciones (decisiones técnicas, pendientes de revisión)

- **`clientes`** suma: `nivel_por_puntos` (por defecto `bronce`),
  `nivel_por_membresia` + `membresia_vigente_hasta` (nullable, los escribe
  E6-01), `nivel_por_evento` + `evento_vigente_hasta` (nullable),
  `nivel_efectivo` (por defecto `bronce`), `nivel_efectivo_desde`,
  `proteccion_hasta` (nullable). Los niveles se guardan como texto
  (`bronce`, `plata`, `oro`, `diamante`) a través de un enum `Nivel` que
  conoce su orden.
- **`eventos_calificados`**: `id`, `cliente_id`, `comercio_id` (el
  comercio donde ocurrió), `tipo` (`compra_inmueble` |
  `matricula_colegio`), `vigente_desde`, `vigente_hasta`, `soporte_path`
  (archivo en un disco privado), `motivo_cierre` (nullable),
  `registrado_por`, `cerrado_por` (nullable), timestamps. Las filas no se
  borran; cerrar solo adelanta `vigente_hasta`, y el `AuditoriaObserver`
  (E2-01) guarda el antes y el después.
- **Sin columna `nivel_otorgado`** en los eventos (CLAUDE.md §4 la lista):
  el nivel del colegio depende de cuántas matrículas estén vigentes, así
  que un nivel por fila sería engañoso. El nivel se deriva de
  `event_rules`.
- **`cambios_nivel`** (append-only): `id`, `cliente_id`,
  `nivel_anterior`, `nivel_nuevo`, `motivo` (`ascenso_compra`,
  `ascenso_evento`, `ascenso_membresia`, `descenso_nocturno`),
  `created_at` puesto por la aplicación.
- **Reglas de eventos** en `config/niveles.php` (`event_rules`), con una
  prueba que verifica que coinciden con el fixture. Podrían pasar al motor
  de reglas de E3-01 más adelante si el cliente quiere editarlas desde el
  panel.
- **Actions** (`app/Actions/Niveles/`, invocables):
  - `RaiseNivelEfectivo`: recalcula las tres fuentes y deja
    `nivel_efectivo` en el mayor entre el actual y el calculado. Nunca lo
    baja. E4-01 la llama en la misma transacción que la acumulación;
    también la llaman el registro de eventos y E6-01.
  - `ReevaluateNivelEfectivo`: la evaluación nocturna. Recalcula las
    fuentes, aplica la protección y puede bajar el nivel. Se niega a correr
    fuera de la consola (worker de colas o scheduler), y una prueba de
    arquitectura verifica que solo la usan `App\Console` y `App\Jobs`.
  - `RegisterEventoCalificado` y `CloseEventoCalificado`.
  - Todo cambio de nivel escribe `cambios_nivel` y `nivel_efectivo_desde`
    dentro de la misma transacción, con la fila del cliente bloqueada
    (`lockForUpdate`), igual que el libro (E3-02).
- **Panel**: un recurso para registrar y cerrar eventos calificados, con
  carga del archivo de soporte y búsqueda del cliente por `documento`. Sin
  edición ni borrado de eventos.
- **Autorización**: las compras de inmueble las registra el administrador
  del grupo (issue #1, decisión 5). Las matrículas las registran y cierran
  el administrador del grupo o el administrador del comercio del colegio,
  y solo para comercios de la categoría `colegio`.

## Decisiones a confirmar en la revisión

Propuestas por el implementador (2026-10-12); el revisor las aprueba con
esta spec.

1. La protección sale de la regla `niveles` (`proteccion_meses`, hoy 0) en
   lugar de 3 meses fijos. CLAUDE.md §3.5 y §3.6 todavía dicen "3 meses de
   protección y aviso 30 días antes" y deberían actualizarse (el issue #1
   ya lo pide, junto con E3-05).
2. `eventos_calificados` no tiene columna `nivel_otorgado`; el nivel se
   deriva de `event_rules`.
3. Las reglas de eventos viven en configuración, no en el motor de reglas.
4. El historial de niveles va en una tabla nueva, `cambios_nivel`.
5. Quién registra y cierra cada tipo de evento (propuesta arriba).

## Reglas duras de CLAUDE.md que aplican

- 3.5 Tres fuentes separadas
- 3.6 Ascenso síncrono, descenso nocturno
- 3.9 `America/Bogota` para las fechas de vigencia
- 3.13 Código agnóstico del motor

## Depende de

E3-01 (regla `niveles`), E3-02 (`clientes` y `saldo_puntos`), E2-01
(roles y auditoría), E2-02 (categorías de comercio).
