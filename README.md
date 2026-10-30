# Bingo Virtual

Plataforma web de bingo en línea orientada al mercado colombiano, con proyección SaaS.

Este documento es la referencia de arquitectura, estado real del código y documentación de la API.  
**PWA:** v1.0 (`frontend/` · `https://bingo.local/app/` · shell cache `bingo-shell-v182+`). Incluye **jugador** y **panel mesa** (`#/mesa/*`).

**Pruebas internas:** sí — ya se puede hacer smoke test / primera partida controlada (ver checklist abajo). No es aún “lanzamiento público”.

## Forma de trabajo

- Inspeccionar los archivos reales antes de modificarlos.
- Hacer cambios pequeños y verificables.
- Respetar lo que ya funciona. No reescribir un módulo sin una razón concreta.
- No introducir frameworks PHP ni dependencias sin justificar su necesidad.
- No exponer mensajes internos, trazas ni credenciales en las respuestas de la API.
- Cursor guía; el desarrollador implementa. No entregar módulos enteros de golpe salvo que se pida.
- Evitar sobreingeniería: un catálogo de solo lectura no necesita Entity + Service + DTO completos.

## Arquitectura

Cada recurso de dominio se reparte en capas:

| Capa | Responsabilidad | Ejemplo |
|---|---|---|
| Entity | Datos del dominio y sus reglas | `Carton`, `Organizacion`, `Usuario` |
| Repository | SQL y mapeo con PDO | `CartonRepository` |
| Service | Casos de uso | `CartonService` |
| DTO | Forma del JSON de salida | `CartonResponseDTO` |
| Controller | HTTP → servicio → JSON | `CartonController` |
| Punto de entrada | Dependencias + recurso/método/acción | `public/index.php` |

`public/index.php` no contiene reglas de negocio ni SQL.

El autoload (`app/config/Autoload.php`) busca la clase por nombre de archivo en `config`, `entity`, `dto`, `repository`, `service` y `controller`. No hay namespaces ni `declare(strict_types=1);`. Composer carga `vendor/` desde `bootstrap.php` (p. ej. `firebase/php-jwt`).

**Excepciones deliberadas:**

- Ciudades (catálogo solo lectura) → `CiudadRepository` + `CiudadController`.
- JWT → `JwtService` (infraestructura; no es un recurso REST).

## Modelo SaaS

```text
organizaciones 1 ─── N usuarios
organizaciones 1 ─── N cartones
organizaciones 1 ─── N partidas
organizaciones 1 ─── N cuentas_pago   (Nequi / Bancolombia / Bre-B…)
usuarios       1 ─── N cartones   (solo cuando el cartón ya fue reservado/vendido)
usuarios       1 ─── N partidas   (operadorId = quien crea/gestiona la partida)
partidas       1 ─── N modalidades de premio (L, X, completa)
ciudades       1 ─── N usuarios
```

- Tenant = **organización**.
- Roles: `administrador`, `operador`, `jugador`.
- Cartón nace sin dueño (`usuarioId = null`); se asigna al reservar/vender.
- AuthGuard: JWT Bearer en rutas protegidas; identidad y org salen del token (ver sección Auth).

## API

URL base local: `https://bingo.local/`

Formato:

```text
/{recurso}/{accion}
```

### Autenticación (JWT)

Login con **correo o celular** + password, siempre dentro de una organización.

| Método | URL | Auth |
|---|---|---|
| POST | `/auth/login` | no |
| POST | `/auth/registro` | no (siempre crea `jugador`) |
| POST | `/auth/invitado` | no (nombre+celular+correo → JWT + enlace `#/acceso?token=`) |
| POST | `/auth/otp-solicitar` | no (solo administrador: envía OTP al correo) |
| POST | `/auth/otp-verificar` | no (código 6 dígitos → JWT admin) |
| GET | `/auth/yo` | `Authorization: Bearer <token>` |

Body de login:

```json
{
  "organizacionId": 1,
  "identificador": "contacto@ccmsoftware.com.co",
  "password": "secreto123"
}
```

`identificador` también acepta celular (`"3001234567"`).

Respuesta:

```json
{
  "token": "eyJ...",
  "usuario": { "id": 12, "rol": "jugador", "...": "..." }
}
```

Claims del JWT: `sub` (usuarioId), `organizacionId`, `rol`, `iat`, `exp`.

Variables en `~/websites/.env-bi`:

| Variable | Ejemplo | Uso |
|---|---|---|
| `JWT_SECRET` | cadena larga aleatoria | Firma HS256 |
| `JWT_TTL` | `604800` (7 días) | Expiración en segundos |

Capas: `JwtService`, `AuthService`, `AuthGuard`, `AuthContext`, `AuthResponseDTO`, `AuthController`.

Dependencia: `composer require firebase/php-jwt`.

### AuthGuard (rutas cerradas)

Casi todas las rutas exigen `Authorization: Bearer <token>` (o `?token=` solo en SSE).

| Público (sin JWT) | Notas |
|---|---|
| `POST /auth/login` | — |
| `POST /auth/registro` | alta jugador + JWT |
| `POST /auth/invitado` | acceso sin contraseña + enlace único |
| `GET /ciudades/listar` | catálogo |
| `GET /cuentas-pago/publicas?organizacionId=` | cuentas para transferencia |
| `GET /realtime/config` | driver sse/centrifugo |
| `GET /push/vapid-publica` | clave VAPID para PWA |
| `GET /partidas/publicas?organizacionId=` | landing: partidas pendiente/en_curso |
| `POST /organizaciones/crear` | alta tenant / bootstrap |
| `POST /ventas/webhook-openpay` | Basic Auth OpenPay |
| `GET /ventas/retorno-pse`, `GET /pago-ok` | API: consulta cargo OpenPay (`?id=`) |
| Estático `/app/pago-ok.html` | UI PWA tras volver del banco (no es la API) |

La identidad (`usuarioId` / `operadorId` / `administradorId` / `organizacionId` del actor) sale del **token**, no del body.  
Roles: mesa (`administrador`\|`operador`), admin solo (`aprobar`/`rechazar` reclamo), cualquier rol autenticado (jugador: cantar, PSE, etc.).

### Organizaciones

| Método | URL | Body |
|---|---|---|
| POST | `/organizaciones/crear` | `{"nombre":"CCM Software"}` |
| GET | `/organizaciones/listar` | — |
| GET | `/organizaciones/buscar?id=1` | — |
| PUT | `/organizaciones/actualizar?id=1` | `{"nombre":"...","estado":"activo"}` |

Estados: `activo`, `suspendido`.

### Usuarios

| Método | URL | Body |
|---|---|---|
| POST | `/usuarios/crear` | ver ejemplo (mesa) |
| GET | `/usuarios/listar` | org del JWT (mesa) |
| GET | `/usuarios/buscar?id=1` | — |
| PUT | `/usuarios/actualizar?id=1` | campos parciales (incluye `password`). Ver reglas de rol abajo |

Crear (org = JWT; mesa):

```json
{
  "ciudadId": 1,
  "nombre": "Ana",
  "apellido": "Pérez",
  "celular": "3001234567",
  "correo": "ana@mail.com",
  "rol": "jugador",
  "password": "secreto123"
}
```

- `password` opcional al crear (mín. 6 caracteres); se guarda con `password_hash`. **Nunca** sale en el DTO.
- Correo y celular únicos por organización.
- Roles: `administrador`, `operador`, `jugador`.
- **Protección:** operador solo crea/edita `jugador`. Solo admin asigna `operador`/`administrador`. Admin no se desactiva ni se quita el rol a sí mismo. Jugador solo edita su ficha (sin `rol`/`estado`/`correo`/`celular`).
- Listar/buscar: scoped a la org del token.
- Organización: `PUT /organizaciones/actualizar` solo admin y solo la org del JWT.

### Cartones

| Método | URL | Body |
|---|---|---|
| POST | `/cartones/crear` | `{}` (org = JWT; mesa) |
| GET | `/cartones/listar` | mesa → org; jugador → propios; `?partidaId=` filtra |
| GET | `/cartones/buscar?id=1` | — |
| PUT | `/cartones/actualizar?id=1` | ver flujo |
| DELETE | `/cartones/eliminar?id=1` | — (anula; no borra) |

Estados: `disponible`, `reservado`, `vendido`, `anulado`.

Vender/reservar: `{"estado":"vendido","usuarioId":5}`. Liberar: `{"estado":"disponible"}` → `usuarioId` a `null`.

En `GET /cartones/listar`, si el cartón está `reservado` y tiene pago pendiente, la respuesta incluye `pagoCanal` y `pagoEstado` (p. ej. `comprobante` / `pendiente`) para que la PWA muestre “En revisión”.

### Ciudades

| Método | URL |
|---|---|
| GET | `/ciudades/listar` |

## Partidas (enfoque A — en curso)

### Flujo de negocio

1. El **operador** entra al sistema.
2. Crea una **partida** (ej. “Bingo navideño”): fecha del evento, cupo de cartones, precio por cartón, premios por modalidad.
3. Al crear la partida, el sistema **genera automáticamente** esos cartones (`partidaId` + `organizacionId`, estado `disponible`, sin dueño).
4. Estado inicial de la partida: **`pendiente`**.
5. Los jugadores reservan/compran cartones de **esa** partida; `cartonesConfirmados` sube con cada venta confirmada.
6. Pasar a `en_curso`, sacar balotas, reclamar modalidades, `finalizada`.

Ejemplo:

```text
Bingo navideño
100 cartones × $50.000
Premios: L $1.000.000 | X $5.000.000 | completa $20.000.000
fecha: 2026-12-20 20:00
estado: pendiente
```

### Precio vs premio

| Campo | Qué es |
|---|---|
| `precioCarton` | Lo que **paga** el jugador por cartón |
| `premio` (modalidad) | Lo que **gana** quien cierra L, X o completa |
| `cantidadCartones` | Cupo a generar al crear (máx. **500** en dominio) |
| `cartonesConfirmados` | Cuántos ya están vendidos/confirmados (empieza en 0) |

### Modalidades (v1)

| Código | Significado |
|---|---|
| `linea` | Forma en L |
| `equis` | Forma en X |
| `completa` | Tabla / cartón lleno |

### Modularidad (arquitectura)

Una sola acción HTTP orquesta piezas ya existentes; no se mezcla todo en el controlador:

```text
PartidaService::crear
  ├── valida org + operador
  ├── guarda partida (estado pendiente)
  ├── guarda modalidades
  └── en ciclo: CartonGenerator + CartonRepository
        (N cartones con partidaId, usuarioId null, disponible)
```

Todo en **una transacción** PDO: si falla a mitad, no quedan partida a medias ni cartones huérfanos.

### Tablas

```text
partidas
├── nombre, numero, fecha, precioCarton
├── cantidadCartones, cartonesConfirmados
├── estado: pendiente | en_curso | finalizada | cancelada
├── youtubeVideoId (opcional)
├── organizacionId, operadorId

partida_modalidades → linea | equis | completa + premio

cartones.partidaId → FK a partidas (enfoque A)
cuentas_pago → org + tipo + valor/etiqueta + titular
```

### Body orientativo `POST /partidas/crear`

`organizacionId` y `operadorId` salen del JWT (no van en el body).

```json
{
  "nombre": "Bingo navideño",
  "numero": 15,
  "fecha": "2026-12-20 20:00:00",
  "precioCarton": 50000,
  "gratis": false,
  "cantidadCartones": 100,
  "modalidades": [
    { "modalidad": "linea", "premio": 1000000 },
    { "modalidad": "equis", "premio": 5000000 },
    { "modalidad": "completa", "premio": 20000000 }
  ]
}
```

`gratis: true` fuerza `precioCarton = 0` (bingo promocional).  
Marcar después: `POST /partidas/marcar-gratis` `{ "partidaId": 1, "gratis": true }` (solo `pendiente`; para quitar gratis envía `precioCarton > 0`).

Las tablas de partidas ya están en la BD local; los scripts versionados en el repo empiezan en `database/010_*.sql` (ver tabla más abajo).

## Postman

| Archivo | Uso |
|---|---|
| `postman/Bingo_API.postman_collection.json` | Colección (sin scripts que muten env) |
| `postman/Bingo_Local.postman_environment.json` | Variables locales |

Variables útiles: `base_url`, `organizacionId`, `ciudadId`, `usuarioId`, `cartonId`, `partidaId`, `token`, `password`, correos de prueba.

La colección usa **Bearer `{{token}}` a nivel colección** (Inherit auth from parent). Tras `POST /auth/login`, pega el `token` en el environment. Login y webhook OpenPay van en No Auth / Basic Auth.

## Cómo agregar un recurso de dominio

1. Entidad → 2. Repositorio → 3. Servicio → 4. DTO → 5. Controlador → 6. `index.php` → 7. README + Postman.

## Convenciones

- Clases/archivos: `PascalCase`
- Métodos/variables: `camelCase`
- Columnas: `creadoEn`, `organizacionId`
- PDO con consultas preparadas
- Errores técnicos → log; al cliente mensaje de dominio o genérico

## Entorno técnico

- DocumentRoot: `public/`
- URL: `https://bingo.local/`
- BD: `bingo`
- Secrets: `~/websites/.env-bi` (no mover ni exponer)

### Scripts SQL en el repo

Solo estos archivos viven en `database/` (el esquema base de org/usuarios/cartones/partidas ya está aplicado en local):

| Archivo | Qué hace |
|---|---|
| `database/010_pagos.sql` | Tabla `pagos` |
| `database/011_partida_balotas.sql` | Tabla `partida_balotas` |
| `database/012_reclamos.sql` | Tabla `reclamos` (L / X / completa) |
| `database/013_partidas_youtube.sql` | Columna `youtubeVideoId` en partidas |
| `database/014_partida_eventos.sql` | Bitácora `partida_eventos` (SSE / audit) |
| `database/015_push_subscriptions.sql` | Suscripciones Web Push (PWA) |
| `database/016_cuentas_pago.sql` | Tabla `cuentas_pago` + semilla org 1 |

### Realtime (Redis + Centrifugo)

El **dominio sigue 100% en este repo** (capas PHP). Redis y Centrifugo son **infra al lado**, no otra app de negocio.

| Dónde | Qué corre |
|---|---|
| Este proyecto (PHP) | API + `PartidaRealtime` → MySQL `partida_eventos` + HTTP publish a Centrifugo |
| Docker Compose | Solo `redis` + `centrifugo` (`docker compose up -d`) |
| Hosting compartido típico | Solo PHP/MySQL. **No** Docker. Pon `CENTRIFUGO_ENABLED=0` o apunta `CENTRIFUGO_API_URL` a un VPS barato con Compose |
| VPS | PHP + `docker compose` en la misma máquina (o Compose en un micro-VPS aparte) |

**No hay que sacar el bingo del repo.** Si el hosting no permite Docker, el realtime vive en otro lado y PHP solo hace un POST HTTP.

Variables en `~/websites/.env-bi`:

| Variable | Ejemplo |
|---|---|
| `CENTRIFUGO_ENABLED` | `0` (apagado por ahora) / `1` cuando se use |
| `CENTRIFUGO_API_URL` | local `http://127.0.0.1:8000` · prod `https://realtime.tudominio.com` |
| `CENTRIFUGO_API_KEY` | igual que `http_api.key` en `realtime/centrifugo.json` |
| `CENTRIFUGO_WS_URL` | local `ws://127.0.0.1:8000/connection/websocket` · prod `wss://...` |

**Estado actual:** `CENTRIFUGO_ENABLED=0`. El camino activo es **SSE PHP** (`GET /partidas/eventos`). Centrifugo no recibe publish; balotas/reclamos sí quedan en `partida_eventos` para el stream. Compose queda listo para reactivar.

**SSE en vivo:** `desdeId=0` (primera conexión) arranca desde el **último** evento — no reenvía historial de balotas/cantadas (eso ya viene del REST). Las reconexiones usan el cursor (`sessionStorage` + `reconectarConDesdeId`) y no pierden eventos nuevos. Así no se repite el efecto “¡Bingo cantado!” de reclamos ya aprobados.

Consulta el driver: `GET /realtime/config` → `{ "driver": "sse"|"centrifugo", "sseUrl", "wsUrl", "canalPlantilla" }`.

Canal Centrifugo (cuando enabled): `partida:{id}` (namespace `partida` en `realtime/centrifugo.json`).

Eventos que publica el API (Postman → balota/reclamo):

| `tipo` | Cuándo | Uso en front |
|---|---|---|
| `balota` | `POST /balotas/sacar` | Mostrar balota; incluye `mesa` |
| `reclamo_pendiente` | `POST /reclamos/cantar` | **Bloquear todos los botones**; admin ve jugador + ciudad + cartón |
| `reclamo_aprobado` | `POST /reclamos/aprobar` | Cerrar modalidad; desbloquear botones de las demás |
| `reclamo_rechazado` | `POST /reclamos/rechazar` | Reabrir mesa (`botonesBloqueados: false`) |

Payload siempre lleva `tipo`, `partidaId`, `mesa` (`botonesBloqueados`, `reclamoPendienteId`, `modalidadesCerradas`). En reclamos además `reclamo` (jugador, cartón, modalidad, premio).

Prueba local:

- Con Centrifugo **apagado** (default): abre `https://bingo.local/realtime-demo.html` (badge **sse**) + Postman sacar/cantar. No hace falta Docker.
- Con Centrifugo **encendido**: `CENTRIFUGO_ENABLED=1` + `docker compose up -d` → el demo usa WebSocket.
- Config hub: `realtime/centrifugo.json` (`client.insecure: true` solo local).

#### Aplazado: desplegar realtime en VPS (CyberPanel)

No subir el PHP del bingo al VPS. Solo la tubería Redis + Centrifugo. No hace falta un sitio del juego; sí conviene un **subdominio** con SSL para `wss://`.

1. En el VPS (Ubuntu + Docker):
   - `curl -fsSL https://get.docker.com | sh && systemctl enable --now docker`
   - Carpeta p. ej. `/opt/bingo-realtime/`
   - Copiar `docker-compose.yml` y `realtime/centrifugo.json`
   - Cambiar `http_api.key` (no usar la key de local en internet)
   - `docker compose up -d`
   - Probar: `curl` a `http://127.0.0.1:8000/api/publish` con `X-API-Key`
2. CyberPanel: crear subdominio `realtime.tudominio.com` → DNS A a la IP del VPS → SSL Let’s Encrypt → reverse proxy a `http://127.0.0.1:8000` (OpenLiteSpeed).
3. En `.env-bi` (o env del hosting PHP): `CENTRIFUGO_ENABLED=1` + `CENTRIFUGO_API_URL` / `CENTRIFUGO_API_KEY` / `CENTRIFUGO_WS_URL` (`wss://realtime...`).
4. En `centrifugo.json` del VPS: `allowed_origins` con el origen del front; luego quitar `client.insecure` y usar JWT de canal.

Archivos del repo a llevar: solo `docker-compose.yml` + `realtime/`.

## Estado del proyecto

### Backend: cerrado (requisitos de dominio)

Cumple el alcance acordado de API: capas PHP, multi-tenant, JWT + AuthGuard, partidas/mesa, balotas, reclamos (L/X/completa), ventas (manual / PSE multi / comprobante), cuentas de pago, YouTube opcional, realtime SSE (Centrifugo listo pero apagado), push VAPID.

### Listo

- Enrutamiento `/{recurso}/{accion}`
- Organizaciones, usuarios, cartones, ciudades, cuentas-pago
- Password + JWT (`/auth/login`, `/auth/registro`, `/auth/invitado`, `/auth/yo`)
- **AuthGuard** + roles + identidad/org desde token (no forjar `operadorId` / `administradorId` / actor `usuarioId`)
- Arquitectura por capas
- Postman con Bearer a nivel colección
- PWA jugador **v1.0** (`frontend/` → `https://bingo.local/app/`)

### Listo (partidas)

- `POST /partidas/crear` → partida `pendiente` + N cartones con `partidaId` (máx. 500); opcional `gratis`
- `GET /partidas/listar`, `GET /partidas/buscar?id=`, `GET /partidas/publicas?organizacionId=` (respuesta incluye `gratis`)
- `POST /partidas/iniciar`, `POST /partidas/finalizar`, `POST /partidas/asignar-youtube`
- `POST /partidas/marcar-gratis` → mesa marca/desmarca bingo gratis (solo `pendiente`)
- `GET /partidas/eventos` (SSE)
- `GET /cartones/listar?partidaId=` (lista completa; mesa/admin)
- `GET /partidas/vitrina?id=&page=&perPage=` (vitrina pública paginada; default 24)
- Mesa: rol `administrador` u `operador` de la org del token
- Multi-tenant: toda partida/cartón/pago queda atado a `organizacionId` del JWT

### Listo (pagos / venta)

Tabla `pagos` con canales: `manual_operador`, `openpay_pse`, `comprobante`, `gratis`.

Al confirmar venta (manual, gratis, PSE o aprobar comprobante) se notifica al comprador:

- **Correo** HTML (cartones + botón jugar) — siempre que haya Gmail en `.env-bi`
- **WhatsApp** automático si `WHATSAPP_ENABLED=1` + token Meta Cloud API
- Mesa siempre tiene botón **WhatsApp al cliente** (`wa.me`, mensaje listo)

```env
WHATSAPP_ENABLED=0
WHATSAPP_TOKEN=
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_TEMPLATE_NAME=compra_cartones
WHATSAPP_TEMPLATE_LANG=es
```

Dejar `WHATSAPP_ENABLED=0` hasta que Meta apruebe las plantillas; el correo y el botón `wa.me` de mesa siguen activos.

Plantilla Meta de **confirmación** (salida en frío), 3 variables:

```
Hola {{1}}, tu compra del bingo {{2}} está confirmada.
Entra a jugar con tu enlace personal (ya incluye acceso): {{3}}
```

`{{1}}` nombre · `{{2}}` nombre del bingo · `{{3}}` URL con token (`#/acceso?token=…&next=#/mis-cartones`).  
Sin plantilla, el API de texto libre solo funciona si el cliente ya escribió en las últimas 24 h. El botón de mesa `wa.me` siempre lleva el mismo enlace con token.

| Método | URL | Efecto |
|---|---|---|
| GET | `/ventas/listar` | mesa: pagos con cliente + cartón (filtro `?estado=`) |
| POST | `/ventas/manual` | pago `aprobado` + cartón `vendido` + `cartonesConfirmados++` + correo |
| POST | `/ventas/gratis` | cartones sin cobro + correo |
| POST | `/ventas/pse` | reserva + cargo OpenPay PSE (`cartonId` o `cartonIds[]`) → `pse.url` |
| POST | `/ventas/webhook-openpay` | Basic Auth; `charge.succeeded` → vendido; fail → libera |
| GET | `/ventas/retorno-pse` o `/pago-ok` | consulta estado cargo (`?id=`) |
| POST | `/ventas/comprobante` | form-data: reserva cartón + pago `pendiente` + imagen |
| POST | `/ventas/aprobar-comprobante` | mesa aprueba → `vendido` + contador++ |
| POST | `/ventas/rechazar-comprobante` | mesa rechaza → libera cartón |

Venta manual (`usuarioId` = comprador; operador = JWT):

```json
{ "cartonId": 1, "usuarioId": 2 }
```

Comprobante (form-data): `cartonId` + archivo `comprobante` (JPG/PNG/WEBP ≤ 5 MB). Jugador = JWT.  
Archivos en `public/uploads/comprobantes/`. URL pública: `/uploads/comprobantes/...`

Aprobar / rechazar:

```json
{ "pagoId": 2 }
```

### PSE OpenPay (API)

Arquitectura multi-pasarela: `PasarelaPago` ← `OpenPayPasarela`.

```json
POST /ventas/pse
{
  "cartonIds": [3, 4, 5],
  "redirectUrl": "https://bingo.local/app/pago-ok.html?partidaId=1"
}
```

También acepta un solo `cartonId` (compat). Comprador = JWT. Un cargo OpenPay por el total; respuesta incluye `pse.url` (redirect al banco). Docs: [Pagos con PSE](https://docs.openpay.co/docs/bank-charge.html) + API `method: bank_account` + [Webhooks](https://docs.openpay.co/docs/webhooks.html).

Webhook a registrar en Dashboard:

`https://bingo.local/ventas/webhook-openpay`  
Basic Auth = `OPENPAY_WEBHOOK_USER` / `OPENPAY_WEBHOOK_PASS`

Al verificar el webhook, OpenPay manda `type: verification`; respondemos 200 y el `verificationCode` queda en la respuesta/log para pegarlo en el Dashboard.

Script: `database/010_pagos.sql`.

### Cuentas de transferencia (comprobante)

Tabla `cuentas_pago` por organización (Nequi, Bancolombia, Bre-B, etc.). El **administrador** las crea/edita; el jugador las ve en el carrito al pagar con comprobante.

| Método | URL | Auth |
|---|---|---|
| GET | `/cuentas-pago/publicas?organizacionId=1` | no |
| GET | `/cuentas-pago/listar` | admin |
| POST | `/cuentas-pago/crear` | admin |
| PUT | `/cuentas-pago/actualizar?id=` | admin |
| DELETE | `/cuentas-pago/eliminar?id=` | admin (desactiva) |

Body crear/actualizar (ejemplo):

```json
{
  "tipo": "nequi",
  "banco": "Nequi",
  "marca": "N",
  "valor": "3245894268",
  "etiqueta": "324 589 4268",
  "titular": "Cristian Ceballos",
  "orden": 1,
  "activo": true
}
```

`tipo`: `nequi` | `bancolombia` | `breb` | `otro`. Script: `database/016_cuentas_pago.sql`.

**Nota local:** `public/uploads/comprobantes` debe ser escribible por Apache (`www-data`).

### Listo (balotas)

| Método | URL | Efecto |
|---|---|---|
| POST | `/partidas/iniciar` | `pendiente` → `en_curso` |
| POST | `/balotas/sacar` | saca 1 balota aleatoria (1–75, sin repetir) |
| GET | `/balotas/listar?partidaId=` | historial ordenado |
| POST | `/partidas/finalizar` | `en_curso` → `finalizada` |

```json
{ "partidaId": 1 }
```

Operador = JWT. Respuesta de sacar: `letra`, `numero`, `etiqueta` (ej. `B12`), `orden`, `restantes`.  
Script: `database/011_partida_balotas.sql`.

### Listo (reclamos)

El jugador canta primero; un solo ganador activo por modalidad. El administrador valida en vivo.

| Método | URL | Efecto |
|---|---|---|
| POST | `/reclamos/cantar` | bloquea modalidad (`pendiente`); pre-valida patrón |
| POST | `/reclamos/aprobar` | admin confirma → `aprobado` |
| POST | `/reclamos/rechazar` | admin rechaza → reabre modalidad |
| GET | `/reclamos/listar?partidaId=` | historial |

Cantar (jugador = JWT):

```json
{ "cartonId": 10, "modalidad": "equis" }
```

Aprobar / rechazar (admin = JWT):

```json
{ "reclamoId": 1 }
```

Patrones: `linea` = L (columna B + fila inferior), `equis` = diagonales, `completa` = cartón lleno (centro libre cuenta).  
También: `POST /reclamos/prevalidar` (mismo body que cantar; no bloquea mesa).  
Script: `database/012_reclamos.sql`.

### Listo (YouTube en partida)

Campo opcional `youtubeVideoId` (id o URL). Respuesta incluye `youtubeEmbedUrl`.

| Método | URL |
|---|---|
| POST | `/partidas/crear` (campo opcional `youtubeVideoId`) |
| POST | `/partidas/asignar-youtube` |

```json
{ "partidaId": 1, "youtubeVideoId": "https://youtube.com/watch?v=XXXXXXXXXXX" }
```

Script: `database/013_partidas_youtube.sql`.

### Listo (realtime)

- `GET /realtime/config` → driver `sse` o `centrifugo`
- `GET /partidas/eventos` → SSE (Bearer o `?token=`); bitácora `partida_eventos`
- Centrifugo + Redis en Compose; **apagado** (`CENTRIFUGO_ENABLED=0`) hasta VPS
- Demo local: `https://bingo.local/realtime-demo.html`

### Listo (PWA jugador · v1.0)

Carpeta portable [`frontend/`](frontend/) (symlink local `public/app` → misma carpeta).  
Versión de app: `CONFIG.appVersion` (`1.0`) visible en el footer.

| URL local | Uso |
|---|---|
| `https://bingo.local/app/` o `#/landing` | **Bingos / pauta** (hero compacto + premios + cómo + FAQ). Tab Bingos → aquí. Bootstrap **5.3.8**. |
| `https://bingo.local/app/#/bingos` | Alias → redirige a `#/landing` |
| `https://bingo.local/app/pago-ok.html` | Retorno PSE (limpia carrito de la partida) |

**Flujo jugador**

1. Landing (ads) → CTA **Quiero jugar** → `#/comprar/{id}` (vitrina pública, **sin** pedir cuenta).
2. Elegir cartones → carrito en el **dock inferior**.
3. Al pagar: si no hay sesión → modal de datos (`ensureGuestSession`, sin contraseña ni código), luego PSE o comprobante. Celular + correo correctos → entra directo. **Administrador u operador** → código OTP al correo en **Entrar**; en el modal de pago (`paraPagar: true`) entra sin código pero solo como jugador (JWT `soloJugador`, el panel responde 403). Tras PSE, `pago-ok.html` espera hasta ~1 min la confirmación y lleva solo a Mis cartones.
4. Pago: **PSE multi-cartón** (`cartonIds`) o **transferencia** (`/cuentas-pago/publicas` + comprobante).
5. Mis cartones: activos a color; partida `finalizada`/`cancelada` → card en gris + badge **Jugado** / **Cancelada**. Comprobante pendiente → “En revisión”.
6. En vivo (SSE) si la partida está `en_curso` y el cartón está `vendido`. Cantar L / Equis / Bingo → celebración (logo + confeti) + audio arcade **en todos los hosts** conectados; error → buzzer. Reloj fecha/hora en vivo (12 h).

**UI / UX (v1.0 · actual)**

- Dock fijo (móvil compacto / desktop más grande): Bingos · Cartones · Carrito · Cuenta (jugador) · Inicio/Partidas/Vivo/Pagos (mesa).
- Hero: nombre de partida + badge `fecha · Próximo a jugar` / `En vivo ahora`; CTA amarillo.
- Premios ilustrados (patrón L / X / completa) con copy emocional.
- Logo BINGO (`icons/logo.png`) más legible en header + iconos PWA.
- Sonido Web Audio: balota, corrediza, mezcla (todo el giro), cantar (arcade), error.
- Celebración full-screen al cantar (`js/celebrate.js`) por modalidad: ¡LÍNEA! / ¡EQUIS! / ¡BINGO!
- Footer: © CCM Software · v1.0 · Desarrollado por Cristian Ceballos (WhatsApp contratar).
- Centro de ayuda: copy en lenguaje jugador (operador valida cantada; acceso por correo).
- Paleta Tradición y Fiesta (`#003399`, `#FFCC00`, `#E60000`, `#00994C`). Tipografía: Archivo + Source Sans 3.
- PWA: `manifest.webmanifest` + `sw.js` network-first / bump `bingo-shell-vN`. Detalle: [`frontend/README.md`](frontend/README.md).

### Listo (panel mesa · operador / admin)

Misma PWA (`#/mesa/*`), roles `administrador` | `operador`. Org-scoped por JWT.

| Ruta | Uso |
|---|---|
| `#/mesa` | Dashboard: resumen org (`GET /organizaciones/resumen`) + accesos |
| `#/mesa/partidas` | Listado / crear partida |
| `#/mesa/partidas/:id` | Gestionar: iniciar, YouTube, gratis, finalizar |
| `#/mesa/vivo/:id` | **Mesa en vivo** (tablero + bombo + balotas + cantadas) |
| `#/mesa/pagos` | Pagos / comprobantes |
| `#/mesa/clientes` | Tabla personas: búsqueda, filtros, paginación, stats (`/usuarios/*`) |
| `#/mesa/organizaciones` | CRUD tenants (admin): tabla + filtros + paginación. Operador: solo lectura de su org |

**Mesa en vivo (operador)**

1. **Tablero maestro B-I-N-G-O 75** (cuadritos que se encienden; no balotas 3D en la grilla).
2. Fila: **bombo SVG** (gira el rotor) + **Girar** · panel **Última / Anteriores** (mismo estilo que el jugador) · **Cantadas** pendientes.
3. Al girar: sonido de mezcla durante todo el giro → revelado **xl a pantalla completa** (igual que jugadores). Tiempos en `CONFIG.sorteo` (`giroMs`, `periodoSeg`, `holdMs`, `variar`): admin y jugadores usan la misma fórmula (si `variar`, el número de la balota fija el matiz; ya no hay `Math.random`).
4. Al cantar (SSE `reclamo_pendiente`): efecto + sonido en **todos** los hosts (mesa + jugadores); **solo cantadas nuevas**, no el historial.
5. **Validar** abre Offcanvas Bootstrap (`end`, sin backdrop): cartón del sistema marcado con balotas en vivo, ciudad del jugador, **Confirmar ganador** / Rechazar. El tablero maestro sigue visible; en desktop el contenido se empuja con transición suave.
6. Reloj en vivo (fecha + hora 12 h con segundos; en móvil fecha corta en 2 líneas, sin pegarse al badge).
7. Mobile: fila en columna, bombo compacto, Offcanvas a ancho completo, tipografía/logo/reloj ajustados.

### Checklist — primeras pruebas (smoke)

Hacer con 1 admin + 1 operador + 1–2 jugadores (local o staging), cache forzada (`bingo-shell-v221`):

1. Login mesa (admin/operador) → `#/mesa` resumen → crear/abrir partida → iniciar.
2. Admin: `#/mesa/clientes` crear operador/jugador; `#/mesa/organizacion` editar nombre. Operador: solo crea/edita jugadores; org en solo lectura.
3. Jugador: landing → **Quiero jugar** → cartones → carrito → datos al pagar → PSE o comprobante.
4. Jugador `#/vivo/:id` y mesa `#/mesa/vivo/:id` abiertos a la vez.
5. **Girar** en mesa → ambos ven balota grande + sonido; tablero 75 se marca.
6. Jugador canta L / X / Bingo → efecto + sonido en **todos** los hosts.
7. Mesa: Validar → Offcanvas con cartón + ciudad → Confirmar o Rechazar; mesa se desbloquea.
8. Mobile: reloj legible, Offcanvas usable, dock no tapa botones.

**Aún no bloquear lanzamiento, pero anotar:** WhatsApp plantillas Meta, Centrifugo VPS, auto-cierre a 75 balotas.

**Auth invitado**

| Método | URL | Notas |
|---|---|---|
| POST | `/auth/invitado` | `organizacionId`, `ciudadId`, `nombre`, `celular`, `correo` → token + `enlaceAcceso` |

Notificaciones push:

| Método | URL | Auth |
|---|---|---|
| GET | `/push/vapid-publica` | no |
| POST | `/push/suscribir` | JWT |
| POST | `/push/desuscribir` | JWT |

Variables en `~/websites/.env-bi`: `VAPID_SUBJECT`, `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`.  
Dependencia Composer: `minishlink/web-push`. Script: `database/015_push_subscriptions.sql`.

**iPhone:** push solo tras *Añadir a Inicio* (Safari ≥ 16.4) y abrir la app instalada.  
Config front: [`frontend/js/config.js`](frontend/js/config.js) (`apiBase`, marca, soporte, fallback transferencia). Org: [`frontend/js/org-context.js`](frontend/js/org-context.js) desde partida/JWT.

CDN front: Bootstrap **5.3.8** + Icons 1.11; ciudad con jQuery + Select2 4.1 + `select2-bootstrap-5-theme`.  
*(Bootstrap 6 solo existe como alpha; no usar en producción hasta estable.)*

**Scripts BD recientes**

| Script | Qué hace |
|---|---|
| `database/016_cuentas_pago.sql` | Cuentas de transferencia por org |
| `database/017_partidas_gratis.sql` | Columna `partidas.gratis` + canal pago `gratis` |

Registro jugador:

```json
POST /auth/registro
{
  "organizacionId": 1,
  "ciudadId": 1,
  "nombre": "Ana",
  "apellido": "Pérez",
  "celular": "3001112233",
  "correo": "ana@mail.com",
  "password": "secreto123"
}
```

### Panel mesa — estado (oct 2026)

| Capacidad | API | UI |
|---|---|---|
| Crear partida + cartones + modalidades | `POST /partidas/crear` | Listo (`#/mesa/partidas`) |
| Marcar bingo gratis | `POST /partidas/marcar-gratis` | Listo (detalle partida) |
| Iniciar / finalizar | `POST /partidas/iniciar` · `finalizar` | Listo |
| Sacar balota + animación | `POST /balotas/sacar` | Listo (bombo + overlay xl) |
| Validar cantadas (revisión visual) | `POST /reclamos/aprobar` · `rechazar` | Listo (Offcanvas + cartón en vivo) |
| Dashboard métricas org | `GET /organizaciones/resumen` | Listo (`#/mesa`) |
| YouTube live | `POST /partidas/asignar-youtube` | Listo (detalle) |
| Venta / comprobantes | `/ventas/*` | Listo (`#/mesa/pagos`) |
| Cuentas transferencia CRUD | `/cuentas-pago/*` | Listo (pestaña en `#/mesa/pagos`) |
| Clientes / equipo CRUD | `GET·POST·PUT /usuarios/*` | Listo (`#/mesa/clientes`) — tabla + filtros + pág.; operador solo jugadores |
| Organizaciones CRUD | `GET·POST·PUT /organizaciones/*` | Listo (`#/mesa/organizaciones`) — admin; crear exige JWT admin |

**Pendiente técnico cercano**

- WhatsApp Cloud API: plantillas Meta (`compra_cartones`, OTP) — credenciales en `.env-bi` con `WHATSAPP_ENABLED=0` hasta aprobar plantillas
- SaaS: alta de organización + planes (más adelante)
- Centrifugo en VPS (SSE sigue siendo el driver activo)

Org en front: ya no va en `config.js`. Se toma de la **partida** (`#/comprar/{id}`) o del JWT (mesa). Si el front no la conoce, `/auth/*` y `/partidas/publicas` usan la **única organización activa** (con varias, hay que entrar por el enlace del bingo). `POST /ventas/gratis` y paginación de vitrina (`page` / `perPage`) listos. OTP admin Gmail listo (`GMAIL_*`).
