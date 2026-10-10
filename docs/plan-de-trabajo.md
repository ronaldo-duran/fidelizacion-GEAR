# Plan de trabajo interno — 6 semanas

Plan de ejecución del equipo. Cada fila es un issue en GitHub con el ID
en el título (`[E3-01] …`), la etiqueta `semana:N` y el milestone del hito.

- **N** = Neyder: implementa todo lo técnico.
- **R** = Ronaldo: cliente, cuentas a nombre del cliente, revisión de PRs,
  aceptación de hitos y capacitación. Los issues `riesgo:alto` llevan su
  aprobación de la spec y del PR.
- Fechas suponiendo inicio el lunes 12 de octubre de 2026. Si el inicio
  se mueve, se corren todas por igual.

## Hitos

| Hito   | Semana | Fecha      | Qué se entrega                                                                                                                             |
| ------ | ------ | ---------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| Hito 2 | 2      | vie 23 oct | Motor de reglas configurable y panel administrativo funcionando (en staging)                                                               |
| Hito 3 | 4      | vie 6 nov  | Operación diaria (compras, beneficios, redención) + membresías con pago en línea, motor de notificaciones e integración de email marketing |
| Hito 4 | 6      | vie 20 nov | PWA del cliente, API pública, plataforma en producción, documentación y capacitación                                                       |

Después del hito 4: dos semanas de acompañamiento (corrección de
defectos sobre uso real) y 90 días de garantía.

## Semana a semana

### Semana 0 — 8 y 9 oct

| ID    | Tarea                                                            | Quién | Horas |
| ----- | ---------------------------------------------------------------- | ----- | ----- |
| E1-01 | Fundación técnica (Laravel 13, Filament 5, CI, tokens de diseño) | R     | 16    |

### Semana 1 — 12 al 16 oct

| ID    | Tarea                                                                          | Quién | Horas |
| ----- | ------------------------------------------------------------------------------ | ----- | ----- |
| —     | Taller de definición de reglas con el cliente + envío de decisiones pendientes | R     | —     |
| E3-01 | Motor de reglas configurable                                                   | N     | 30    |
| E3-02 | Libro de puntos (arranque)                                                     | N     | 16    |
| E2-01 | Cuentas, roles, permisos y auditoría                                           | N     | 18    |
| E2-02 | Comercios, sedes y categorías                                                  | N     | 14    |
| E1-02 | Servidor y despliegue automatizado (staging y producción)                      | N     | 8     |

### Semana 2 — 19 al 23 oct · **Hito 2**

| ID     | Tarea                                                                    | Quién | Horas |
| ------ | ------------------------------------------------------------------------ | ----- | ----- |
| E3-02  | Libro de puntos (cierre)                                                 | N     | —     |
| E3-03  | Nivel efectivo del cliente                                               | N     | 10    |
| E3-04  | Simulador de reglas                                                      | N     | 14    |
| E10-01 | Panel administrativo: tema, login y tablero del grupo                    | N     | 12    |
| E8-01  | Base de la interfaz: PWA instalable y componentes primitivos (`/_ui`)    | N     | 10    |
| —      | Obsequio: actualización de grupoaponterivera.com (si llegó el contenido) | N     | 16    |

### Semana 3 — 26 al 30 oct · primera demostración funcional

| ID    | Tarea                                      | Quién | Horas |
| ----- | ------------------------------------------ | ----- | ----- |
| E4-01 | Registro de compras: acumulación y ascenso | N     | 8     |
| E4-04 | Código QR firmado y rotativo               | N     | 10    |
| E3-05 | Procesos automáticos nocturnos             | N     | 12    |
| E4-02 | Pantalla del cajero: registrar compra      | N     | 10    |
| E8-02 | Registro y acceso del cliente              | N     | 8     |
| E5-01 | Catálogo de beneficios y promociones       | N     | 18    |

### Semana 4 — 2 al 6 nov · **Hito 3**

| ID    | Tarea                                                        | Quién | Horas |
| ----- | ------------------------------------------------------------ | ----- | ----- |
| E6-01 | Membresías y pagos en línea                                  | N     | 26    |
| E5-02 | Redención de beneficios y pantallas de validación del cajero | N     | 10    |
| E7-02 | Entregabilidad del correo                                    | N     | 8     |
| E7-01 | Motor de notificaciones                                      | N     | 18    |
| E7-03 | Integración de email marketing con Brevo                     | N     | 26    |

### Semana 5 — 9 al 13 nov

| ID    | Tarea                             | Quién | Horas |
| ----- | --------------------------------- | ----- | ----- |
| E9-01 | API pública para comercios        | N     | 36    |
| E8-03 | PWA: inicio y niveles             | N     | 12    |
| E8-04 | PWA: catálogo y uso del beneficio | N     | 12    |
| E8-05 | PWA: historial y perfil           | N     | 8     |
| E4-03 | Carga masiva por archivo          | N     | 16    |

### Semana 6 — 16 al 20 nov · **Hito 4**

| ID     | Tarea                                                         | Quién | Horas |
| ------ | ------------------------------------------------------------- | ----- | ----- |
| E1-04  | Pruebas en dispositivos reales y QA final                     | N     | 16    |
| E10-03 | Seguridad y habeas data                                       | N     | 12    |
| E1-03  | Producción: respaldos verificados, monitoreo y salida en vivo | N     | 6     |
| E10-05 | Capacitación (dos sesiones en vivo)                           | R     | 6     |
| E10-02 | Reportes exportables                                          | N     | 10    |
| E10-04 | Manual de operación y videos tutoriales                       | N     | 14    |
| E9-02  | Documentación interactiva de la API                           | N     | 10    |

Total: 460 horas del acuerdo (348 núcleo + 112 complementarias), más
16 del obsequio.

## Carga de Neyder

Horas estimadas por semana (sin contar lo que hace Ronaldo): S1 86 ·
S2 62 · S3 66 · S4 88 · S5 84 · S6 68. Es mucho para una sola persona:
la apuesta es trabajar con varias sesiones de Claude Code en paralelo sobre
issues independientes (por ejemplo, E2-02 y E1-02 mientras avanza E3-01).
Si una semana se atrasa, lo primero que se corre es el obsequio y luego los
complementarios que no son condición de hito (E3-04, E4-03, E9-02).

## Ruta crítica

1. **Decisiones del cliente** (issue "Decisiones pendientes del cliente").
   Sin tasas ni umbrales el motor funciona, pero los fixtures quedan con
   supuestos. Pedirlas por escrito en la semana 1; la cláusula 3 permite
   seguir con el supuesto documentado a los 3 días hábiles.
2. **E3-01 → E3-02 → E3-03 → E4-01**: todo lo que suma puntos depende de
   esta cadena. Va en orden y no se paraleliza; el resto del trabajo de la
   semana se acomoda alrededor.
3. **E8-01** (primitivas de interfaz) antes de cualquier pantalla del
   cajero o de la PWA.
4. **Pasarela de pagos** elegida y cuenta creada a nombre del cliente
   antes de la semana 4 (E6-01).
5. **Acceso al DNS del dominio de correo** antes de la semana 4 (E7-02).
6. **Servidor** (cuenta de Hetzner a nombre del cliente) antes de la
   semana 2 para mostrar el hito 2 en staging.

## Cómo trabajar con Claude Code

- Abrir sesión desde la rama `main` actualizada. El hook
  `.claude/hooks/session-start.sh` deja PHP, Node, PostgreSQL y Redis
  listos en sesiones en la nube.
- Pedido típico: _"Toma el issue #N. Lee CLAUDE.md, la spec y las
  dependencias. Si no hay spec, escríbela primero y para."_
- Un issue = una rama `f/<ID>-<slug>` = uno o varios PRs, idealmente de
  menos de 400 líneas de producción, con `Closes #N` en el último.
- Antes de pedir revisión: Pint, PHPStan y Pest en verde. El PR le pide
  revisión al otro automáticamente (`.github/CODEOWNERS`): Ronaldo revisa
  lo de Neyder y Neyder lo de Ronaldo.
- Para que Claude revise un PR: comentar `/revisar` con el CI en verde.
  `@claude <pedido>` le pide cualquier otra cosa.
