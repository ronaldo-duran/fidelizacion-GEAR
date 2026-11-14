# E9-02 · Documentación interactiva de la API

**Épica:** E9 · **Horas estimadas:** 10 · **Riesgo:** bajo

## Objetivo

El proveedor del POS de un comercio abre una página con la referencia de la
API, lee los ejemplos de cada operación y las ejecuta desde ahí ("Try it")
contra un ambiente con datos ficticios, con una credencial de pruebas.

## Fuera de alcance

- **Cambiar la API.** Las siete operaciones, sus errores y sus topes son los
  de E9-01; esta feature solo los muestra.
- **Generar el OpenAPI desde el código** (`dedoc/scramble`, lo que proponía
  el issue). E9-01 ya dejó `docs/api/openapi.yaml` escrito a mano, con sus
  ejemplos y errores, y una prueba que exige que cubra las rutas
  registradas. Generarlo desde el código duplicaría la fuente o perdería
  esos ejemplos (decisión 1).
- **El portal en producción** (decisión 2). Los comercios prueban en
  staging o en el servidor de demostración; a producción solo llega la
  credencial real.
- Un registro de desarrolladores, llaves de autoservicio o un sandbox
  aislado por proveedor: la credencial de pruebas la crea el administrador
  en el panel, como en producción (E9-01).
- SDK o colecciones de Postman.
- Conectar un POS concreto (§8).

## Reglas de negocio

1. **Una sola fuente.** `docs/api/openapi.yaml` sigue siendo la referencia
   (E9-01, regla 13). El portal lo muestra tal cual; no hay un segundo
   archivo ni anotaciones en los controladores.
2. **El portal** es una página en `/desarrolladores` que muestra la
   referencia con Scalar (`@scalar/api-reference`, MIT, por npm, empacado
   con Vite en una entrada propia para no engordar la PWA). El archivo se
   sirve en `/desarrolladores/openapi.yaml`.
3. **Apagado por defecto.** El portal y su archivo responden `404` salvo
   que `API_PORTAL=true` (`config('api.portal')`). Solo se enciende en un
   servidor con datos ficticios: staging o demostración. Nunca en el
   servidor con clientes reales (decisión 2). La guía de despliegue lo dice
   junto a `SEMBRAR_DATOS_DE_MUESTRA`.
4. **"Try it" contra el mismo servidor.** Al servir el archivo, la lista de
   `servers` se reemplaza por una sola entrada: `APP_URL` + `/api/v1`. Así,
   las peticiones del portal van al ambiente donde está encendido, nunca a
   `club.example.com` ni a producción. El archivo del repositorio no cambia.
5. **Nada sale a terceros.** Scalar se configura sin sus fuentes externas,
   sin su proxy de CORS (las peticiones son al mismo origen) y sin enlaces a
   servicios de Scalar. La página pasa por `SecurityHeaders` (no se quita de
   la ruta) y no carga nada de un CDN. Las fuentes son las del programa
   (Fontsource).
6. **Aviso visible.** Encima de la referencia, una franja con el texto
   `settings.banner` del fixture: es un ambiente de pruebas con datos
   ficticios y la credencial se pide al administrador del grupo.
7. **Ejemplos de las siete operaciones.** Cada operación del archivo tiene:
   - un ejemplo de su respuesta exitosa;
   - si tiene cuerpo, un ejemplo de la petición;
   - si es un `POST`, el header `Idempotency-Key` con un ejemplo de UUID.

   Una prueba recorre el archivo y lo exige, igual que la prueba de rutas de
   E9-01. Hoy les faltan ejemplos a varias operaciones (por ejemplo
   `GET /clientes/beneficios`, `GET /compras/{referencia_externa}` y
   `GET /codigos/{codigo}`): se completan en el PR del código.

8. **Credencial de pruebas.** No se siembra ningún token: la regla de
   seguridad del §7 prohíbe credenciales conocidas fuera de local. En
   staging, el administrador del grupo crea en el panel una credencial por
   proveedor (Comercios › Credenciales API) sobre un comercio de muestra y
   se la entrega. La rota o la revoca al terminar la integración.
9. **Datos ficticios.** El ambiente usa los comercios, sedes y clientes de
   `DatosDeMuestraSeeder` (`SEMBRAR_DATOS_DE_MUESTRA=true`). La guía lista
   tres documentos de muestra (uno registrado con beneficios, uno sin
   registrar y uno con una compra anulada) para probar los casos
   principales. Se eligen del seeder en el PR del código y quedan en el
   fixture.
10. **Guía de integración.** `docs/api/README.md` (E9-01) ya cubre
    autenticación, idempotencia, reintentos, errores y topes. Se agrega una
    sección "Ambiente de pruebas": cómo pedir la credencial, la dirección
    del portal, los documentos de muestra y el aviso de que los datos se
    reinician en cada despliegue de staging.

## Criterios de aceptación

- [ ] Dado `API_PORTAL` sin definir, cuando alguien abre `/desarrolladores`
      o `/desarrolladores/openapi.yaml`, entonces responde `404`.
- [ ] Dado `API_PORTAL=true`, cuando alguien abre `/desarrolladores`,
      entonces ve la franja de pruebas y la referencia con las siete
      operaciones, y la respuesta trae los encabezados de `SecurityHeaders`.
- [ ] Dado `API_PORTAL=true` y `APP_URL=https://staging.club.test`, cuando
      se pide `/desarrolladores/openapi.yaml`, entonces `servers` tiene una
      sola entrada, `https://staging.club.test/api/v1`, y el resto del
      archivo es igual al del repositorio.
- [ ] Dado el archivo del repositorio, cuando corre la prueba de ejemplos,
      entonces cada operación cumple la regla 7. Quitar un ejemplo la hace
      fallar.
- [ ] Dado el portal encendido en staging con una credencial de pruebas,
      cuando el proveedor ejecuta `POST /compras` desde "Try it" dos veces
      con la misma `Idempotency-Key`, entonces la segunda responde `200`
      con `Idempotent-Replayed: true`. Se comprueba a mano en staging; el
      E2E solo carga la página.
- [ ] Dada la página del portal, cuando carga, entonces no pide nada a otro
      dominio (E2E: ninguna petición fuera del origen).

## Fixtures

`tests/Fixtures/portal-api-escenarios.json`: la ruta y el archivo, la
bandera y su valor por defecto, la franja, los casos de encendido y
apagado, el reemplazo de `servers`, lo que debe tener cada operación, la
configuración de Scalar que no se puede activar y los documentos de
muestra.

## Reglas duras de CLAUDE.md que aplican

- 3.1 Los ejemplos y la franja no hablan de canjear puntos: las compras
  suman puntos de estatus y entregar un beneficio no los descuenta.
- 3.7 Los ejemplos de `POST` llevan `Idempotency-Key`, y la guía repite
  que el reintento va con la misma llave.
- 3.10 Los ejemplos piden al cliente por tipo y número de documento.
- §7 Seguridad: el portal pasa por `SecurityHeaders`, no hay credenciales
  conocidas fuera de local y nada se carga de otro sitio.

## Decisiones

1. **Scalar sobre el YAML, no Scramble** (Neyder, 2026-11-14). Una sola
   fuente, sin perder los ejemplos ni los errores escritos en E9-01. La
   dependencia nueva es npm (`@scalar/api-reference`, MIT); se pide su
   aprobación en este PR.
2. **Solo en staging y demostración** (Neyder, 2026-11-14). Apagado por
   defecto. Así nadie ejecuta "Try it" contra clientes reales y el
   criterio "el portal no expone datos reales" se cumple por construcción.
3. **`/desarrolladores` y no `/api/docs`.** Todo lo que cuelga de `/api`
   responde errores en JSON (E9-01, regla 14); el portal es una página.
4. **Sin token sembrado** (regla 8), por la auditoría de seguridad de
   noviembre de 2026.

## Notas de interfaz

- Página propia, fuera de la PWA y del panel: sin encabezado del cliente ni
  menú de Filament. Arriba, el nombre del programa y la franja de pruebas
  con los tokens de aviso (`bg-warn-soft`, `border-warn-line`, `text-warn-ink`).
- Scalar toma los colores del programa: el acento es `--color-brand` y las
  fuentes son Figtree y Bricolage Grotesque. Nada de hex sueltos: los
  tokens se traducen a las variables `--scalar-*` en la hoja de la entrada
  del portal.
- Es para escritorio (proveedores de POS); a 360 px solo debe verse sin
  desbordar la página.
