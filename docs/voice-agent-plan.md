# Plan — Llamadas de WhatsApp con agente de IA (`aero/hello` fase 9)

> Creado: 2026-08-24 | Estado: **fase 9.1 implementada**, 9.2–9.4 pendientes

## Resumen

Zernio **ya expone la WhatsApp Business Calling API completa**, incluyendo forwarding
a un agente de IA por WebSocket o SIP. No hay que integrar Meta directamente ni montar
un SIP trunk propio: `aero/hello` ya habla con Zernio, así que esto es una extensión
del plugin más un microservicio de audio fuera del stack PHP.

Hallazgo clave: `POST /v1/phone-numbers/{id}/whatsapp/calling` acepta
`forwardTo: "wss://..."`. Zernio configura `calling.status=ENABLED` en Meta contra su
endpoint SIP de Telnyx, guarda la password SIP que emite Meta, y reenvía el audio al
destino que le indiquemos. El agente de IA vive en ese `wss://`.

## Estado previo

- `wapi` (whatsapp-web.js) fue **eliminado del proyecto el 2026-08-24**. No sirve para
  voz: solo expone `onIncomingCall` y `Call.reject()`, sin acceso al stream de audio.
- El canal `whatsapp` de `aero/sites` ya enruta por `aero/hello`.
- `aero/hello` cubre hoy solo texto: `posts`, `profiles`, `accounts`, `inbox`, `sms`,
  `media`, `analytics`. No hay recurso de voz.

## Arquitectura

```
Usuario WhatsApp
      │  (llamada in-app, sin línea PSTN)
      ▼
Meta WhatsApp Business Calling  ──SIP/TLS:5061──▶  Zernio (Telnyx)
                                                        │ forwardTo: wss://
                                                        ▼
                                   voice-agent  (Node, servidor propio)
                                   STT ──▶ LLM (Claude) ──▶ TTS
                                                        │
                                                        ▼
                                   October / aero/hello  (tools, transcripción, métricas)
```

October **nunca** está en el camino del audio. Solo config del tenant, prompt del
agente, transcripciones, métricas y facturación.

## Endpoints de Zernio (verificados contra docs.zernio.com, 2026-08-24)

| Acción | Endpoint |
|---|---|
| Habilitar calling en un número | `POST /v1/phone-numbers/{id}/whatsapp/calling` |
| Leer config de calling | `GET /v1/phone-numbers/{id}/whatsapp/calling` |
| Actualizar config | `PATCH /v1/phone-numbers/{id}/whatsapp/calling` |
| Deshabilitar | `DELETE /v1/phone-numbers/{id}/whatsapp/calling` |
| Consultar permiso de llamada | `GET /v1/whatsapp/call-permissions?accountId=&to=` |
| Llamada saliente / pedir consentimiento | `POST /v1/whatsapp/calls` |
| Historial | `GET /v1/whatsapp/calls`, `GET /v1/whatsapp/calls/{id}` |
| Grabación (MP3, URL firmada ~10 min) | `GET /v1/whatsapp/calls/{id}/recording` |
| Estimación de costo por minuto | `GET /v1/whatsapp/calls/estimate` |

Webhooks: `call.received`, `call.ended`, `call.failed`, `call.permission_request`,
`call.billing`.

Campos relevantes de `POST .../whatsapp/calling`:

- `forwardTo` — `tel:+E164`, `sip:...` o `wss://...`
- `forwardCallerId: caller` — presenta el número del usuario de WhatsApp al destino.
  **Necesario para agentes de IA**: muchos trunks rechazan ver al número del negocio
  llamándose a sí mismo. Solo aplica a destinos `sip:`.
- `maxCallDurationSeconds` — corta ambas patas de la llamada. Ponerlo siempre; es la
  válvula de seguridad contra facturación por dead-air si el agente cuelga y se pierde
  la señal.
- `recordingEnabled` — off por defecto; encenderlo tiene implicancias legales de
  consentimiento.

## Bloqueantes reales (verificado en docs.zernio.com, 2026-08-24)

**El gate duro es de facturación, no de calentamiento.** Calling está disponible
solo en el **plan Usage** de Zernio, o sea que hay que activar usage-based billing
en la cuenta. Eso es un switch, no semanas de trabajo.

**Las llamadas entrantes funcionan en cualquier tier.** Esto es lo que desbloquea el
proyecto: un recepcionista de IA que atiende llamadas entrantes se puede lanzar sin
calentar nada. El tier solo restringe las salientes.

**Salientes (business-initiated):**

- Meta bloquea salientes en números registrados en **US, Canadá, Egipto, Vietnam y
  Nigeria**. Bolivia **no** está en esa lista. Las entrantes funcionan igual en los
  países bloqueados.
- Fuera de esos países, la guía de producción de Meta pide el tier de **2.000
  destinatarios diarios (`TIER_2K`)**. Por debajo, las salientes *pueden* ser
  rate-limited por Meta — limitadas, no bloqueadas.
- Consentimiento: hay que tener `permission.status` en `temporary` o `permanent`. Si
  no, mandar `action: "send_call_permission_request"` — Meta lo limita a **1 prompt
  por consumidor cada 24h (2 por 7 días)** y exige ventana de servicio de 24h
  abierta. Esto condiciona todo el diseño de campañas salientes.

**Otros errores esperables al habilitar:** `409` si el número ya está atado a un SIP
trunk (desatarlo primero).

### Cómo se calienta el número

El tier lo sube Meta solo, mirando volumen y calidad. Para pasar de `TIER_250` a
`TIER_1K`: **1.000 mensajes de plantilla entregados a números únicos, fuera de la
ventana de atención, en una ventana móvil de 30 días**, con las plantillas en buena
calidad. Al completar el tramo, Meta analiza la calidad y aprueba o deniega el salto
automático. De ahí se sigue escalando hasta `TIER_10K`/`TIER_100K`.

El `qualityRating` (GREEN/YELLOW/RED) se calcula sobre los **últimos 7 días** de
feedback: bloqueos y reportes de usuarios. Si cae, el tier baja en vez de subir — así
que calentar mandando mensajes no pedidos es contraproducente.

Ambos valores se leen en `GET /v1/accounts` bajo `metadata.qualityRating` y
`metadata.messagingLimitTier`, así que el progreso del calentamiento se puede
monitorear desde el backend.

**Camino práctico:** usar el tráfico transaccional real de los plugins que ya existen
(confirmaciones de pedido de `aero/shop`, avisos de `aero/qrbo`, notificaciones de
contacto de `aero/sites`) como calentamiento natural. Son mensajes que el usuario
pidió, así que suman volumen sin arriesgar el quality rating.

## Fases

### Fase 9.1 — Recurso de voz en `aero/hello` ✅ hecho (v1.0.7, 2026-08-24)

- `classes/zernio/resources/WhatsappCallingResource.php` — los 11 endpoints,
  registrado en el facade como `app(Zernio::class)->calling()`. Requirió agregar
  `patch()` a `ZernioClient` (no existía).
- Modelo `Call` + tabla `aero_hello_calls`, con `conversation_id` nullable (una
  llamada de un número desconocido llega sin conversación enlazada) y los tres
  costos separados — Meta y Zernio son facturas distintas y no se suman.
- `ProcessWebhookEventJob` maneja `call.received|ended|failed|permission_request`.
  Upsert sobre `zernio_call_id`: el orden de llegada no importa y una redelivery no
  duplica. Eventos emitidos: `aero.hello.callUpdated`, `aero.hello.callMissed`,
  `aero.hello.callPermissionResponse`.
- Backend: bandeja "Llamadas" con permiso `aero.hello.manage_calls`, detalle con
  transcripción y botón para repedir la grabación (la URL firmada expira a los
  ~10 min, así que no sirve guardarla).

Probado con payloads reales de la spec: ring→ended con `no_answer` dispara
`callMissed`, redelivery no duplica, `call.failed` guarda el error, permiso emite
el evento, y una cuenta desconocida no crea filas. Las rutas del backend responden
302 a login, no 500.

**Sin verificar contra la API real**: no hay número con calling habilitado todavía,
así que `WhatsappCallingResource` nunca hizo una llamada HTTP de verdad.

### Fase 9.2 — Microservicio `voice-agent`

Fuera del stack PHP, en el mismo VPS. Node + `ws`.

- Acepta la conexión `wss://` de Zernio; el audio va en frames PCM/Opus.
- Pipeline en streaming (nada de request/response por turno):
  STT (Deepgram/Whisper streaming) → Claude (streaming) → TTS (ElevenLabs streaming).
- Presupuesto de latencia: **<800 ms boca-a-boca**. Cualquier paso que bufferice el
  turno completo lo revienta.
- Barge-in: cortar el TTS apenas el STT detecta voz del usuario.
- Al colgar: POST a October con transcripción, duración y costo.

**Atajo recomendado para el MVP:** en vez de construir el pipeline, apuntar `forwardTo`
a Vapi o Retell (ambos aceptan WebSocket/SIP) y quedarse solo con la fase 9.1 + 9.3.
Se pierde control fino y margen, se gana un MVP en días en vez de semanas. La decisión
de construir el pipeline propio se puede tomar después con datos de uso reales.

### Fase 9.3 — Configuración por tenant

- Modelo `VoiceAgent` (`aero_hello_voice_agents`): `tenant_id`, `name`, `system_prompt`,
  `voice_id`, `language`, `max_duration_seconds`, `tools` (jsonable), `is_enabled`.
- CRUD backend bajo el menú Hello.
- **Tools del agente** — es donde esto deja de ser un juguete: consultar pedido en
  `aero/shop`, ver menú/sucursal en `aero/qrbo`, crear lead en `aero/crm`, agendar,
  escalar a humano. Exponerlas como endpoints internos autenticados que el
  `voice-agent` llama durante la conversación.

### Fase 9.4 — Facturación

Mismo patrón de tramos que `qrbo` y `masterads`, pero por minuto.

Dos billers separados, esto hay que modelarlo bien desde el principio:
- **Meta factura su fee por minuto directo a la WABA del cliente.** No pasa por Zernio
  ni por nosotros.
- **Zernio factura la conexión de carrier at-cost, sin markup.**
- **El proveedor de IA factura aparte.**

El costo total ronda **$0.05–0.15/min** sumando STT+LLM+TTS+carrier. `GET
/v1/whatsapp/calls/estimate` da el número exacto por destino antes de marcar — usarlo
para gatear rutas caras y para mostrar precio al tenant.

## Orden de trabajo sugerido

1. ~~Fase 9.1~~ ✅ hecha.
2. Activar el plan Usage en Zernio y provisionar el número. Es el único gate duro.
3. **Lanzar con entrantes**: habilitar calling con `forwardTo` a un agente de Vapi o
   Retell. No requiere calentar el número.
4. Fase 9.3 (tools contra `shop`/`qrbo`/`crm`) — acá está el valor diferencial real.
5. Calentar el número en segundo plano con el tráfico transaccional que ya existe,
   monitoreando `messagingLimitTier`. Recién al llegar a `TIER_2K` habilitar salientes.
6. Evaluar pipeline propio de audio solo si el volumen justifica el margen.
