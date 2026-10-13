# E3-02 · Points ledger

**Epic:** E3 · **Estimated hours:** 16 · **Risk:** high

## Goal

Record every points movement in an immutable, auditable way, with the
customer's balance always consistent.

## Out of scope

- Computing how many points a purchase earns, and the `transacciones` table
  with its idempotency (E4-01 and E9-01, hard rule 3.7).
- Cashier screen (E4-02) and any other UI for movements.
- The nightly expiry job and the decision on how expiry is cut (E3-05).
  This feature only lets the ledger record a `caducidad` movement.
- Customer levels (`nivel_*` columns, E3-03).
- Benefit redemptions: they live in `redenciones` (E5-02) and never touch
  points (hard rule 3.1).

## Business rules

1. `movimientos_puntos` is append-only: never UPDATE, never DELETE.
2. Every movement stores the applied `regla_id` and `regla_version`
   (snapshot, hard rule 3.3), except `ajuste_manual`, which has no rule
   and instead requires a reason (`motivo`) and the user who made it.
3. The balance in `clientes` is updated inside the same database
   transaction that inserts the movement.
4. Voiding a purchase inserts a negative `reversa` movement that references
   the original transaction. The original movement is never touched.
5. A reversal is only accepted within the reversal window after the
   purchase. The window length lives in a fixture and in configuration,
   never in code literals.
6. The balance recalculated from scratch must always match the
   materialized balance.
7. Movement types: `acumulacion`, `caducidad`, `ajuste_manual`, `reversa`.
   **`redencion` is removed** from the enum (see Decisions).

## Acceptance criteria

- [ ] Given an inserted movement, when someone tries to update or delete it
      through the model, then the operation fails.
- [ ] Given an accrual of N points, when it completes, then the customer's
      balance grew by exactly N inside the same transaction.
- [ ] Given a voided transaction, when the reversal is processed, then a
      negative movement referencing it exists, the original is untouched
      and the balance reflects the subtraction.
- [ ] Given a transaction older than the reversal window, when someone
      tries to reverse it, then it is rejected and nothing is inserted.
- [ ] Given a transaction that was already reversed, when someone tries to
      reverse it again, then it is rejected.
- [ ] Given a manual adjustment, when it is recorded, then it stores the
      reason and the user, and a manual adjustment without a reason is
      rejected.
- [ ] Given a customer with movements of every type, when the balance is
      recalculated from scratch, then it matches the materialized balance.
- [ ] Given a failure halfway through the operation, when it rolls back,
      then neither the movement nor the balance change remain.
- [ ] Given a movement that would leave the balance below zero, when it is
      recorded, then it is rejected — except a reversal, which is capped at
      the current balance (see Reversal).
- [ ] Given a reversal of a purchase whose points were partly consumed by a
      later negative movement, when it is processed, then it subtracts only
      the current balance, leaves the balance at zero and records the capped
      amount with its reason.
- [ ] Given two reversals of the same transaction processed at the same
      time, when both finish, then only one reversal movement exists and the
      balance was subtracted once.
- [ ] Given two accruals for the same customer processed at the same time,
      when both finish, then the balance equals the sum of both.
- [ ] Given a manual adjustment by a merchant administrator or a cashier,
      when it is recorded, then it is rejected with 403 and nothing is
      inserted.
- [ ] Given a reversal by the merchant administrator of the transaction's
      merchant, then it is accepted; by the merchant administrator of
      another merchant or by a cashier, then it is rejected with 403.
- [ ] Given a movement whose sign or fields do not match its type (an
      `acumulacion` with negative points, an `acumulacion` without
      `comercio_id` or rule snapshot, a `reversa` without `motivo`, zero
      points), when it is recorded, then it is rejected.

## Fixtures

`tests/Fixtures/movimientos-escenarios.json` — updated in this spec PR:
keys and prose in English (movement fields keep their column names), the
pending `redencion` note replaced by the decision, `reversal_window_days`,
and cases for: a reversal on the last day of the window (accepted), a
double reversal, an adjustment without a reason, a movement that would go
negative, a reversal capped at the balance, simultaneous reversals and
accruals, authorization by role and merchant, and movements whose sign or
fields do not match their type.

## Data and migrations (technical decisions, pending review)

- **`clientes`** (base table; this issue creates it): `id`, `documento`
  (unique index, never the primary key — hard rule 3.10; stored trimmed),
  `celular`, `email` (nullable, stored lowercase — 3.13), `saldo_puntos`
  (integer, default 0), timestamps. The `nivel_*` columns are added by
  E3-03; registration and login fields by E8-02.
- **`movimientos_puntos`**: `id`, `cliente_id`, `comercio_id` (required
  for `acumulacion` and `reversa`, which copies it from the original;
  null for the other types),
  `transaccion_id` (nullable), `tipo`, `puntos` (signed integer, never 0),
  `regla_id` (nullable, FK to `reglas`), `regla_version` (nullable),
  `vigencia_hasta` (nullable), `motivo` (nullable), `usuario_id`
  (nullable, FK to `users`), `created_at` set by the application.
  Indexes: `(cliente_id, created_at)` and `(transaccion_id)`.
- **`transaccion_id` has no foreign key yet**: `transacciones` belongs to
  E4-01, which adds the constraint in its own migration. Until then the
  reversal finds the original accrual by `transaccion_id` in the ledger
  and uses that movement's `created_at` as the purchase date.
- **Sign and fields by type** (checked by the actions and by the model):

  | `tipo`          | `puntos` | `regla_*`                | `comercio_id`            | `transaccion_id` | `motivo` / `usuario_id`                  |
  | --------------- | -------- | ------------------------ | ------------------------ | ---------------- | ---------------------------------------- |
  | `acumulacion`   | > 0      | required                 | required                 | required         | optional                                 |
  | `reversa`       | < 0      | copied from the original | copied from the original | required         | `motivo` required, `usuario_id` required |
  | `caducidad`     | < 0      | required                 | —                        | —                | optional                                 |
  | `ajuste_manual` | ≠ 0      | null                     | —                        | —                | both required                            |

- **Reversal**: reverses the net points of the transaction (the sum of its
  accruals), once; a second reversal is rejected. If a later negative
  movement (an adjustment or an expiry) already took part of those points,
  the reversal is capped at the current balance: it subtracts only what is
  left, the balance ends at zero, and the movement records the capped
  amount with a reason saying so. A voided purchase never leaves points
  behind and the balance never goes negative.
- **`vigencia_hasta`** on an accrual = movement date + the rule's
  `vigencia_puntos_meses` (per-point validity, as CLAUDE.md §9 is drafted).
  E3-05 decides whether the cut is daily or monthly; it does not change
  what is stored here.
- **Concurrency**: every movement locks the customer row
  (`lockForUpdate`) before reading the balance, so movements for the same
  customer are serialized. That lock is also what makes the "already
  reversed" check race-free; a partial unique index is not used because
  it is not portable (3.13).
- **Balance**: updated with an atomic increment inside the transaction,
  never read-modify-write without the lock. The balance cannot go below
  zero: any movement that would take it there is rejected, except a
  reversal, which is capped (see Reversal).
- **Recalculation**: an action returns the sum of `puntos` for a customer
  and whether it matches `saldo_puntos`. The nightly consistency check
  that uses it belongs to E3-05.
- **Actions** (`app/Actions/Puntos/`, invokable): `RecordAcumulacion`,
  `ReverseTransaccion`, `RecordAjusteManual`, `RecordCaducidad`,
  `RecalculateSaldo`. Nothing else writes to `movimientos_puntos`.
- **Authorization** in the actions: `ajuste_manual` only for the group
  administrator. `reversa` for the group administrator or the merchant
  administrator of the transaction's merchant. `acumulacion` and
  `caducidad` are system operations, authorized by their callers
  (E4-01, E3-05).
- **Reversal window** in `config/puntos.php` (`reversal_window_days`): the
  application needs it at runtime and production does not ship `tests/`, so
  a test asserts it equals the fixture's value. The window counts calendar
  days in `America/Bogota`, not instants: a reversal is allowed while
  today's date is on or before the purchase date plus
  `reversal_window_days`. A purchase from 30 days ago is accepted at any
  hour; one from 31 days ago is rejected.

## Decisions to confirm in review

Proposed by the implementer (2026-10-12); the reviewer approves them with
this spec.

1. `redencion` is removed from the movement types (hard rule 3.1: a
   benefit never costs points, and redemptions have their own table). This
   closes the open question in issue #8 and in CLAUDE.md §4.
2. `transacciones` stays in E4-01; E3-02 keeps `transaccion_id` without a
   foreign key until then.
3. The balance can never go below zero: movements that would do so are
   rejected, and a reversal is capped at the current balance instead.
4. Manual adjustments: group administrator only. Reversals: group
   administrator or the merchant administrator of the transaction's
   merchant.

## Hard rules from CLAUDE.md that apply

- 3.1 Points are status, not currency
- 3.3 Rule snapshot
- 3.4 Immutable ledger
- 3.9 Integer pesos, `America/Bogota`
- 3.10 `documento` is a unique index, never the primary key
- 3.13 Engine-agnostic code

## Depends on

E3-01 (`reglas` table for the snapshot), E2-01 (roles), E2-02
(`comercios`).
