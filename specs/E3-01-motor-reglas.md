# E3-01 · Configurable rule engine

**Epic:** E3 · **Estimated hours:** 30 · **Risk:** high

## Goal

The group administrator defines and changes the accrual rules from the
panel, without a developer, and every change is versioned with its
effective date.

## Out of scope

Computing points for a specific purchase (E3-02 and E4-01). Simulator
(E3-04). Evaluating demotion notices and the expiry cut-off (E4-02): this
feature only stores the values.

## Business rules

1. A rule is stored as JSONB validated against JSON Schema. If it does not
   validate, it is not saved.
2. Only whitelisted keys and operators are accepted. No free-form
   expressions and no dynamic evaluation of strings.
3. Saving creates `version + 1` with `vigente_desde`; the previous version
   gets `vigente_hasta`. Never an UPDATE on the content.
4. Two versions of the same rule cannot overlap in validity.
5. Only the group administrator role can create or change rules. Every
   operation is audited with user and date.
6. The advanced JSON editor sits behind a separate permission; the guided
   form covers the normal case.
7. Levels rule (confirmed by the client on 2026-10-10, issue #7): no
   protection (`proteccion_meses: 0`) and demotion notices 30, 7 and 1 day
   before (`avisos_dias_antes: [30, 7, 1]`). The list holds unique integers,
   greater than or equal to 0 and strictly decreasing. The notice is only
   sent when expiry drops the customer a level; E4-02 evaluates that.

## Acceptance criteria

- [ ] Given a JSON with a key outside the whitelist, when it is saved,
      then it is rejected with the validation error and nothing is
      persisted.
- [ ] Given a rule at version 3, when a change is saved, then a version 4
      exists with `vigente_desde` = today and version 3 has
      `vigente_hasta` = today, with its original `definicion` untouched.
- [ ] Given an attempt to create a version whose validity overlaps an
      existing one, when it is saved, then it is rejected.
- [ ] Given a user with the merchant administrator role, when they try to
      edit a rule, then they get 403.
- [ ] Given any successful save, when the audit log is queried, then the
      user, the date and the diff of the definition appear.
- [ ] `defNiveles` accepts `avisos_dias_antes` as an array of unique,
      decreasing integers, and `proteccion_meses: 0` validates.
- [ ] `aviso_dias_antes` as an integer (old shape) and a list of repeated
      or non-decreasing notices are rejected.

## Fixtures

`tests/Fixtures/reglas-validas.json`
`tests/Fixtures/reglas-invalidas.json`
`tests/Fixtures/schema-regla.json`

## Data and migrations (technical decisions, pending review)

- `reglas` table: `id`, `codigo`, `tipo`, `version`, `vigente_desde`,
  `vigente_hasta` (nullable), `activa`, `definicion` (jsonb), `creada_por`,
  `created_at`. Each row is a version; the `regla_id` and `regla_version`
  of point movements (3.3) point to that row.
- `codigo` groups the versions of one rule (lowercase slug, 3.13). There
  can be several rules of the same type (e.g. several bonuses). The type
  does not change between versions.
- **Unique constraint on `(codigo, version)`**: two concurrent saves cannot
  create the same version. In addition, the new version is computed from
  the latest row read with a lock (`lockForUpdate`), and the save is
  rejected if the administrator edited a version that is no longer the
  latest.
- Validity is an application date-time (`America/Bogota`) in a half-open
  interval `[vigente_desde, vigente_hasta)`. The new version starts at
  `now()` (or at a future date) and the previous one closes at that same
  instant. Retroactive validity is rejected (3.3), and so is a version
  that starts before the current one or before an already scheduled one:
  that is the overlap that gets rejected.
- Deactivating a rule = a new version with `activa = false`.
- The schema the application uses lives in `resources/schemas/regla.json`
  (production code does not read `tests/`). A test requires it to be
  identical to `tests/Fixtures/schema-regla.json`.
- Two-layer validation: JSON Schema draft 2020-12 with `opis/json-schema`
  (Apache-2.0, already a Laravel dev dependency) and checks the schema
  cannot express (increasing thresholds with Bronce at 0 and each level
  once; decreasing notices; `en` requires an array and every other operator
  a single value; `mayor`/`menor` require an integer; categories not
  repeated, normalized and existing).
- JSON editor permission: boolean column `edita_reglas_json` on
  `users`, following E2-01 (role as an enum plus scope attributes, no
  permissions package). It only takes effect for the group administrator.
- Audit: every save writes one row in `auditorias` with the author, the
  date and the previous and new definition.
- The model throws on any content change or delete; it only allows closing
  `vigente_hasta`, once.

## Hard rules from CLAUDE.md that apply

- 3.2 Versioning, never in-place edits
- 3.8 Closed JSON schema
- 3.13 Engine-agnostic code (PostgreSQL and MySQL in CI)

## Notes

This feature does not compute points. It only defines, validates, versions
and stores.

## Depends on

E2-01 (group administrator role and audit log), E2-02 (category slugs).
