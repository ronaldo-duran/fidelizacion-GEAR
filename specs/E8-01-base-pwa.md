# E8-01 · Base de la interfaz: PWA instalable y componentes primitivos

**Épica:** E8 · **Horas estimadas:** 10 · **Riesgo:** bajo

## Objetivo

Dejar la base sobre la que se arman el cajero y la PWA del cliente: layouts,
PWA instalable, página sin conexión y las primitivas compartidas del diseño,
con una pantalla interna de muestra para compararlas contra la referencia.

## Fuera de alcance

- Las pantallas reales (inicio, catálogo, historial, perfil, cajero): viven en
  E8-03, E8-04, E8-05 y E4-02.
- La lógica de negocio (puntos, niveles, beneficios): llega con E3 y E4; aquí
  los componentes solo reciben datos por props.
- Inicio de sesión del cliente (decisión pendiente, #18).
- El logo definitivo: se usa el placeholder "AR" (`docs/diseno/NOTAS.md`).

## Reglas de negocio

1. Los componentes son presentacionales: reciben todo por props y no consultan
   nada. Pintan el nivel con `var(--color-tier-{nivel}-*)`, que siempre existe
   en `:root`.
2. El service worker no cachea respuestas con datos del cliente. Solo precachea
   el casco estático (página "Sin conexión" e íconos) y, ante un fallo de red
   en una navegación, muestra la página sin conexión.
3. La pantalla de muestra `/_ui` responde 404 en producción.

## Criterios de aceptación

- [ ] Dado un navegador a 360 px cuando se abre `/_ui` entonces se ven las
      primitivas sin desbordar el ancho.
- [ ] Dado cualquier vista cuando se revisa su marcado entonces no hay colores,
      radios ni tamaños hardcodeados: solo tokens y clases del preset.
- [ ] Dado un dispositivo Android o iOS cuando se abre la PWA entonces se puede
      instalar (manifest + íconos + service worker registrado).
- [ ] Dado el entorno de producción cuando se pide `/_ui` entonces responde 404.
- [ ] Dada una pérdida de red cuando se navega entonces aparece la página
      "Sin conexión" con la acción "Reintentar".

## Fixtures

No aplica (sin reglas de negocio con números).

## Reglas duras de CLAUDE.md que aplican

- 3.9 Dinero y tiempo: los helpers formatean pesos enteros y hora
  `America/Bogota` en estilo colombiano (`$85.000`, `1.160 pts`, `7:42 p. m.`).

## Notas de interfaz

- Primitivas de `docs/diseno/COMPONENTS.md` → "Primitivas compartidas":
  LogoAR, TierDiamonds, TierBadge, TierCard, ProgressBar, ClientHeader, TabNav,
  BenefitRow, BenefitListItem, Chip, TextField (`<x-field>`), Checkbox, Toggle,
  Button (primario, deshabilitado con ayuda, secundario, enlace, destructivo),
  Notice (aviso, info), más los estados Skeleton (1w) y vacío con CTA (1u, 1v).
- Tokens en `resources/css/design/tokens.css`; escala, radios y alturas táctiles
  en `resources/css/app.css`. Iconos Heroicons v2 (`blade-ui-kit/blade-heroicons`).
- Skill `club-ar-ui` y referencia en `docs/diseno/reference/`.
