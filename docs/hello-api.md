# API de WhatsApp Business (Hello) para las apps del tenant

Cada tenant puede construir sus propias apps sobre su WhatsApp: **enviar y leer** con la API REST y **recibir** mensajes entrantes y estados por **webhook**.

Base: `https://<tu-dominio>/api/v1/hello` · Formato JSON · Respuestas `{ "data": ... }`, errores `{ "error": "codigo", "message": "..." }`.

## Autenticación
1. En **Configuración → API keys** crea una key con los permisos de Hello (o `hello.*`).
2. Envía `Authorization: Bearer ak_...` en cada llamada.
3. La key queda atada a tu tenant: solo ves y usas tus cuentas, contactos y conversaciones.

| Permiso | Para qué |
|---|---|
| `hello.messages.send` / `.read` | Enviar / consultar mensajes |
| `hello.conversations.read` / `.reply` | Leer conversaciones / responder |
| `hello.contacts.read` / `.write` | Leer / crear contactos |
| `hello.accounts.read` | Listar tus cuentas y sus capacidades |
| `hello.calls.*`, `hello.posts.write` | Llamadas y publicaciones (Cloud API / Zernio) |

Errores comunes: `401 unauthenticated|key_revoked|key_expired`, `403 insufficient_scope`, `429 rate_limited` (tope por minuto de la key), `402 insufficient_credits`, `422 validation_failed|unsupported|account_not_found`.

## Endpoints
| Método | Ruta | Descripción |
|---|---|---|
| GET | `/accounts` | Tus cuentas, con `capabilities` (`text, media, location, contact, poll, templates, window_24h, calls, posts`) |
| POST | `/messages` | Enviar un mensaje (202, asíncrono) |
| GET | `/messages/{id}` | Estado de un mensaje |
| GET | `/conversations` · `/conversations/{id}/messages` | Bandeja e historial |
| POST | `/conversations/{id}/reply` | Responder en una conversación |
| GET/POST | `/contacts` · GET `/contacts/{id}` | Contactos |

### Enviar un mensaje
`POST /messages`

| Campo | Notas |
|---|---|
| `to` | Número con código de país (`59171234567`); 8 dígitos bolivianos → 591 |
| `account_id` | Opcional. Si falta se usa tu canal por defecto (Configuración → WhatsApp) |
| `type` | `text` (defecto), `location`, `contact`, `poll` |
| `body`, `media_url` | Para `text`. `media_url` es una URL pública; con adjunto, `body` es el pie (máx. 1024) |
| `latitude`, `longitude`, `location_name` | `location` |
| `contact_name`, `contact_phone` | `contact`: tarjeta de contacto |
| `poll_name`, `poll_options` (2–12), `poll_multiple` | `poll` |

`location`, `contact` y `poll` solo funcionan con cuentas de **WhatsApp Web** (`capabilities`); con Cloud API responde `422 unsupported`. Cloud API además exige plantilla fuera de la ventana de 24 h.

```bash
curl -X POST https://micro.clouds.com.bo/api/v1/hello/messages \
  -H "Authorization: Bearer ak_..." -H "Content-Type: application/json" \
  -d '{"to":"59171234567","type":"poll","poll_name":"¿Te sirve el jueves?","poll_options":["Sí","No"]}'
```
Respuesta `202`: `{ "data": { "id": 812, "status": "queued", ... } }`. Sigue el estado por `GET /messages/812` o, mejor, por webhook.

### Créditos
Cada mensaje enviado por API descuenta **1 crédito naranja** (acción `hello.message_send`, el costo lo fija el superadmin). Sin saldo: `402 insufficient_credits`. Si el mensaje termina **fallido** se reembolsa solo. Los envíos desde el backend (Redactar, Bandeja) no consumen.

## Webhooks (recibir en tu app)
Configuración → **WhatsApp** → *Tu app: API y webhooks*: activa el webhook, pon la URL (**https** y servidor público; se rechazan IPs privadas) y elige los eventos. Ahí mismo ves el **secreto de firma**, un botón de **evento de prueba** y las últimas entregas.

### Eventos
- `message.received`: un contacto te escribió.
- `message.status`: un mensaje que enviaste pasó a `sent`, `delivered`, `read` o `failed` (con `previous_status`).
- `ping`: solo el botón de prueba.

```json
{
  "id": "5d1f0b0e-…",            // id único de la entrega
  "event": "message.received",
  "created_at": "2026-09-19T20:31:04-04:00",
  "tenant_id": 16,
  "data": {
    "message": { "id": 812, "direction": "inbound", "type": "text", "body": "Hola", "media_url": null, "status": "delivered", "external_id": "3EB0…", "created_at": "…" },
    "contact": { "id": 44, "name": "Bolivia Host", "identities": [{ "platform": "whatsapp", "external_id": "59175669697" }] },
    "account": { "id": 27, "label": "Llajwa", "phone_number": "59177636675" }
  }
}
```
Los adjuntos entrantes llegan como `media_url` pública.

### Cabeceras y firma
`X-Hello-Event`, `X-Hello-Delivery` (uuid), `X-Hello-Timestamp` y `X-Hello-Signature: t=<timestamp>,v1=<hmac>`, donde `hmac = HMAC_SHA256(secreto, "<timestamp>.<cuerpo crudo>")` en hexadecimal. **Verifícala** y rechaza timestamps de más de 5 minutos.

```php
$body = file_get_contents('php://input');
[$t, $v1] = array_map(fn ($p) => explode('=', $p, 2)[1], explode(',', $_SERVER['HTTP_X_HELLO_SIGNATURE']));
$ok = hash_equals(hash_hmac('sha256', "$t.$body", $secret), $v1) && abs(time() - (int) $t) < 300;
```
```js
const crypto = require('crypto');
const [t, v1] = req.get('X-Hello-Signature').split(',').map(p => p.split('=')[1]);
const ok = crypto.timingSafeEqual(Buffer.from(crypto.createHmac('sha256', secret).update(`${t}.${req.rawBody}`).digest('hex')), Buffer.from(v1))
  && Math.abs(Date.now() / 1000 - Number(t)) < 300;
```

### Entrega y reintentos
Responde con cualquier **2xx en menos de 8 s** (procesa en segundo plano). Si falla, se reintenta 5 veces (10 s, 1 min, 5 min, 15 min, 1 h). Tras **15 entregas fallidas seguidas** el webhook se desactiva solo y se muestra el motivo en pantalla; corrige el endpoint y vuelve a activarlo. Puede llegar el mismo evento más de una vez: usa `X-Hello-Delivery` para deduplicar. No se siguen redirecciones.

## Notas de diseño
- El evento sale de los ganchos de `Message` (`afterCreate`/`afterUpdate`), así que cubre cualquier origen (API, Redactar, bots, acuses de Zernio o wapi).
- `TenantWebhooks::validateUrl()` resuelve el dominio y fija esa IP en la llamada (`CURLOPT_RESOLVE`) para evitar SSRF y DNS rebinding.
- Tablas: `aero_hello_tenant_settings` (webhook_*, secreto cifrado), `aero_hello_webhook_deliveries` (registro), `aero_hello_messages.credit_transaction_id`.
