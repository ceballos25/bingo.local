# Frontend PWA · jugador + mesa (v1.0)

App Bootstrap 5.3: bingos, compra (PSE multi / comprobante), carrito, mis cartones, cuenta, **en vivo (SSE)**, y **panel mesa** para operador/admin.

**UI:** Bootstrap **5.3.8** (navbar, bottom dock, Offcanvas, Modal, list-group, toast, Collapse). Paleta Tradición y Fiesta: Azul `#003399`, Amarillo `#FFCC00`, Rojo `#E60000`, Verde `#00994C`. Tipografía: Archivo + Source Sans 3.  
**Ciudad:** Select2 4.1 + tema Bootstrap 5.  
**Versión:** `CONFIG.appVersion` → footer (`v1.0`). Cache shell: `bingo-shell-v182+` en `sw.js`.

**Pruebas:** listo para smoke interno (jugador + mesa en paralelo). Ver checklist en [`../README.md`](../README.md).

## Local

Sirve en el mismo origen que la API vía symlink:

`https://bingo.local/app/` → `frontend/`

Config: [`js/config.js`](js/config.js) (`organizacionId`, `apiBase`, `appVersion`, marca, WhatsApp/correo de soporte, retorno PSE, fallback de cuentas de transferencia).  
Logo: `icons/logo.png` + `icon-192.png` / `icon-512.png` PWA.

## Rutas hash

| Ruta | Quién | Rol |
|---|---|---|
| `#/landing` | Jugador | Pauta / premios / CTA |
| `#/comprar/:id` | Jugador | Elegir cartones |
| `#/mis-cartones` | Jugador | Cartones propios |
| `#/vivo/:id` | Jugador | En vivo + SSE + cantar |
| `#/cuenta` | Jugador | Login / invitado / perfil |
| `#/mesa` | Mesa | Dashboard org |
| `#/mesa/partidas` · `#/mesa/partidas/:id` | Mesa | Partidas / gestionar |
| `#/mesa/vivo/:id` | Mesa | Mesa en vivo (tablero + bombo + validar) |
| `#/mesa/pagos` | Mesa | Pagos |

## Módulos JS (relevantes)

| Archivo | Rol |
|---|---|
| `js/app.js` | Router hash jugador + mesa |
| `js/cart.js` | Carrito Offcanvas, PSE, comprobante |
| `js/balota.js` | Balota 3D + panel Última/Anteriores compartido |
| `js/tablero75.js` | Tablero maestro B-I-N-G-O 75 |
| `js/draw-overlay.js` | Bombo SVG mesa + revelado fullscreen xl |
| `js/celebrate.js` | ¡LÍNEA! / ¡EQUIS! / ¡BINGO! (todos los hosts) |
| `js/sound.js` | Mezcla, balota, cantar arcade (unlock async) |
| `js/pages/vivo.js` | Jugador en vivo |
| `js/pages/mesa/vivo.js` | Operador en vivo + Offcanvas validar |
| `js/pages/mesa/dashboard.js` | Resumen org |
| `js/pages/mesa/shell.js` | Dock mesa |

## Mesa en vivo (resumen)

- Layout: **tablero** arriba · fila **bombo | Última/Anteriores | Cantadas**.
- Girar → mezcla (toda la duración) → balota **grande a pantalla completa** (jugadores igual).
- Cantada → SSE `reclamo_pendiente` → efecto + sonido en **todos** los hosts (**solo eventos nuevos**; sin replay de historial).
- Validar → Offcanvas `end` (sin backdrop): cartón del sistema + balotas en vivo + ciudad → Confirmar ganador.
- Reloj fecha/hora 12 h con segundos (móvil: fecha corta, fecha/hora en líneas separadas).
- Mobile: columna, bombo compacto, Offcanvas ancho completo, logo/reloj más contenidos.

## SSE

- Primera conexión (`desdeId=0`): el API arranca desde el último id (estado vía REST).
- Reconexión: cursor en `sessionStorage` + `reconectarConDesdeId` del evento `timeout`.

## Cache PWA

`sw.js` cachea el shell. Al cambiar HTML/CSS/JS, subir `bingo-shell-vN` para forzar actualización.

## Mover a otro hosting

1. Copia `frontend/`.
2. Ajusta `apiBase` en `js/config.js`.
3. HTTPS obligatorio para PWA/push.
4. iOS: *Añadir a Inicio* para notificaciones.
