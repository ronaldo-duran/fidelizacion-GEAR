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

| Capa | Tecnología |
|---|---|
| Backend y API | Laravel 13 (PHP 8.3) |
| Panel administrativo | Filament 5 |
| PWA del cliente y cajero | Blade + Livewire 4 + Alpine, Tailwind v4 (build propio, separado del de Filament) |
| Base de datos | PostgreSQL 16 |
| Colas y caché | Redis + Horizon (Horizon se instala con el despliegue, E1-02) |
| Pruebas | Pest 4 (sobre PostgreSQL, nunca SQLite) |
| Análisis estático | Larastan nivel 6 |
| Formato | Laravel Pint |
| Fuentes | Fontsource (auto-alojadas, sin CDN) |
| Infraestructura | Hetzner + Coolify + Docker |

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

### Pull requests
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

# Antes de abrir cualquier PR:
./vendor/bin/pint                  # formato
./vendor/bin/phpstan analyse       # análisis estático, nivel 6
./vendor/bin/pest                  # pruebas (necesitan PostgreSQL: base fidelizacion_test)
php artisan migrate:fresh --seed   # verificar que las migraciones corren limpias
npm run build                      # que el frontend compile
```

En sesiones de Claude Code en la nube, `.claude/hooks/session-start.sh`
instala dependencias y levanta PostgreSQL y Redis automáticamente.
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
*placeholder* evidente y dejar `// TODO(cliente):` al lado. Se siguen en
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
