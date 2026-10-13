# E3-04 · Rule simulator

**Epic:** E3 · **Estimated hours:** 14 · **Risk:** medium

## Goal

The group administrator tries a rule change before saving it: for a merchant
and a set of sample purchases, they see how many points the current rules
and the draft would award, and which rule and version produced them.

## Out of scope

- Recording purchases and writing points (E4-01). The simulator never
  touches `movimientos_puntos`, `clientes` or `transacciones`.
- Levels: the simulator does not compute which level a customer would reach
  (E3-03). Only accrual and bonus rules are simulated.
- Saving the draft. Saving stays in the E3-01 resource ("Nueva versión").

## Business rules

1. The simulation is read-only: it never writes to the database.
2. The draft is a rule definition that has not been saved. It is validated
   with the same E3-01 validator before simulating; an invalid draft shows
   the validation errors and simulates nothing.
3. The simulator compares, purchase by purchase, the points under the rules
   in force today against the points with the draft replacing its rule.
4. Every result line shows the base points, the bonus applied (if any), the
   cap applied (if any), the final points, and the rule code and version
   that produced them. The draft is labeled "borrador" instead of a version
   number.
5. The points calculation is the same one E4-01 will use to record real
   purchases, so a simulation predicts exactly what a real purchase would
   award (see Decisions).

## Calculation (assumptions pending client confirmation)

The client has confirmed the rates (1 point per $1.000, issue #1) but not
how bonuses and caps combine. Until they do, the calculation follows these
assumptions, each marked `TODO(cliente)` in code and listed in the client
decisions issue:

1. **Base points** = `floor(monto / moneda_por_punto) × puntos_por_unidad`
   of the merchant's category. A category without a rate earns 0.
2. **Bonuses**: among the active `bonificacion` rules whose conditions all
   hold, only the highest `multiplicador` applies; bonuses do not compound.
3. **Order**: base points → bonus → `topes.puntos_por_transaccion` →
   `topes.puntos_por_dia` → `topes.puntos_por_mes`.
4. **Daily and monthly caps** count the points of the earlier sample
   purchases in the same simulation, as if all of them belonged to one
   customer. The simulator does not read real customer history.

The numbers for each scenario live in the fixture, not here.

## Acceptance criteria

- [ ] Given any simulation, when it runs, then no row is inserted, updated
      or deleted in any table (explicit test).
- [ ] Given a valid draft, when it is simulated, then each line shows the
      final points and the rule and version applied, with "borrador" for
      the draft.
- [ ] Given an invalid draft, when it is simulated, then the schema
      validation errors are shown and no result is computed.
- [ ] Given the fixture scenarios, when they are simulated, then the points
      match the expected values for the current rules and for the draft.
- [ ] Given a purchase that meets the conditions of two bonuses, when it is
      simulated, then only the highest multiplier applies.
- [ ] Given caps per transaction, per day and per month, when the sample
      purchases exceed them, then each line shows the cap that limited it.
- [ ] Given a user who is not the group administrator, when they open the
      simulator, then they get 403.

## Fixtures

`tests/Fixtures/simulador-escenarios.json` (new): sample purchases, the
current rules and a draft, with the expected points and the rule applied
per purchase. The rule definitions reuse the shapes in
`tests/Fixtures/reglas-validas.json`.

## Data and design (technical decisions, pending review)

- **No migrations.** The simulator reads `reglas`, `comercios` and
  `categorias` and writes nothing.
- **`PuntosCalculator`** (`app/Reglas/`): a pure class with no database
  access. It receives the purchase (amount, merchant category, merchant,
  branch, date and time) and the rule definitions to use, and returns a
  breakdown (base, bonus, cap, final points, rule and version). E4-01
  reuses it to record real purchases; this issue builds and tests it.
- **`SimulateReglas`** action (`app/Actions/Reglas/`, invokable): loads the
  rules in force, validates the draft with `ReglaDefinitionValidator`,
  replaces the draft's rule in a copy of the set and runs
  `PuntosCalculator` over the sample with the current set and the draft
  set. It runs inside a transaction that is always rolled back, as a
  second guard on top of the explicit test.
- **Panel page** under "Niveles y reglas": pick the rule to change (its
  current version fills the draft through the E3-01 guided form, or the
  JSON editor for users with that permission), pick a merchant, enter the
  sample purchases (amount, date and time, optional branch), and see a
  table with the current and draft points side by side and the
  difference.
- **Authorization**: the same as the rule engine (`ReglaPolicy::viewAny`,
  group administrator only).

## Decisions to confirm in review

Proposed by the implementer (2026-10-13); the reviewer approves them with
this spec.

1. The points calculator is built here, as a pure class, and E4-01 reuses
   it. The issue says the simulator reuses an E3-01 evaluator, but E3-01
   deliberately does not compute points, and E4-01 comes later.
2. The draft is an unsaved definition, not a saved version: E3-01 has no
   draft state, and adding one would change its versioning.
3. The calculation assumptions above (rounding, non-compounding bonuses,
   order of caps, caps counted within the sample) go to the client as
   `TODO(cliente)` and are confirmed before E4-01 records real points.

## Hard rules from CLAUDE.md that apply

- 3.1 Points are status, not currency
- 3.8 Closed JSON schema: the draft goes through the same validator
- 3.9 Integer pesos and points, `America/Bogota` for day and month cuts

## Depends on

E3-01 (`reglas`, validator and guided form), E2-02 (merchant categories).
