# fidelizacion-GEAR-Produccion

Repositorio de **artefactos de producción** del Club Aponte Rivera. No se
programa aquí: el código llega ya compilado desde el repo fuente
(`ronaldo-duran/fidelizacion-GEAR`) cada vez que se crea un tag `v*`.

Por eso aquí **sí** se versionan `vendor/` y `public/build/`: el hosting
compartido de Hostinger no tiene Composer ni Node, así que recibe todo listo
para ejecutar.

## Cómo llega el código

1. En el repo fuente se crea un tag: `git tag v1.2.0 && git push origin v1.2.0`.
2. El workflow **Desplegar a producción** del repo fuente compila
   (`composer install --no-dev`, `npm run build`) y publica un commit en el
   `main` de este repo con todo compilado.
3. Ese push dispara el workflow **Actualizar servidor** de este repo, que entra
   por SSH al servidor y corre `scripts/deploy/actualizar-servidor.sh`.

No hay que tocar este repo a mano.

## Secretos y variables que configura Ronaldo

### En el repo fuente (`fidelizacion-GEAR`)

Settings → Secrets and variables → Actions → **New repository secret**:

| Secreto                | Qué es                                                                        |
| ---------------------- | ----------------------------------------------------------------------------- |
| `PROD_REPO_DEPLOY_KEY` | Clave privada SSH con permiso de **escritura** sobre este repo de producción. |

Para crearla:

```bash
ssh-keygen -t ed25519 -C "deploy-produccion" -f deploy_prod -N ""
```

- El contenido de `deploy_prod` (privada) → secreto `PROD_REPO_DEPLOY_KEY` en el repo fuente.
- El contenido de `deploy_prod.pub` (pública) → este repo → Settings → Deploy keys →
  **Add deploy key**, marcando **Allow write access**.

### En este repo (`fidelizacion-GEAR-Produccion`)

Settings → Secrets and variables → Actions:

| Secreto       | Qué es                                                                                          |
| ------------- | ----------------------------------------------------------------------------------------------- |
| `SSH_HOST`    | Host o IP del servidor de Hostinger.                                                            |
| `SSH_PORT`    | Puerto SSH (en Hostinger suele ser `65002`).                                                    |
| `SSH_USER`    | Usuario SSH del hosting.                                                                        |
| `SSH_KEY`     | Clave privada SSH para entrar al servidor (su pública va en el `authorized_keys` del servidor). |
| `DEPLOY_PATH` | Ruta absoluta del clon de este repo en el servidor.                                             |

Variable opcional (misma pantalla, pestaña **Variables**):

| Variable  | Qué es                                                             |
| --------- | ------------------------------------------------------------------ |
| `PHP_BIN` | Binario de PHP del host si no es `php` (p. ej. `/usr/bin/php8.3`). |

## Preparación del servidor (una sola vez)

En el servidor compartido, por SSH:

1. **Clave de GitHub Actions → servidor.** Agregar la pública de `SSH_KEY` a
   `~/.ssh/authorized_keys`.

2. **Clave del servidor → GitHub (lectura).** Para que el servidor pueda
   `git pull` de este repo privado:

   ```bash
   ssh-keygen -t ed25519 -C "servidor-produccion" -f ~/.ssh/github_prod -N ""
   cat ~/.ssh/github_prod.pub   # agregar como Deploy key (solo lectura) de este repo
   ```

   Y configurar el SSH del servidor para usarla con GitHub (`~/.ssh/config`):

   ```
   Host github.com
     IdentityFile ~/.ssh/github_prod
     IdentitiesOnly yes
   ```

3. **Clonar el repo** en la ruta que usará `DEPLOY_PATH`:

   ```bash
   git clone git@github.com:ronaldo-duran/fidelizacion-GEAR-Produccion.git <DEPLOY_PATH>
   cd <DEPLOY_PATH>
   ```

4. **Configurar `.env`** de producción (no está versionado):

   ```bash
   cp .env.example .env
   php artisan key:generate
   # Editar: APP_ENV=production, APP_DEBUG=false, APP_URL, DB_* (MySQL de Hostinger),
   # QUEUE_CONNECTION=database, CACHE_STORE=database (o file), correo Brevo, etc.
   ```

5. **Permisos y symlink:**

   ```bash
   chmod -R ug+rw storage bootstrap/cache
   php artisan storage:link
   ```

6. **Document root.** Apuntar el dominio (`club.grupoaponterivera.com`) a la
   carpeta `public/` del clon (o enlazar `public_html` → `<DEPLOY_PATH>/public`).

7. **Cron de la cola y tareas programadas** (sin Redis/Horizon en fase 1), con
   el binario de PHP del host:

   ```
   * * * * * cd <DEPLOY_PATH> && php artisan schedule:run >> /dev/null 2>&1
   ```

Hecho esto, cada tag `v*` en el repo fuente actualiza el servidor solo.

> **Alternativa sin git en el servidor:** si el hosting no permite `git`, se puede
> cambiar `scripts/deploy/actualizar-servidor.sh` para recibir los archivos por
> `rsync`/`scp` desde el runner en vez de `git fetch`/`reset`. Ver `docs/despliegue.md`
> en el repo fuente.
