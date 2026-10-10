# E2-02 · Comercios, sedes y categorías

**Épica:** E2 · **Horas estimadas:** 14 · **Riesgo:** bajo

## Objetivo

El administrador del grupo da de alta los 15 comercios, sus sedes y la
categoría comercial de cada uno, que es la que el motor de reglas usa
para determinar cómo acumulan puntos.

## Fuera de alcance

Las tasas de acumulación por categoría: viven en el motor de reglas
(E3-01), no en esta tabla. Aquí la categoría solo se referencia. Registro
de compras (E4-01).

## Reglas de negocio

1. Tres entidades: `categorias` (nombre, slug), `comercios` (razón
   social, NIT, categoría, activo) y `sedes` (comercio, nombre,
   dirección, activa). Modelo en CLAUDE.md §4.
2. NIT único por comercio.
3. El slug de categoría es único y estable: es la llave que usan los
   fixtures de reglas (`tasas_por_categoria[].categoria_slug`). Cambiarlo
   rompe la referencia; se normaliza en minúsculas (regla 3.13).
4. Comercios y sedes se desactivan, no se borran. Un comercio inactivo no
   puede registrar compras (se verifica en E4-01).
5. Recursos de Filament para las tres entidades, con el alcance por
   comercio de E2-01 (un admin de comercio solo gestiona el suyo).
6. Toda alta, edición o desactivación queda en la auditoría de E2-01.

## Criterios de aceptación

- [ ] Dado un NIT ya existente, cuando se intenta crear otro comercio con
      el mismo NIT, entonces se rechaza.
- [ ] Dado un slug de categoría en mayúsculas o con espacios, cuando se
      guarda, entonces queda normalizado y único.
- [ ] Dado un comercio inactivo, cuando se consulta si puede registrar
      compras, entonces no puede (contrato verificado en E4-01).
- [ ] Dado que se crea, edita o desactiva un comercio o una sede, cuando
      se consulta la auditoría, entonces aparece el autor y el cambio.
- [ ] Dado un administrador de comercio, cuando entra al recurso de
      comercios, entonces solo ve y edita el suyo.

## Datos y migraciones

- Categorías iniciales (slugs): `restaurante`, `heladeria`, `cerveceria`,
  `colegio`, `inmobiliaria`. Coinciden con los fixtures de reglas.
- Seeder de muestra con 3 o 4 comercios de ejemplo del diseño (p. ej. El
  Rancho de Javi, Cervecería BBC, Colegio Semillitas del Futuro,
  Inmobiliaria Laura Rivera), marcados como provisionales con
  `// TODO(cliente):`. Basta para staging y pruebas; los 15 comercios
  reales no llegan de una, se cargan desde el panel a medida que el
  cliente entregue los datos (issue #1, decisión de insumos).

## Reglas duras de CLAUDE.md que aplican

- 3.13 Código agnóstico del motor (slugs y textos normalizados; nada de
  operadores nativos de JSON ni de `ILIKE`).

## Notas

- La categoría determina la tasa, pero la tasa se define y versiona en
  E3-01. Esta tabla no guarda números de negocio.

## Depende de

E2-01 (permisos y alcance por comercio). Habilita E3-01 (categorías) y
E4-01 (comercio activo).
