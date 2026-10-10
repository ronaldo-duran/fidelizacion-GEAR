# E2-01 · Cuentas, roles, permisos y auditoría

**Épica:** E2 · **Horas estimadas:** 18 · **Riesgo:** medio

## Objetivo

Cuatro tipos de usuario con permisos granulares y alcance por comercio, y
un registro de auditoría de toda operación sensible, para que cada quien
vea y haga solo lo suyo y todo cambio quede rastreado.

## Fuera de alcance

Login y tema del panel (E10-01). Autenticación del cliente final en la
PWA (E8-02). La auditoría de movimientos de puntos la da el propio libro
inmutable (E3-02); aquí se audita la operación administrativa.

## Reglas de negocio

1. Cuatro roles: **administrador del grupo**, **administrador de
   comercio** y **cajero** son usuarios del sistema (tabla `users`);
   **cliente final** es un guard aparte y se define en E8-02.
2. Permisos granulares por acción, no por rol monolítico. Propuesta:
   `spatie/laravel-permission` (MIT). Confirmar antes de agregarlo
   (CLAUDE.md §2).
3. Alcance por comercio: un administrador de comercio y un cajero solo
   leen y escriben datos de su comercio (y el cajero, de su sede). El
   administrador del grupo ve todo.
4. `User` implementa `FilamentUser::canAccessPanel()`: a `/admin` solo
   entran administrador del grupo y administrador de comercio. Hoy, sin
   esto, cualquier usuario autenticado podría entrar en local.
5. Auditoría de operaciones sensibles (crear, editar, desactivar
   usuarios, comercios, sedes, reglas y beneficios): quién, cuándo y qué
   cambió (antes/después). Propuesta: `spatie/laravel-activitylog` (MIT),
   misma confirmación.
6. Los usuarios se desactivan, no se borran (coherente con E2-02). Un
   usuario inactivo no puede iniciar sesión.
7. La lógica de acceso vive en Policies y en el chequeo de panel, nunca
   en el recurso de Filament (CLAUDE.md §7).

## Criterios de aceptación

- [ ] Dado un usuario con rol cajero, cuando intenta abrir `/admin`,
      entonces recibe 403.
- [ ] Dado un administrador de comercio del comercio A, cuando lista
      clientes, transacciones o usuarios, entonces no ve registros del
      comercio B.
- [ ] Dado un cajero de la sede 1, cuando consulta transacciones,
      entonces solo ve las de su sede.
- [ ] Dado que se crea, edita o desactiva un usuario, cuando se consulta
      la auditoría, entonces aparece el autor, la fecha y el diff
      antes/después.
- [ ] Dado un usuario desactivado, cuando intenta iniciar sesión,
      entonces se le niega el acceso.
- [ ] Existe una prueba por cada política de acceso (una por rol y
      alcance).

## Datos y migraciones

- Tabla `users` ampliada con `comercio_id` (nullable; null = grupo),
  `sede_id` (nullable) y `activo` (bool, por defecto true).
- Tablas de `spatie/laravel-permission` si se aprueba el paquete.
- Seeder con un usuario de cada rol para staging (CLAUDE.md §7).

## Reglas duras de CLAUDE.md que aplican

- 3.13 Código agnóstico del motor (normalizar correos antes de guardar y
  buscar; SQL Server compara sin distinguir mayúsculas).

## Notas

- Correos normalizados (minúsculas, sin espacios) al guardar y al buscar,
  por la regla 3.13.
- Decisión de producto 10 del issue #1 (cómo inicia sesión el cliente) NO
  afecta a esta feature: aquí solo van los usuarios del sistema.

## Depende de

Nada bloqueante. Habilita E2-02, E3-01 (rol que edita reglas) y E10-01.
