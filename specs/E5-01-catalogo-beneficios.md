# E5-01 · Catálogo de beneficios

**Épica:** E5 · **Horas estimadas:** 18 · **Riesgo:** medio

## Objetivo

El administrador define los beneficios de cada comercio, con su valor por
nivel, vigencia, condiciones de uso, límite por cliente y cupo global, y el
sistema responde qué beneficios tiene un cliente hoy y en qué estado:
disponible, bloqueado, no disponible hoy, ya usado o sin cupos.

## Fuera de alcance

- Usar el beneficio: el QR del cliente, la validación del cajero, registrar
  la redención con su valor otorgado y descontar el cupo global de forma
  atómica (E5-02, reglas duras 3.11 y 3.12). Aquí se define el estado que
  la redención consulta.
- Las pantallas del cliente que muestran el catálogo y el uso (E8-03 "Para
  usar hoy", E8-04 catálogo 1n y uso 1o/1p).
- Promociones de puntos (bonos y multiplicadores): son reglas del motor
  (E3-06), no beneficios.
- Producto gratis como tipo de beneficio (§8: exige inventario). Un 100 % de
  descuento sigue siendo un descuento.
- Las columnas de beneficios del tablero (E10-01) se llenan con E5-02.

## Reglas de negocio

1. **Los beneficios no cuestan puntos** (regla dura 3.1): usarlos no
   descuenta nada del saldo ni del nivel. El catálogo nunca muestra un
   "costo en puntos".
2. **Un beneficio, un comercio y un valor por nivel**: el tipo es
   `porcentaje` (entero de 0 a 100) o `monto_fijo` (pesos enteros, regla
   dura 3.9), con un valor para cada nivel que lo recibe. El **nivel
   mínimo** es el más bajo que tiene valor; un nivel sin valor no lo recibe.
   El catálogo inicial vive en `tests/Fixtures/beneficios-catalogo.json`.
3. **Condiciones de uso cerradas** (regla dura 3.8): solo las de una lista
   blanca propia de los beneficios, validadas contra su esquema al guardar:
   `es_cumpleanos`, `dia_semana` (lista de días) y `fin_de_semana` (viernes,
   sábado y domingo). Nada de expresiones libres. El texto de condiciones que
   lee el cliente es aparte y no se evalúa.
4. **Cumpleaños**: el día del mes y el mes de `fecha_nacimiento`, en
   `America/Bogota`; quien nació un 29 de febrero lo usa el 28 en los años no
   bisiestos. Sin fecha de nacimiento, el beneficio no está disponible.
5. **Límite por cliente**: una cantidad por ventana `mes`, `anio` o `vida`
   (de por vida, por cuenta), o sin límite. Las ventanas de mes y año son
   calendario en Bogotá; cuando se agota, el beneficio vuelve el primer día de
   la ventana siguiente ("Vuelve el 1 de noviembre"). Uno de por vida no
   vuelve.
6. **Cupo global**: un número opcional de redenciones entre todos los
   clientes. Al agotarse, el beneficio queda "sin cupos" para todos. Lo
   descuenta la redención (E5-02), sin condición de carrera.
7. **Vigencia y activo**: fuera de su vigencia o inactivo, el beneficio no
   aparece. Un beneficio con redenciones no se borra: se desactiva.
8. **Editar cambia lo que viene**: cambiar el valor o las condiciones afecta
   las próximas redenciones; cada redención guarda su valor otorgado (regla
   dura 3.11), así que el historial no cambia.
9. **Estado de un beneficio para un cliente en una fecha**, en este orden:
   - **bloqueado**: el nivel efectivo del cliente está por debajo del nivel
     mínimo; trae ese nivel y los puntos que faltan para él (umbral de la
     regla `niveles` vigente menos el saldo).
   - **sin cupos**: el cupo global se agotó.
   - **usado**: el cliente agotó su límite en la ventana; trae la fecha en
     que vuelve (o ninguna, si es de por vida).
   - **no disponible hoy**: hoy no se cumple una condición; trae la próxima
     fecha en que se cumple (el próximo martes, el próximo cumpleaños) o el
     motivo (sin fecha de nacimiento).
   - **disponible**: con el valor de su nivel y, si un nivel más alto da
     más, ese nivel, su valor y los puntos que faltan ("Con Oro, 40 %").
10. **Soporte**: un beneficio puede exigir un soporte de la operación (Laura
    Rivera); se marca en el catálogo y lo pide el cajero al redimir (E5-02).
11. **Quién administra**: el administrador del grupo, todos; el de un
    comercio, solo los de su comercio. El cajero no administra beneficios.

## Criterios de aceptación

- [ ] El catálogo inicial queda sembrado desde el fixture: los cinco
      beneficios con sus valores por nivel, condiciones, límites, cupos y
      soporte.
- [ ] Un beneficio con condiciones fuera de la lista blanca o con una
      expresión libre no se guarda.
- [ ] Dado un cliente Plata y un beneficio cuyo nivel mínimo es Oro,
      entonces aparece bloqueado con los puntos que le faltan.
- [ ] Dado un beneficio de martes, el martes está disponible y otro día
      aparece no disponible hoy con la fecha del próximo martes.
- [ ] Dado el beneficio de cumpleaños, solo está disponible el día del
      cumpleaños del cliente (el 28 de febrero para quien nació el 29 en un
      año no bisiesto), y no lo está sin fecha de nacimiento.
- [ ] Dado un beneficio usado el máximo en su ventana, entonces aparece usado
      con la fecha en que vuelve (mes, año) o sin fecha (de por vida).
- [ ] Dado un beneficio con el cupo global agotado, entonces aparece sin
      cupos para todos.
- [ ] Dado un beneficio fuera de vigencia o inactivo, entonces no aparece.
- [ ] Dado un beneficio disponible cuyo nivel siguiente da más, entonces trae
      ese nivel, su valor y los puntos que faltan.
- [ ] Un administrador de comercio solo ve, crea y edita los beneficios de
      su comercio; un beneficio con redenciones no se puede borrar.
- [ ] La consulta de un cliente no hace una consulta por beneficio (sin
      N+1).

## Fixtures

- `tests/Fixtures/beneficios-catalogo.json` (ya en `main`): el catálogo
  inicial confirmado por el cliente.
- `tests/Fixtures/beneficios-escenarios.json` (nuevo, en inglés): fechas,
  niveles, saldos, cumpleaños y redenciones previas, con el estado, el valor,
  la fecha en que vuelve y el nivel siguiente esperados.

## Datos y diseño (decisiones técnicas, pendientes de revisión)

- **`beneficios`** (tabla nueva): `id`, `comercio_id`, `nombre`,
  `condiciones_texto`, `tipo` (`porcentaje` | `monto_fijo`),
  `valores_por_nivel` (json: nivel → valor entero), `condiciones` (json
  validado contra `resources/schemas/beneficio-condiciones.json`),
  `max_por_cliente_ventana` (`mes` | `anio` | `vida`, nulo = sin límite),
  `max_por_cliente_cantidad`, `cupo_global` (nulo = sin cupo),
  `requiere_soporte`, `vigente_desde`, `vigente_hasta` (nulo = sin fin),
  `activo`, timestamps. Esto reemplaza en CLAUDE.md §4 las columnas `nivel`,
  `valor`, `cupo` y `max_por_cliente_mes`.
- **`redenciones`** (tabla nueva, append-only): `id`, `cliente_id`,
  `beneficio_id`, `comercio_id`, `sede_id`, `valor_otorgado_cop`,
  `validado_por`, `created_at`. Aquí solo se crea y se lee (usos por ventana
  y cupo); la escribe la Action de E5-02.
- **Consulta** (`app/Beneficios/BeneficiosParaCliente`): los beneficios
  vigentes y activos con su estado para un cliente en una fecha. Una
  consulta de beneficios y una de redenciones agrupadas (por beneficio para
  el cupo, por beneficio y ventana para el cliente), sin una por beneficio.
  Query builder, fechas puestas por la aplicación (regla dura 3.13).
- **Validación de condiciones**: el mismo patrón de JSON Schema del motor de
  reglas (E3-01), con su propio esquema cerrado.
- **Recurso de Filament "Beneficios"** en el grupo "Beneficios" (E10-01):
  comercio, nombre, texto de condiciones, tipo, un valor por nivel,
  condiciones (cumpleaños, días de la semana, fin de semana), límite por
  cliente (ventana y cantidad), cupo global, soporte, vigencia y activo. Sin
  lógica en el recurso: guardar pasa por una Action que valida.
- **Seeder**: el catálogo de `beneficios-catalogo.json` en `DatabaseSeeder`.

## Decisiones a confirmar en la revisión

Propuestas por el implementador (2026-10-26); el revisor las aprueba con
esta spec.

1. **Un beneficio con un valor por nivel** (como el catálogo del cliente) en
   vez de una fila por nivel; el nivel mínimo es el más bajo con valor.
   Actualiza el modelo de datos de CLAUDE.md §4.
2. **Lista blanca propia de los beneficios** (`es_cumpleanos`, `dia_semana`,
   `fin_de_semana`) en vez de extender la del motor de reglas: las
   condiciones de un beneficio deciden si se puede usar hoy, no cuántos
   puntos da una compra.
3. **Ventanas calendario** en Bogotá para el límite por mes y por año ("Vuelve
   el 1 de noviembre"), no móviles de 30 o 365 días.
4. **La tabla `redenciones` se crea aquí** (solo esquema y lectura), para que
   el estado "usado" y "sin cupos" se pueda probar ya; E5-02 agrega la
   escritura.
5. **"Fin de semana" es viernes, sábado y domingo** (el issue lo pide así para
   Hubble).
6. **Cupos sin número todavía**: Laura Rivera (¿tiene cupo?) y Hubble ("100
   por temporada": ¿cuál es la temporada?) quedan sin cupo global con
   `TODO(cliente)` hasta que el cliente fije el valor. Un cupo por temporada
   necesitaría fechas de temporada: hoy el cupo es uno solo para toda la
   vigencia del beneficio.
7. El estado **"disponible" sugiere el nivel siguiente** cuando da más, para
   que E8-04 muestre "Con Oro, 40 %" (en el catálogo inicial todos los
   beneficios dan algo desde Bronce, así que ninguno sale bloqueado).

## Reglas duras de CLAUDE.md que aplican

- 3.1 Los beneficios no cuestan puntos.
- 3.8 Condiciones con esquema cerrado, nada de expresiones libres.
- 3.9 Montos en pesos enteros; fechas en Bogotá.
- 3.11 Cada redención guarda su valor otorgado (la escribe E5-02).
- 3.13 Código agnóstico del motor: el JSON se lee con la sintaxis de
  Laravel, sin operadores nativos.

## Notas de interfaz

- Solo el recurso del panel (Filament, solo colores y fuentes de la marca).
  Las pantallas del cliente son E8-03 y E8-04.
- Textos del diseño que el estado debe permitir: "Puedes usarlos", "Se abren
  al subir de nivel", "Te faltan N pts", "Ya lo usaste este mes", "Vuelve el
  1 de octubre".

## Depende de

E2-02 (comercios) y E3-03 (nivel efectivo), en `main`; E10-01 (grupo
"Beneficios" del panel), en `main`.
