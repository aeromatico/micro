# Webhooks: recibir eventos en tiempo real

En vez de consultar la API todo el tiempo para saber si algo cambió, un
webhook hace que Market te avise apenas ocurre: un pago confirmado, un pedido
nuevo, un ticket respondido. Se configuran en **Configuración → Webhooks**.

## Crear una suscripción

1. **URL de destino**: tu endpoint, debe aceptar `POST` con cuerpo JSON y
   responder `2xx` rápido (procesá en segundo plano si tu lógica tarda —
   Market no espera más de 10 segundos).
2. **Eventos**: uno o más patrones, por ejemplo `pay.paymentReceived` o
   `shop.order.*` (el comodín de sufijo cubre cualquier evento que empiece
   así — `shop.order.created`, `shop.order.updated`, etc).
3. Guardá — el **secreto de firma** se muestra en la pantalla siguiente.
   Guardalo vos también: lo necesitás para verificar cada entrega.

## Catálogo de eventos

Crece con cada plugin instalado — los que ya existen hoy:

| Evento | Cuándo se dispara |
|---|---|
| `pay.paymentReceived` | Un pago QR se confirmó (banco o conciliación manual) |
| `crm.support.ticketCreated` | Se creó un ticket de soporte |
| `crm.support.ticketReplied` | Alguien respondió un ticket |
| `sms.messageStatusChanged` | Cambió el estado de entrega de un SMS |
| `tracking.job.created` / `tracking.job.assigned` / `tracking.job.status_changed` | Ciclo de vida de un trabajo de flota |
| `tracking.asset.moved` | Un activo rastreado cambió de posición |
| `sites.tenant.purging` | Tu sitio va a borrarse (dado de baja) |

Si tu tenant tiene endpoints de tienda, mensajería u otro plugin con eventos
propios, aparecen acá con el tiempo — usá el patrón `plugin.*` si querés
recibir todos los de un área sin listarlos uno por uno.

## Verificar una entrega

Cada `POST` llega con tres cabeceras:

| Cabecera | Contenido |
|---|---|
| `X-Aero-Event` | El evento que matcheó, ej. `pay.paymentReceived` |
| `X-Aero-Signature` | `sha256=<firma>` — HMAC-SHA256 del cuerpo crudo con tu secreto |
| `X-Aero-Delivery` | Id único de este intento — usalo para no procesar un reintento dos veces |

Ejemplo en PHP:

```php
$body = file_get_contents('php://input');
$expected = 'sha256=' . hash_hmac('sha256', $body, $miSecreto);

if (!hash_equals($expected, $_SERVER['HTTP_X_AERO_SIGNATURE'] ?? '')) {
    http_response_code(401);
    exit;
}

$payload = json_decode($body, true);
// $payload['event'], $payload['sent_at'], $payload['data']
```

## Reintentos

Si tu endpoint no responde `2xx` (o no responde), Market reintenta hasta 6
veces con espera creciente (15s, 1m, 5m, 15m, 30m, 1h). Una suscripción que
falla 10 veces seguidas se **desactiva sola** — la reactivás con un clic desde
su pantalla una vez que el problema esté resuelto.

## Probar sin esperar un evento real

El botón **Enviar ping de prueba** en el detalle de la suscripción manda un
`test.ping` directo a tu URL, sin necesidad de que ocurra el evento real —
útil para verificar la firma antes de integrarlo del todo.
