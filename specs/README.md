# Especificaciones

Una spec por feature. Sin spec no hay código.

**Nomenclatura:** `E<épica>-<número>-<slug>.md` → `E3-01-motor-reglas.md`

**Regla de oro:** las reglas de negocio con números no van en la prosa de
la spec. Van en `tests/Fixtures/`. Cuando el cliente cambie un tope, se
toca el fixture y el CI dice qué se rompió. Si los números están regados
por la prosa, cada cambio de regla se vuelve una cacería.

Una spec se revisa como PR antes de que exista una línea de código.
