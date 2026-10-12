# E3-03 · Customer effective level

**Epic:** E3 · **Estimated hours:** 10 · **Risk:** high

## Goal

Compute each customer's level from its three possible sources (points,
membership, qualifying event), with immediate promotion and controlled
demotion.

## Out of scope

- Level change notifications and demotion notices (E7-01).
- The nightly job itself, its schedule and points expiry (E3-05). This
  feature provides the action the job calls.
- Buying or renewing a membership (E6-01). This feature only reads
  `nivel_por_membresia` and `membresia_vigente_hasta`.
- Customer screens that show the level (E8-03).

## Business rules

1. The effective level is the highest of `nivel_por_puntos`,
   `nivel_por_membresia` and `nivel_por_evento`, counting only the sources
   that are still valid.
2. The three sources are stored in separate columns. They are never
   collapsed.
3. Promotion is evaluated synchronously in the request that records the
   purchase, registers an event or activates a membership. The customer
   sees the new level immediately.
4. Demotion only happens in the nightly job, never in a user request.
5. Protection: when the points level falls below the current effective
   level, `proteccion_hasta` is set to today plus the `proteccion_meses` of
   the current `niveles` rule, and the customer does not drop before that
   date. The client confirmed 0 months (issue #1, decision 4; E3-01), so
   today the drop happens on the same night; the mechanism stays so the
   value can change without code.
6. When a membership or an event expires, the customer falls to the level
   of their other valid sources, not to the entry level.
7. `nivel_por_puntos` comes from `saldo_puntos` and the thresholds of the
   current `niveles` rule (E3-01). Points expire through the ledger (E3-02,
   E3-05); a drop only happens if losing those points lowers the level
   (issue #1, decision 4).
8. Qualifying events (issue #9, client decisions of 2026-10-10):
   - Each event is its own row in `eventos_calificados`, with its support
     document and its own end date (`vigente_hasta`).
   - **Property purchase** → Diamante while the event is valid. Its default
     validity is set by configuration (client: 12 months) and does not
     award points.
   - **School enrollment** → depends on how many valid enrollments the
     customer has: the count-to-level mapping is configuration (client: 1
     or 2 children → Oro, 3 or more → Diamante). Each enrollment's end
     date is entered when it is registered, because the school defines it.
   - Several events of the same kind do not add up levels.
     `nivel_por_evento` is the highest level the valid events give, and
     `evento_vigente_hasta` is the date until which that level is
     guaranteed: for properties, the latest end date; for enrollments, the
     date the count would fall below the level's minimum (with three
     enrollments, Diamante lasts until the third-latest end date). The
     nightly evaluation recomputes both.
   - When a child leaves the school, the event is closed: its end date
     moves to the closing date, with a reason, and the change is audited.
9. Every change of `nivel_efectivo` is recorded with its date, previous and
   new level, and the reason.

## Acceptance criteria

- [ ] Given a customer who is Oro by points and Diamante by a valid
      membership, when their level is queried, then it is Diamante.
- [ ] Given that same customer, when the membership expires and the
      nightly evaluation runs, then they are Oro, not Bronce.
- [ ] Given a customer who crosses the Oro threshold with a purchase, when
      the request ends, then their effective level is already Oro.
- [ ] Given a customer whose balance falls below the threshold, when the
      nightly evaluation runs inside the protection period, then they do
      not drop.
- [ ] Given that same customer after the protection period, when the
      nightly evaluation runs, then they drop and the change is recorded
      with its date.
- [ ] Given `proteccion_meses: 0`, when the balance falls below the
      threshold, then the customer drops on the next nightly evaluation.
- [ ] Given a valid property purchase, when the level is computed, then it
      is Diamante even if the points say Bronce.
- [ ] Given two valid school enrollments, then `nivel_por_evento` is Oro;
      given three, it is Diamante; when one of the three is closed, it goes
      back to Oro on the next evaluation.
- [ ] No code path lowers the level inside an HTTP request.

## Fixtures

`tests/Fixtures/niveles-escenarios.json` — updated in this spec PR: keys
and prose in English, protection taken from the rule, the event
configuration (`event_rules`), and new cases for school enrollments,
closing an enrollment, several properties and zero-month protection.

## Data and migrations (technical decisions, pending review)

- **`clientes`** gets: `nivel_por_puntos` (default `bronce`),
  `nivel_por_membresia` + `membresia_vigente_hasta` (nullable, written by
  E6-01), `nivel_por_evento` + `evento_vigente_hasta` (nullable),
  `nivel_efectivo` (default `bronce`), `nivel_efectivo_desde`,
  `proteccion_hasta` (nullable). Levels are stored as strings
  (`bronce`, `plata`, `oro`, `diamante`) through a `Nivel` enum that knows
  their order.
- **`eventos_calificados`**: `id`, `cliente_id`, `comercio_id` (the
  merchant where it happened), `tipo` (`compra_inmueble` |
  `matricula_colegio`), `vigente_desde`, `vigente_hasta`, `soporte_path`
  (file on a private disk), `motivo_cierre` (nullable), `registrado_por`,
  `cerrado_por` (nullable), timestamps. Rows are not deleted; closing only
  moves `vigente_hasta` earlier, and the `AuditoriaObserver` (E2-01) keeps
  the before and after.
- **No `nivel_otorgado` column** on events (CLAUDE.md §4 lists it): the
  school level depends on how many enrollments are valid, so a per-row
  level would be wrong. The level is derived from `event_rules`.
- **`cambios_nivel`** (append-only): `id`, `cliente_id`, `nivel_anterior`,
  `nivel_nuevo`, `motivo` (`ascenso_compra`, `ascenso_evento`,
  `ascenso_membresia`, `descenso_nocturno`), `created_at` set by the
  application.
- **Event rules** live in `config/niveles.php` (`event_rules`), with a test
  asserting they equal the fixture. They could move to the E3-01 rule
  engine later if the client wants to edit them from the panel.
- **Actions** (`app/Actions/Niveles/`, invokable):
  - `RaiseNivelEfectivo`: recomputes the three sources and sets
    `nivel_efectivo` to the higher of the current one and the computed
    one. It can never lower it. E4-01 calls it in the same transaction as
    the accrual; event registration and E6-01 call it too.
  - `ReevaluateNivelEfectivo`: the nightly evaluation. It recomputes the
    sources, applies protection and may lower the level. It refuses to run
    outside the console (queue worker or scheduler), and an architecture
    test checks that only `App\Console` and `App\Jobs` use it.
  - `RegisterEventoCalificado` and `CloseEventoCalificado`.
  - Every level change writes `cambios_nivel` and `nivel_efectivo_desde`
    inside the same transaction, with the customer row locked
    (`lockForUpdate`), like the ledger (E3-02).
- **Panel**: a resource to register and close qualifying events, with the
  support file upload and customer lookup by `documento`. No editing or
  deleting of events.
- **Authorization**: property purchases are registered by the group
  administrator (issue #1, decision 5). School enrollments are registered
  and closed by the group administrator or the merchant administrator of
  the school, and only for merchants in the `colegio` category.

## Decisions to confirm in review

Proposed by the implementer (2026-10-12); the reviewer approves them with
this spec.

1. Protection comes from the `niveles` rule (`proteccion_meses`, now 0)
   instead of a fixed 3 months. CLAUDE.md §3.5 and §3.6 still say "3 months
   of protection and a notice 30 days before" and should be updated
   (issue #1 already asks for it, together with E3-05).
2. `eventos_calificados` has no `nivel_otorgado` column; the level is
   derived from `event_rules`.
3. Event rules live in configuration, not in the rule engine.
4. Level history goes to a new `cambios_nivel` table.
5. Who registers and closes each kind of event (proposal above).

## Hard rules from CLAUDE.md that apply

- 3.5 Three separate sources
- 3.6 Synchronous promotion, nightly demotion
- 3.9 `America/Bogota` for validity dates
- 3.13 Engine-agnostic code

## Depends on

E3-01 (`niveles` rule), E3-02 (`clientes` and `saldo_puntos`), E2-01
(roles and audit), E2-02 (merchant categories).
