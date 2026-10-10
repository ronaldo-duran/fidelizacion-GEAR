# Despliegue — staging y producción (E1-02)

Guía operativa para montar los dos entornos en Hetzner con Coolify y Docker.
El código de la aplicación (imagen, Horizon, scheduler) ya está en el repo;
este documento cubre los pasos manuales del servidor que no viven en el
código.

## Arquitectura

Un solo artefacto (`Dockerfile`) sirve tres procesos, diferenciados por el
comando:

| Proceso     | Comando                      | Qué hace                            |
| ----------- | ---------------------------- | ----------------------------------- |
| `web`       | nginx + php-fpm (supervisor) | Atiende HTTP/HTTPS (`/`, `/admin`). |
| `horizon`   | `php artisan horizon`        | Procesa las colas de Redis.         |
| `scheduler` | `php artisan schedule:work`  | Dispara las tareas programadas.     |

PostgreSQL 16 y Redis son recursos gestionados por Coolify (o externos). El
`docker-compose.yml` del repo los incluye para poder levantar staging de
forma autónoma; en producción se usan los recursos gestionados y se apagan
los servicios `postgres`/`redis` del compose.

## Entornos

- **staging**: se despliega **solo** en cada push a `main`. Datos ficticios
  (`php artisan migrate:fresh --seed`) para demos del hito. `APP_ENV=staging`.
- **production**: despliegue **manual por tag** (p. ej. `v1.2.0`). Nunca
  `migrate:fresh`; solo `migrate --force`. `APP_ENV=production`,
  `APP_DEBUG=false`.

### Despliegue automático de staging

En Coolify, en el recurso de staging:

1. Fuente: este repositorio, rama `main`.
2. Build: Dockerfile.
3. Activar _auto-deploy on push_ a `main` (webhook de GitHub).
4. Comando de despliegue (post-deploy): `php artisan migrate --force`.
   Alternativa para demos: `php artisan migrate:fresh --seed --force`.

### Despliegue de producción por tag

1. En el recurso de producción, fijar la fuente a **tags** (patrón `v*`).
2. Crear el tag desde `main` ya probado: `git tag v1.2.0 && git push origin v1.2.0`.
3. Coolify construye y despliega esa imagen.
4. Comando post-deploy: `php artisan migrate --force` (sin `fresh`, sin seed).

## Variables de entorno

Fuera del repo, en Coolify (sección _Environment Variables_). Partir de
`.env.example`. Claves que cambian por entorno:

- `APP_ENV` (`staging` | `production`), `APP_DEBUG=false` en producción.
- `APP_KEY` (generar una por entorno con `php artisan key:generate --show`).
- `APP_URL` con el dominio real y HTTPS.
- `DB_*` apuntando al PostgreSQL gestionado.
- `REDIS_*` apuntando al Redis gestionado; `QUEUE_CONNECTION=redis`,
  `CACHE_STORE=redis`.
- `HORIZON_PREFIX` distinto si dos entornos comparten el mismo Redis.
- Pasarela de pagos y Brevo en modo real (producción) — pendiente del
  cliente, ver E1-03.

## HTTPS y DNS

- Dominio o subdominio **del cliente** (p. ej. `staging.clubaponterivera.co`
  y `app.clubaponterivera.co`).
- Apuntar el registro A/AAAA a la IP del servidor Hetzner.
- Coolify emite y renueva el certificado (Let's Encrypt) automáticamente.

## Horizon y scheduler

- Horizon lee `config/horizon.php`; hay entornos `production`, `staging` y
  `local` con distinto número de procesos.
- El tablero `/horizon` está protegido: solo el administrador del grupo
  activo entra (ver `App\Providers\HorizonServiceProvider`).
- El scheduler corre con `schedule:work` dentro de su propio contenedor; no
  hace falta cron en el host. La zona horaria es `America/Bogota`
  (`config/app.php`), así que los cortes de periodo caen en hora de Bogotá.
- Tarea ya programada: `horizon:snapshot` cada cinco minutos (métricas del
  tablero). Los jobs nocturnos de descenso de nivel y caducidad se registran
  en E4.

## Estrategia de dos fases (optimizar costo)

Redis + Horizon es el objetivo, pero la cola de Laravel es una abstracción:
el código despacha jobs igual sea cual sea el motor. Eso permite arrancar más
barato y escalar sin reescribir nada.

### Fase 1 — arranque económico (sin Redis)

Para abaratar el primer servidor se puede empezar con la cola en la base de
datos y sin Horizon:

- `QUEUE_CONNECTION=database` y `CACHE_STORE=database` (o `file`) en el entorno.
- Un proceso de trabajo con `php artisan queue:work --tries=1` en lugar del
  contenedor `horizon` (el servicio `horizon` del compose se reemplaza por
  este comando).
- El `scheduler` sigue igual; los jobs nocturnos (descenso, caducidad) y los
  correos funcionan desde el día uno.
- Sin tablero de Horizon, el monitoreo de colas atascadas / jobs que no
  corrieron se cubre con un heartbeat liviano (ver E1-03).

Costo de esto: la cola mete escrituras y bloqueos a la misma PostgreSQL, que
es justo lo que Redis descarga al crecer.

### Fase 2 — escalar a Redis + Horizon

Cuando el volumen lo pida, la migración es de configuración, no de código:

1. Provisionar Redis (recurso de Coolify o externo) y fijar `REDIS_*`.
2. Cambiar `QUEUE_CONNECTION=redis` y `CACHE_STORE=redis`.
3. Volver a usar el contenedor `horizon` (`php artisan horizon`) en lugar del
   `queue:work`.
4. Desplegar. Horizon ya está instalado y configurado en el repo; no hay
   cambios en los jobs.

Recomendación: el único ahorro real es Redis, y un VPS pequeño lo corre por
muy poco, así que el salto de costo de incluirlo desde el inicio es mínimo
frente a la observabilidad que da. La fase 1 existe para quien necesite el
servidor más barato posible al arrancar, con la certeza de que escalar luego
es reversible y barato.

## Comprobación (criterios de aceptación)

1. **Merge a `main` aparece en staging sin pasos manuales**: hacer un cambio
   trivial, mergear y verificar que Coolify despliega solo.
2. **Horizon procesa un job**: `php artisan tinker` →
   `dispatch(function () { logger('ping horizon'); });` y ver el job en
   `/horizon` y en los logs.
3. **El scheduler corre en hora de Bogotá**: `php artisan schedule:list`
   muestra `horizon:snapshot`; confirmar que el contenedor `scheduler` está
   arriba y que las corridas quedan registradas.
4. **`/admin` responde por HTTPS**: abrir `https://<dominio-staging>/admin`
   y entrar con el usuario sembrado.

## Primer despliegue (resumen)

```bash
# En local, validar que la imagen construye y levanta
docker compose build
docker compose up -d
docker compose exec web php artisan migrate:fresh --seed --force
# Abrir http://localhost:8080/admin
```

Respaldos verificados, monitoreo y la lista de salida en vivo van en **E1-03**.
La provisión de la cuenta del servidor a nombre del cliente es el issue #1.
