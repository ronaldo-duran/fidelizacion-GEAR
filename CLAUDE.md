# Plataforma de Fidelización — Grupo Aponte Rivera

Lee este archivo completo antes de escribir código. Las secciones marcadas
como **REGLA DURA** no se negocian: romperlas corrompe datos financieros o
genera disputas con clientes reales del programa.

---

## 1. Qué es esto

Programa de fidelización que conecta 15 comercios de un mismo grupo
empresarial (restaurantes, heladerías, cervecerías, un colegio y una
inmobiliaria). Los clientes acumulan puntos comprando en cualquiera de
ellos y suben por cuatro niveles: **Bronce, Plata, Oro, Diamante**.
Nombre de trabajo del programa: **Club Aponte Rivera** (pendiente de
confirmar por el cliente).

Tres formas de llegar a un nivel:

1. **Por consumo** — acumulando puntos
2. **Por membresía** — pagando una suscripción
3. **Por evento** — comprar un inmueble otorga Diamante por un año

Tres superficies: **PWA del cliente**, **cajero** (celular del comercio) y
**panel administrativo** (Filament).

Alcance total: 460 horas en 4 hitos de pago. Cronograma de trabajo y
reparto por semana: `docs/plan-de-trabajo.md`. Las tareas viven como
issues en GitHub (ver sección 5).

---

## 2. Stack

| Capa                     | Tecnología                                                                                         |
| ------------------------ | -------------------------------------------------------------------------------------------------- |
| Backend y API            | Laravel 13 (PHP 8.3)                                                                               |
| Panel administrativo     | Filament 5                                                                                         |
| PWA del cliente y cajero | Blade + Livewire 4 + Alpine, Tailwind v4 (build propio, separado del de Filament)                  |
| Base de datos            | PostgreSQL 16 (producción). El código también debe correr en SQL Server 2022                       |
| Colas y caché            | Redis + Horizon (Horizon se instala con el despliegue, E1-02)                                      |
| Pruebas                  | Pest 4 sobre PostgreSQL y SQL Server (nunca SQLite); E2E con Playwright                            |
| Calidad PHP              | Pint (estricto), PHPCS (PSR-12), PHPMD (todas las reglas), PHPStan nivel máximo + reglas estrictas |
| Calidad frontend         | ESLint + Prettier                                                                                  |
| Commits y versión        | Conventional Commits (commitlint + Husky); versión semver en `package.json`                        |
| Fuentes                  | Fontsource (auto-alojadas, sin CDN)                                                                |
| Infraestructura          | Hetzner + Coolify + Docker                                                                         |

**No instalar plugins de pago de Filament.** El código es propiedad del
cliente y una licencia por dominio complica la transferencia. Si algo
falta, se escribe a mano. Paquetes gratuitos (MIT) sí, pero se pregunta
antes de agregar dependencias nuevas.

**Por qué Livewire para la PWA:** es el mismo stack de Filament (un solo
lenguaje, un solo modelo mental para los dos desarrolladores y para
Claude), la app depende del servidor de todas formas (QR firmado, puntos
en vivo) y evita mantener una API interna solo para el frontend. La PWA
agrega manifest y service worker para instalación y pantalla sin conexión.

---

## 3. REGLAS DURAS

### 3.1 Los puntos son estatus, no moneda

Los puntos sirven **únicamente** para determinar el nivel. No se canjean,
no se gastan, no son un saldo. Los beneficios son fijos por nivel.

Nunca introducir: saldo canjeable, "costo en puntos" de un beneficio,
transferencia de puntos entre clientes, ni lenguaje de "canjear puntos"
en la interfaz.

### 3.2 Las reglas se versionan, nunca se editan en sitio

Guardar una regla crea `version + 1` con `vigente_desde`. La anterior
recibe `vigente_hasta`. **Jamás un UPDATE sobre el contenido de una regla
existente.**

### 3.3 Snapshot de regla en cada movimiento

Todo movimiento de puntos guarda `regla_id` y `regla_version`. Cambiar una
regla afecta lo que viene, nunca lo que ya pasó. Sin esto no se puede
defender un reclamo de un cliente.

### 3.4 Libro de movimientos inmutable

`movimientos_puntos` es append-only. Nunca UPDATE, nunca DELETE sobre una
fila existente.

- Anular una compra = insertar un movimiento negativo de tipo `reversa`
- Corregir un error = insertar `ajuste_manual` con motivo y usuario
- El saldo es columna derivada en `clientes`, actualizada **dentro de la
  misma transacción de base de datos** que inserta el movimiento

### 3.5 El nivel efectivo son tres fuentes separadas

```
clientes
├── nivel_por_puntos        derivado del saldo vigente
├── nivel_por_membresia  + membresia_vigente_hasta
├── nivel_por_evento     + evento_vigente_hasta
├── nivel_efectivo          = MAX de los tres vigentes (materializado)
├── nivel_efectivo_desde
└── proteccion_hasta        no baja antes de esta fecha
```

Nunca colapsar las tres fuentes en un solo campo.

### 3.6 Ascenso síncrono, descenso nocturno

- **Ascenso** se evalúa en la misma petición que registra la compra. El
  cliente debe ver su nivel nuevo al instante.
- **Descenso** solo en el job nocturno, y solo al cerrar la ventana de
  permanencia, con 3 meses de protección y aviso 30 días antes.

Nunca bajarle el nivel a alguien en medio de una compra.

### 3.7 Idempotencia por restricción de base de datos

Todo POST de la API pública exige header `Idempotency-Key`. La protección
es una **restricción única en la base de datos** sobre
`(comercio_id, referencia_externa)`.

Nunca resolver esto con una validación en código: tiene condición de
carrera y duplica puntos cuando un POS reintenta.

### 3.8 Esquema JSON cerrado

Las reglas se guardan como JSONB validado contra JSON Schema en el
`save()`. Solo claves y operadores de una lista blanca.

Prohibido: expresiones libres tipo `"monto > 50000 && categoria == 'x'"`,
`eval()`, `create_function`, o cualquier evaluación dinámica de strings
provenientes del panel. Eso es ejecución remota de código.
`tests/Unit/ArquitecturaTest.php` lo verifica con el preset de seguridad
de Pest.

### 3.9 Dinero y tiempo

- Montos en **pesos enteros**. El peso colombiano no tiene centavos.
  Nunca float, nunca decimal, nunca centavos.
- Zona horaria `America/Bogota` fijada en `config/app.php` y en la
  conexión de base de datos (`config/database.php`). Los cortes de
  periodo dependen de esto. Hay una prueba que lo verifica.

### 3.10 Identificación del cliente

La cédula es **índice único**, nunca llave primaria. Las cédulas se
corrigen; los identificadores internos no.

### 3.11 Valor otorgado en cada redención

Toda redención guarda el valor en pesos del beneficio entregado. Habilita
el reporte de aporte por comercio. No es opcional.

### 3.12 QR firmado con ventana corta

El código de la credencial se firma en el servidor y rota cada pocos
segundos. Nunca un QR estático que sobreviva a una captura de pantalla.

### 3.13 Código agnóstico del motor de base de datos

Las pruebas corren contra PostgreSQL **y** SQL Server. Todo debe funcionar
en los dos:

- Usar el query builder y Eloquent. Para JSON, la sintaxis de Laravel
  (`where('definicion->clave', …)`), nunca operadores nativos (`->>`, `@>`,
  `jsonb_*`, `ILIKE`).
- En migraciones, solo tipos de Laravel (`jsonb()` se vuelve `nvarchar(max)`
  en SQL Server; no depender de índices GIN ni parciales).
- La hora la pone la aplicación (`now()`), nunca `NOW()`/`GETDATE()` del
  motor: SQL Server no tiene zona horaria de sesión.
- SQL Server compara sin distinguir mayúsculas por defecto: normalizar
  correos y códigos antes de guardarlos y buscarlos.
- `DB::statement()` o SQL crudo solo si es estándar y está probado en los
  dos motores.

---

## 4. Modelo de datos esencial

```
comercios          id, razon_social, nit, categoria_id, activo
sedes              id, comercio_id, nombre, direccion, activa
categorias         id, nombre, slug
reglas             id, tipo, version, vigente_desde, vigente_hasta,
                   activa, definicion (jsonb)
clientes           id, documento (unique), celular, email,
                   saldo_puntos, nivel_* (ver 3.5)
transacciones      id, cliente_id, comercio_id, sede_id, monto,
                   referencia_externa, idempotency_key, registrada_por,
                   created_at
                   UNIQUE (comercio_id, referencia_externa)
movimientos_puntos id, cliente_id, comercio_id, transaccion_id,
                   tipo (acumulacion|redencion|caducidad|ajuste_manual|reversa),
                   puntos (+/-), regla_id, regla_version,
                   vigencia_hasta, motivo, usuario_id, created_at
beneficios         id, comercio_id, nivel, tipo (porcentaje|monto_fijo),
                   valor, vigencia, cupo, max_por_cliente_mes
redenciones        id, cliente_id, beneficio_id, comercio_id,
                   valor_otorgado_cop, validado_por, created_at
membresias         id, cliente_id, plan_id, vigente_hasta, estado
eventos_calificados id, cliente_id, tipo, nivel_otorgado, vigente_hasta,
                   soporte_url, registrado_por
```

**Por revisar (responsable):** el tipo `redencion` en `movimientos_puntos`
choca con la regla 3.1: usar un beneficio no descuenta puntos y la
redención ya vive en su propia tabla. Confirmar en la spec E3-02 si se
elimina del enum antes de crear la migración.

---

## 5. Flujo de trabajo

### Dónde están las tareas

- Cada feature es un **issue** en GitHub con su ID de spec en el título:
  `[E3-01] Motor de reglas configurable`.
- Etiquetas: `epica:E1`…`epica:E10`, `semana:1`…`semana:6`,
  `riesgo:alto` (lo toma el responsable del proyecto), `decision-cliente`,
  `bloqueado`.
- Milestones = hitos de pago (Hito 2, 3 y 4).
- Tablero: GitHub Project del repositorio (usuario ronaldo-duran, proyecto 6).
- Para saber qué sigue: issues abiertos de la `semana:N` actual, en orden
  de número. Antes de empezar uno, leer sus dependencias.

### Specs antes que código

Cada feature tiene su spec en `/specs/` antes de implementarse. Si no hay
spec, no hay código. La plantilla está en `specs/_PLANTILLA.md`. El issue
dice qué spec le corresponde; si no existe, el primer PR del issue es la
spec.

Las reglas de negocio con números **no van en la prosa de la spec**: van
en fixtures JSON bajo `tests/Fixtures/`. Cuando el cliente cambie un tope,
se toca el fixture y el CI dice qué se rompió.

### Commits

[Conventional Commits](https://www.conventionalcommits.org/es/), validados
por Husky al hacer commit y otra vez en el CI:

```
tipo(alcance opcional): descripción en minúscula y en imperativo

feat(reglas): versiona la regla al guardar
fix(cajero): evita doble registro con doble toque
test(libro): cubre la reversa fuera de plazo
```

Tipos: `feat`, `fix`, `refactor`, `perf`, `test`, `docs`, `style`, `build`,
`ci`, `chore`, `revert`. Un cambio que rompe compatibilidad lleva `!`
(`feat(api)!: …`) o `BREAKING CHANGE:` en el cuerpo.

El hook de pre-commit corre Pint, PHPCS, PHPMD y PHPStan sobre los PHP
cambiados, y ESLint y Prettier sobre JS, CSS, JSON, YAML y Markdown.

### Versión

`package.json` tiene la versión del producto: tres números,
`MAYOR.MENOR.PARCHE`. Si el cambio toca código que llega a producción
(`app/`, `config/`, `database/`, `resources/`, `routes/`, `public/`,
`bootstrap/`, dependencias, `.env.example`), **la versión debe subir**:

```bash
npm version patch --no-git-tag-version   # fix
npm version minor --no-git-tag-version   # feat
npm version major --no-git-tag-version   # cambio incompatible
```

No exige versión si solo cambian docs, specs, pruebas, CI o configuración
de herramientas. Para otra excepción: `[sin-version]` en el título del
commit, o la etiqueta `sin-version` en el PR.

### CI por etapas

Cada etapa corre solo si la anterior pasó:

1. **Calidad**: PHP (Pint, PHPCS, PHPMD, PHPStan, `composer audit`),
   frontend (ESLint, Prettier, `npm audit`, build), versión, Conventional
   Commits y tamaño del PR.
2. **Pruebas**: Pest en PostgreSQL (con cobertura mínima de 60 %) y en
   SQL Server.
3. **E2E**: Playwright contra la app real, en celular y escritorio.
4. **Revisión de Claude** (solo en PRs).

### Revisión de Claude

- Corre sola una vez por PR, cuando las etapas 1 a 3 están en verde.
- No repite lo que el CI ya verifica: revisa reglas duras, seguridad,
  concurrencia, calidad de las pruebas, N+1, arquitectura (controladores
  delgados, lógica en Actions, modelos que solo son modelos),
  sobreingeniería y alcance.
- Sus instrucciones están en `.github/claude/revision.md` y se leen desde
  `main`.
- `@claude revisa` en un comentario del PR la repite; `@claude <pedido>`
  le pide otra cosa. En un PR, solo responde con el CI en verde.
- Necesita el secreto `ANTHROPIC_API_KEY` o `CLAUDE_CODE_OAUTH_TOKEN`. Sin
  secreto, la etapa se omite sin poner el CI en rojo.

### Pull requests

Por ahora se trabaja directo sobre `main`. Cuando se active la protección
de la rama:

- **Máximo 400 líneas cambiadas** (sin contar locks). Un PR más grande se parte.
- Trunk-based: ramas cortas desde `main`, merge diario.
- Nombre de rama: `f/E3-01-motor-reglas`
- El cuerpo del PR debe incluir `Closes #N`
- Si el CI está rojo, el PR no se revisa.

### Reparto

El motor de reglas, el libro de puntos, las membresías y la idempotencia
de la API los implementa el responsable del proyecto, no el desarrollador
auxiliar. Son las áreas donde un error cuesta dinero real. Esos issues
llevan `riesgo:alto`. Si un issue sin esa etiqueta necesita tocar el libro
de puntos, debe hacerlo **a través de las Actions** del responsable, nunca
escribiendo en `movimientos_puntos` directamente.

### Decisiones pendientes del cliente

Si una tarea necesita un dato que el cliente no ha definido (sección 9),
no se inventa: placeholder evidente + `// TODO(cliente):` y se comenta en
el issue de decisiones. Por la cláusula 3 del acta, una decisión pedida
por escrito y sin respuesta en 3 días hábiles se toma con el supuesto
documentado y se sigue.

---

## 6. Comandos

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
composer run dev                   # servidor, cola, logs y vite juntos

# Antes de subir cualquier cambio:
composer fix                       # Pint y PHPCBF corrigen lo que pueden
composer lint                      # Pint, PHPCS, PHPMD y PHPStan (nivel máximo)
npm run format && npm run lint     # Prettier y ESLint
composer test                      # Pest (necesita PostgreSQL: base fidelizacion_test)
php artisan migrate:fresh --seed   # migraciones limpias y datos de prueba
npm run build                      # que el frontend compile
npm run test:e2e                   # Playwright (usa la base sembrada)
```

SQL Server solo corre en el CI. Si un cambio puede comportarse distinto
entre motores (sección 3.13), esperar el resultado del CI antes de darlo
por terminado.

En sesiones de Claude Code en la nube, `.claude/hooks/session-start.sh`
instala dependencias, activa los hooks de git y levanta PostgreSQL y Redis.
Composer corre como root ahí: usar `COMPOSER_ALLOW_SUPERUSER=1` (el hook
ya lo exporta) o Pest pierde sus plugins.

---

## 7. Convenciones

- Código en inglés, dominio en español: `Comercio`, `MovimientoPuntos`,
  `Beneficio`. Mantener consistencia con los nombres de tabla de arriba.
- Form Requests para validación, nunca validar en el controlador.
- Toda escritura que toque puntos va dentro de `DB::transaction()`.
- Acciones de dominio como clases invocables en `app/Actions/`.
- Nada de lógica de negocio en recursos de Filament ni en componentes
  Livewire: delegar a Actions.
- Mensajes de usuario en español de Colombia (tuteo); comentarios de
  código en español.
- Cobertura obligatoria en: motor de reglas, libro de puntos, membresías
  e idempotencia de la API. El resto, lo razonable. El CI exige 60 %
  global.
- Nada de `@phpstan-ignore`, `baseline` ni `@SuppressWarnings` para callar
  una herramienta sin una razón escrita al lado. Se corrige la causa.
- Cada pantalla nueva del cliente o del cajero lleva al menos una prueba
  E2E de su flujo principal en `tests/e2e/`.

### Interfaz

- Antes de tocar cualquier vista, usar la skill `club-ar-ui`
  (`.claude/skills/club-ar-ui/SKILL.md`). Diseño completo en
  `docs/diseno/` (pantallas aprobadas, componentes con medidas, tokens).
- Solo tokens: nada de colores hex sueltos en vistas. Los tokens están en
  `resources/css/design/tokens.css`.
- Zonas táctiles: 44 px en cliente y admin, 64 px en todo el cajero.

---

## 8. Fuera de alcance — NO construir

Si algo de esto parece necesario, **detenerse y preguntar**. Está
excluido por contrato:

- Aplicaciones nativas iOS y Android
- Integración con WhatsApp Business (ojo: el diseño del perfil muestra
  "Avisos por WhatsApp"; **no se construye**, ver issue de decisiones)
- Verificación por código de un solo uso (OTP por SMS)
- Cobro recurrente automático con tarjeta guardada (la renovación es por
  enlace de pago)
- Lectura automática de fotos de factura (OCR)
- Integración a la medida con un punto de venta específico (para eso está
  la API pública)
- Liquidación económica entre comercios (solo el reporte de aporte)
- Integración contable o facturación electrónica DIAN
- Migración de datos históricos de clientes
- Diseño de marca del programa (nombre, logotipo, manual de identidad)
- Beneficios con cupo global limitado (por confirmar: el acta menciona
  "cupos limitados"; ver issue de decisiones)
- Producto gratis como tipo de beneficio (exige inventario)

---

## 9. Decisiones del cliente aún pendientes

No inventar valores para estas. Si el código las necesita, usar un
_placeholder_ evidente y dejar `// TODO(cliente):` al lado. Se siguen en
el issue "Decisiones pendientes del cliente".

- [ ] Tasas de acumulación por categoría (diseño supone 1 punto por $1.000)
- [ ] Umbrales de puntos de cada nivel (diseño supone Plata 500, Oro 1.500, Diamante 3.000)
- [ ] Vigencia de los puntos (diseño supone 12 meses)
- [ ] Precio y periodicidad de las membresías (diseño supone solo Diamante: $49.900/mes o $499.000/año)
- [ ] Catálogo inicial de beneficios por comercio y nivel
- [ ] Nombre comercial del programa (trabajo: Club Aponte Rivera) y logo
- [ ] Pasarela de pagos elegida
- [ ] Cómo inicia sesión el cliente en la PWA (sin OTP por contrato)

**Pendiente de confirmación interna:** el modelo de vencimiento. La
decisión es entre vigencia individual por punto (cada punto vence a los
N meses) y ventana móvil de 12 meses. Está redactado como vigencia por
punto en `vigencia_hasta`; confirmar antes de implementar el job de
caducidad. Con N = 12 ambos modelos dan el mismo saldo; lo que cambia es
la granularidad del corte (diario o fin de mes).
