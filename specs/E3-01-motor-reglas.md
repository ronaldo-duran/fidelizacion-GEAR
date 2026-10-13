# E3-01 · Motor de reglas configurable

**Épica:** E3 · **Horas estimadas:** 30 · **Riesgo:** alto

## Objetivo

El administrador del grupo define y modifica las reglas de acumulación
desde el panel, sin intervención de un desarrollador, y cada cambio queda
versionado con su fecha de vigencia.

## Fuera de alcance

Cálculo de puntos en una compra concreta (E3-02 y E4-01). Simulador (E3-04).
Evaluación de los avisos de descenso y del corte de caducidad (E4-02): aquí
solo se guardan los valores.

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
7. Regla de niveles (confirmado por el cliente el 2026-10-10, issue #7):
   sin protección (`proteccion_meses: 0`) y avisos de descenso a 30, 7 y
   1 día (`avisos_dias_antes: [30, 7, 1]`). La lista es de enteros
   únicos, mayores o iguales a 0 y estrictamente decrecientes. El aviso
   solo se envía cuando la caducidad baja de nivel al cliente; eso lo
   evalúa E4-02.

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
- [ ] `defNiveles` acepta `avisos_dias_antes` como arreglo de enteros
      únicos y decrecientes, y `proteccion_meses: 0` valida.
- [ ] Se rechaza `aviso_dias_antes` como entero (forma vieja) y una lista
      de avisos repetidos o no decrecientes.

## Fixtures

`tests/Fixtures/reglas-validas.json`
`tests/Fixtures/reglas-invalidas.json`
`tests/Fixtures/schema-regla.json`

## Datos y migraciones (decisiones técnicas, pendientes de revisión)

- Tabla `reglas`: `id`, `codigo`, `tipo`, `version`, `vigente_desde`,
  `vigente_hasta` (nullable), `activa`, `definicion` (jsonb),
  `creada_por`, `created_at`. Cada fila es una versión; `regla_id` y
  `regla_version` de los movimientos (3.3) apuntan a esa fila.
- `codigo` agrupa las versiones de una misma regla (slug en minúsculas,
  3.13). Puede haber varias reglas del mismo tipo (p. ej. varias
  bonificaciones). El tipo no cambia entre versiones.
- **Restricción única `(codigo, version)`**: dos guardados simultáneos no
  pueden crear la misma versión. Además, la nueva versión se calcula con
  la última fila bloqueada (`lockForUpdate`) y se rechaza si el
  administrador editó una versión que ya no es la última.
- Vigencia como fecha y hora de la aplicación (`America/Bogota`) en un
  intervalo semiabierto `[vigente_desde, vigente_hasta)`. La versión
  nueva arranca en `now()` (o en una fecha futura) y la anterior se
  cierra en ese mismo instante. No se aceptan vigencias retroactivas (3.3)
  ni una versión que arranque antes de la vigente o de una ya programada:
  eso es el solape que se rechaza.
- Desactivar una regla = versión nueva con `activa = false`.
- El esquema que usa la aplicación vive en `resources/schemas/regla.json`
  (el código de producción no lee `tests/`). Una prueba exige que sea
  idéntico a `tests/Fixtures/schema-regla.json`.
- Validación en dos capas: JSON Schema draft 2020-12 con
  `opis/json-schema` (Apache-2.0, ya es dependencia de desarrollo de
  Laravel) y chequeos que el esquema no expresa (umbrales crecientes con
  Bronce en 0 y cada nivel una vez; avisos decrecientes; `en` exige
  arreglo y los demás operadores un valor simple; `mayor`/`menor` exigen
  entero; categorías sin repetir, normalizadas y existentes).
- Permiso del editor JSON: columna booleana `edita_reglas_json` en
  `users`, en la línea de E2-01 (rol en enum + atributos de alcance, sin
  paquete de permisos). Solo tiene efecto para el administrador del grupo.
  No es asignable en masa: solo lo escriben de forma explícita las páginas
  de usuarios, a las que únicamente entra el administrador del grupo.
- Auditoría: cada guardado escribe un registro en `auditorias` con el
  autor, la fecha y la definición anterior y la nueva.
- El modelo lanza una excepción ante cualquier cambio de contenido o
  borrado; solo admite cerrar `vigente_hasta` una vez. Es una barrera de
  Eloquent: el query builder o el SQL directo la saltan.

## Reglas duras de CLAUDE.md que aplican

- 3.2 Versionado, nunca edición en sitio
- 3.8 Esquema JSON cerrado
- 3.13 Código agnóstico del motor (PostgreSQL y MySQL en el CI)

## Notas

Esta feature no calcula puntos. Solo define, valida, versiona y almacena.

## Depende de

E2-01 (rol administrador del grupo y auditoría), E2-02 (slugs de
categoría).
