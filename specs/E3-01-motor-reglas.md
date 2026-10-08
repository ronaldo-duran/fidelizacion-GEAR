# E3-01 · Motor de reglas configurable

**Épica:** E3 · **Horas estimadas:** 30 · **Riesgo:** alto

## Objetivo
El administrador del grupo define y modifica las reglas de acumulación
desde el panel, sin intervención de un desarrollador, y cada cambio queda
versionado con su fecha de vigencia.

## Fuera de alcance
Cálculo de puntos en una compra concreta (E3-02 y E4-01). Simulador (E3-04).

## Reglas de negocio
1. Una regla se guarda como JSONB validado contra JSON Schema. Si no
   valida, no guarda.
2. Solo se aceptan claves y operadores de la lista blanca. Ninguna
   expresión libre ni evaluación dinámica de strings.
3. Guardar crea `version + 1` con `vigente_desde`; la versión anterior
   recibe `vigente_hasta`. Nunca un UPDATE sobre el contenido.
4. Dos versiones de la misma regla no pueden solaparse en vigencia.
5. Solo el rol administrador del grupo puede crear o modificar reglas.
   Toda operación queda en auditoría con usuario y fecha.
6. El editor JSON avanzado está detrás de un permiso aparte; el
   formulario guiado cubre el caso normal.

## Criterios de aceptación
- [ ] Dado un JSON con una clave fuera de la lista blanca, cuando se
      guarda, entonces se rechaza con el error de validación y no se
      persiste nada.
- [ ] Dada una regla en versión 3, cuando se guarda un cambio, entonces
      existe una versión 4 con `vigente_desde` = hoy y la 3 queda con
      `vigente_hasta` = hoy, con su `definicion` original intacta.
- [ ] Dado un intento de crear una versión cuya vigencia se solapa con
      otra existente, cuando se guarda, entonces se rechaza.
- [ ] Dado un usuario con rol administrador de comercio, cuando intenta
      editar una regla, entonces recibe 403.
- [ ] Dado cualquier guardado exitoso, cuando se consulta la auditoría,
      entonces aparece el usuario, la fecha y el diff de la definición.

## Fixtures
`tests/Fixtures/reglas-validas.json`
`tests/Fixtures/reglas-invalidas.json`
`tests/Fixtures/schema-regla.json`

## Reglas duras de CLAUDE.md que aplican
- 3.2 Versionado, nunca edición en sitio
- 3.8 Esquema JSON cerrado

## Notas
Esta feature no calcula puntos. Solo define, valida, versiona y almacena.
