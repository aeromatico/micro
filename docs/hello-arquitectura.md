# Cómo funciona Hello hoy (multi-driver: Zernio + wapi)

Actualizado: 2026-09-19

## Idea central
Hello es el centro de mensajes. Cada **cuenta** (`Account`) tiene un `driver`: `zernio` o `wapi`.
El resto del sistema (bandeja, envío, notificaciones, CRM) no sabe qué driver hay debajo.

## Envío
1. Algo genera un mensaje: un usuario, una notificación o un recordatorio de cobranza.
2. `MessageComposer` lo arma y `SendMessageJob` lo encola.
3. `MessageDispatcher` busca el driver de la cuenta. Los drivers se registran por el hook
   `aero.hello.registerChannelDrivers` y todos implementan `ChannelDriverInterface`.
4. El driver envía y devuelve el id del mensaje.

| | Zernio | wapi (WhatsApp Web) |
|---|---|---|
| Transporte | API de Zernio, con WhatsApp Cloud API | Baileys, protocolo directo sin navegador |
| Mensaje nuevo a un contacto | Solo con plantilla o `category=utility` | Libre, sin ventana ni plantillas |
| Respuesta libre | Solo si el último **entrante** tiene menos de 24 h | Siempre |
| Acuses | Por webhook de Zernio | Enviado, entregado y leído, con id real de WhatsApp |
| Riesgo | Oficial | No oficial, el mismo que cualquier vía por QR |

Notas sobre Zernio:
- `/v1/sms/messages` es solo SMS.
- Un envío libre fuera de la ventana de 24 h devuelve un wamid falso que Meta rechaza después
  (error 131047). Por eso el driver comprueba la ventana antes de enviar y, si no hay, lanza
  un error claro.

## Redactar (envío puntual)
- **Ruta:** WhatsApp Business → **Redactar** (`backend/aero/hello/compose`), justo antes de Bandeja. Permiso `manage_conversations`.
- Editor con barra de formato (`*negrita*`, `_cursiva_`, `~tachado~`, `` `código` ``, bloque ```` ``` ````, listas `- ` y `1. `, cita `> `), leyenda, vista previa y contador con tope de 4096 caracteres (límite de WhatsApp; el servidor lo revalida).
- Destinatarios: números manuales (chips, pegar varios; 8 dígitos bolivianos → 591), listas y contactos de Aero.Crm (con enlace para gestionarlos en el CRM). Se unen y deduplican; máx. 300 por envío.
- **Adjuntos:** un archivo por envío (imagen JPG/PNG/WebP ≤5 MB, video MP4 y audio ≤16 MB, documento ≤100 MB). Se guarda como archivo público de October (`System\Models\File`) y todos los destinatarios comparten la misma URL. Con adjunto el texto es el pie y se limita a 1024 caracteres. `media_type`: `image|video|audio|document` (Zernio recibe `document` como `file`).
- **Ubicación, contacto y encuesta** (solo WhatsApp Web; capacidades `location`, `contact`, `poll`): selector de tipo sobre el editor. Ubicación acepta `lat, lng` o un enlace de Google Maps; contacto envía una tarjeta; encuesta admite 2–12 opciones únicas y respuesta múltiple opcional. Los votos no se registran en Hello todavía. Viajan por `messages.provider_payload` (`message_type`). Botones y listas interactivas no se soportan a propósito. Cloud API/Zernio los rechaza con un mensaje claro.
- Sale por la cuenta elegida (preseleccionada según el canal por defecto del tenant), escalonado 2–6 s entre mensajes. Un número ya conocido reusa su conversación (con o sin "+").
- Con Cloud API solo llega a quien escribió en las últimas 24 h (aviso en pantalla); para plantillas, Campañas.

## Recepción y estado
- **Zernio:** su webhook llega a Hello.
- **wapi:** el webhook `POST api/v1/wapi/webhooks/{instanceId}` va firmado con HMAC, con un
  secreto compartido. El plugin lo traduce a los eventos de Hello:
  - `message` → `message.received`
  - `message_ack` → `message.status`
  - `disconnected` y `authenticated` → `account.status`
- `ProcessWebhookEventJob` los procesa.
- El acuse puede llegar antes que el mensaje. Se reintenta a los 3 s, con tope de 3 intentos
  porque el worker corre con `--tries=3`.
- `zernio_message_id` es único. Dos cuentas del mismo Hello que se escriben entre sí no ven el
  entrante del otro lado. Solo pasa en pruebas internas.
- Los adjuntos entrantes de wapi se descargan y se guardan como archivo público de October.

## Contrato de driver (Zernio, wapi, futuros como Telegram)
- `ChannelDriverInterface`: `sendMessage`, `parseWebhook` y `capabilities()` (`text, media, templates, window_24h, calls, posts`).
- Nadie debe preguntar "¿es Zernio?": usar `$account->can('calls')` etc. Llamadas y publicaciones devuelven 422 en cuentas sin la capacidad; responder desde Conversaciones valida la ventana de 24 h antes de encolar.
- **Tenant:** `Account::forTenant($id)` y `$account->effective_tenant_id` cubren tenant directo (wapi) y vía perfil (Zernio). Nunca usar `whereHas('profile')` ni `profile?->tenant_id`.
- **Plantillas:** `Template.provider_template_name/provider_language` solo se usan si el driver tiene `templates`; viajan por `messages.provider_payload`. wapi manda el cuerpo tal cual.
- `zernio_*_id` son columnas históricas con ids de cualquier driver (`Message::external_id`); el id de mensaje es único por cuenta.
- Rate limit: cualquier excepción que implemente `RateLimitedInterface` reintenta sin marcar el mensaje como fallido.

## Canal por defecto (tenant)
- **Ruta:** Configuración → **WhatsApp** (`backend/aero/hello/channelsettings`), permiso `aero.hello.manage_settings` (rol `tenant_admin`, migración 1.38 de aero/sites).
- El tenant elige **WhatsApp Web** (wapi) o **WhatsApp Cloud API** (Zernio). Se guarda en `aero_hello_tenant_settings.default_whatsapp_driver`.
- `Hello::resolveAccount()` (notify, API, CRM, cobranzas) prioriza ese driver; si no tiene cuenta de él, usa la que haya. Un `account_id` explícito (bots, respuestas en una conversación) manda siempre.

## API y webhooks para las apps del tenant
Los tenants crean sus propias apps con las API keys de Configuración → API keys (permisos `hello.*`), reciben mensajes y estados por webhook firmado y pagan 1 crédito naranja por mensaje enviado por API. Referencia completa para desarrolladores: `docs/hello-api.md`. Código: `Classes/Webhooks/TenantWebhooks.php`, `Jobs/DeliverTenantWebhookJob.php`, `Classes/ApiCredits.php`, `Classes/StructuredMessage.php` (compartida con Redactar).

## Contactos sincronizados con el CRM
- Enlace: `aero_crm_contacts.hello_contact_id`. Solo tenants con el CRM activado (los contactos de Hello sin tenant quedan fuera).
- **Hello → CRM:** al crearse una identidad de WhatsApp (mensaje entrante, envío desde Redactar o la API) el contacto se crea en el CRM (`source = whatsapp`) o se enlaza al que ya tenga ese teléfono. Lo hace `Aero\Crm\Classes\HelloSync` desde eventos de modelo, así Hello sigue sin depender del CRM.
- **CRM → Hello:** `Contact::syncHelloContact()`; si el número ya chateó, enlaza ese contacto (con su historial) en vez de duplicarlo.
- **Nombres:** wapi envía el nombre de perfil de WhatsApp (`pushName`). Solo rellena contactos con nombre "marcador" (vacío, un número o "Contacto #n"); un nombre real nunca se pisa. Después, editar el nombre en cualquiera de los dos lados lo copia al otro ("Ana María Pérez" ↔ nombre "Ana" + apellido "María Pérez"). Guardia `HelloSync::quietly()` contra bucles.
- **Teléfono:** formato único `Aero\Hello\Classes\PhoneNumber::normalize()` (solo dígitos, 8 dígitos bolivianos → 591). El CRM lo muestra con "+".
- **Límites:** ContactIdentity es única por (plataforma, número) global, así que un mismo número no puede estar en dos tenants. Si el CRM y el chat tienen dos contactos con historial para el mismo número no se fusionan (queda en el log). Zernio aún no aporta nombre de perfil.
- **Cliente de tienda:** un contacto nuevo (o con email/teléfono cambiado) se enlaza solo al `Aero\Shop\Models\Customer` del mismo tenant que coincida (email, o teléfono normalizado) y, si no tenía nombre real, toma el del cliente. Los selectores de relación del CRM (Empresa, Responsable, Miembros de equipo, Cliente de tienda, Listas, Etapa, Equipo…) tienen opción vacía y solo listan registros del tenant; "Responsable" usa `Aero\Crm\Classes\TenantUsers` (admin primario + TenantUser).
- **Reparación / pasada inicial:** `php artisan crm:sync-hello [--tenant=ID] [--dry-run]` (idempotente).
- Tras cambiar código de jobs hay que `php artisan queue:restart`: el worker mantiene el código viejo en memoria.

## Conectar una sesión (tenant)
- **Ruta:** Mensajería → **Conectar** (`backend/aero/hello/connect`).
- **WhatsApp Web:** QR o código de 8 dígitos.
  - Se crea la instancia en wapi, se registra el webhook y se da de alta la cuenta.
  - `Account.zernio_account_id` guarda el id de la instancia de wapi.
  - La pantalla avisa cuando el QR o el código caduca sin vincularse.
- **Cloud API:** alta manual, con instrucciones sobre verificación de Meta, admin y permisos
  de partner.
- **Menús:** "Redes Sociales" (Campañas, Publicaciones) es del tenant; "Cuentas conectadas" y Perfiles solo del superadmin.
- **Permisos:** el rol `tenant_admin` tiene `aero.hello.manage_accounts`, otorgado por la
  migración 1.36 de aero/sites. Perfiles sigue siendo solo del superadmin.

## Aislamiento
- `Account.tenant_id` va directo, y el controlador de cuentas usa `ScopesToOwner`. Cada tenant
  ve solo lo suyo.
- Hay una key de wapi de plataforma en Configuración → wapi. Un perfil puede tener la suya
  (`wapi_api_key`); hoy ninguno la usa.

## wapi por debajo
- Servicio Docker con API, worker, Postgres y Redis en `/www/wwwroot/wapi.clouds.com.bo/server`.
  La sesión se restablece sola tras un reinicio (unos 3 s, sin QR).
- **Chats:**
  - Guarda en su base los recibidos, los enviados por API y los enviados desde el celular.
  - También guarda el historial que WhatsApp entrega una sola vez al vincular.
  - Retención de 30 días por defecto y purga horaria.
  - Multimedia opcional, desactivada por defecto, tope de 25 MB por archivo.
  - Ambos ajustes los edita el superadmin en Configuración → wapi.
- **Respaldo:** diario a las 03:30 en `/www/backup/wapi` (base de datos y credenciales, 14 días
  de retención, restauración probada). Detalles en `server/BACKUP.md` del repo de wapi.

## Estado
- Host (59175669697) y Llajwa (59177636675) conectados en wapi. Zernio operativo.
- Pendiente opcional: revincular Host para importar sus últimos 30 días de historial.
- Pendiente de sites: el trabajo de Layout, controladores y Puck sigue sin commitear.
- Pendiente de seguridad: el puerto 3001 de wapi está abierto en `0.0.0.0`.
