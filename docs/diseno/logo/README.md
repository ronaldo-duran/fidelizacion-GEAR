# Logo (provisional)

El logo definitivo del Club Aponte Rivera aún no existe. El cliente (Ronaldo)
autorizó el 2026-10-10 usar el **logo del grupo** de forma provisional y
recolorearlo a los colores de marca del Club mientras tanto.

| Archivo | Uso |
| --- | --- |
| `logo-grupo-original.webp` | Logo del grupo tal cual lo envió el cliente (azul). Referencia. |
| `logo-club-verde.png` | Recoloreado al verde de marca `--color-brand` (`#1f5c4f`). Fondos claros. |
| `logo-club-claro.png` | Versión clara (`--color-paper`, `#faf7f2`). Fondos oscuros (cajero, nivel Diamante). |

Las versiones recoloreadas conservan la silueta original (canal alfa) y solo
cambian el color de relleno. Los colores salen de
`resources/css/design/tokens.css`.

## Iconos ya desplegados en las rutas del app

A partir de estos originales se generaron los iconos en sus rutas finales
dentro de `public/`, para que al llegar el logo definitivo solo haya que
reemplazar las imágenes (mismas rutas, mismos tamaños):

| Ruta | Tamaño | Uso |
| --- | --- | --- |
| `public/favicon.ico` | 16/32/48 | Favicon del navegador. |
| `public/apple-touch-icon.png` | 180 | iOS (fondo paper, iOS ignora transparencia). |
| `public/icons/icon-192.png` | 192 | Icono PWA `any`. |
| `public/icons/icon-512.png` | 512 | Icono PWA `any`. |
| `public/icons/icon-maskable-512.png` | 512 | Icono PWA `maskable` (zona segura, fondo paper). |

El manifest de la PWA (E8-01, #12) y el logo del panel (E10-01, #11) deben
apuntar a estas rutas. Cuando llegue el logo definitivo del Club, se
regeneran estos archivos y nada más cambia. Al integrarlo en la interfaz,
seguir la skill `club-ar-ui`.
