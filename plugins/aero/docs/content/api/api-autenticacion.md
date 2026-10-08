# Autenticación, permisos y límites

## Crear una key

Andá a **Configuración → API keys → Nueva API key**. Elegí un nombre
descriptivo, los permisos (scopes) que necesita y, opcionalmente, un
vencimiento. El valor completo de la key se muestra **una sola vez** al
crearla — copialo antes de salir de la pantalla. Si lo perdés, podés volver a
revelarlo desde la key (mientras no cambie la clave de cifrado del servidor)
o simplemente regenerarla.

## Cómo se usa

Mandá la key en la cabecera `Authorization` de cada pedido:

```bash
curl -H "Authorization: Bearer ak_xxxxxxxxxxxxxxxxxxxx" \
     https://panel.market.com.bo/api/v1/currencies
```

## Permisos (scopes)

Cada endpoint exige un permiso puntual, con la forma `área.recurso.acción`
(por ejemplo `hello.messages.send`, `shop.orders.write`). Una key solo puede
llamar a los endpoints cuyo permiso tenga concedido — si le falta, la
respuesta es `403 insufficient_scope`. La lista completa de permisos
disponibles (crece con cada plugin instalado) se ve al crear la key y en
**[/api](/api)**.

Dá siempre el mínimo necesario: si la integración solo necesita leer pedidos,
no le des el permiso de crearlos.

## Aislamiento por tenant

Si tu cuenta administra un sitio (tenant), **todas** tus keys quedan atadas a
ese sitio automáticamente — nunca vas a ver ni vas a poder generar una key con
alcance de toda la plataforma. Los endpoints que devuelven datos propios de tu
negocio (pedidos, contactos, mensajes) los filtran siempre por esa
pertenencia: la key solo ve lo tuyo, sin importar qué permiso tenga.

## Límite de pedidos por minuto

Cada key tiene su propio límite (`rate_limit_per_minute`, configurable al
crearla, 60 por defecto). Superarlo devuelve `429 rate_limited` — no afecta a
tus otras keys.

## Errores

| Código | `error` | Motivo |
|---|---|---|
| 401 | `unauthenticated` | Falta la cabecera `Authorization` o la key no existe |
| 401 | `key_expired` / `key_revoked` | La key venció o fue revocada |
| 403 | `insufficient_scope` | La key no tiene el permiso que exige ese endpoint |
| 422 | `validation_failed` | El cuerpo del pedido no pasa validación (ver `details`) |
| 429 | `rate_limited` | Se superó el límite por minuto de esa key |

## Revocar o regenerar

Desde el detalle de la key: **Revocar** la desactiva de forma permanente (el
historial de uso queda, para auditoría); **Regenerar** emite un valor nuevo e
invalida el anterior en el acto — usalo si sospechás que se filtró.
