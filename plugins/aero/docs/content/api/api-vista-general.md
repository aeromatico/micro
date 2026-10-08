# API de Market — Vista general

Market expone una API pública para que integres tu propio sistema o el de un
tercero con tu sitio, sin darle acceso al panel. Toda la interacción pasa por
dos mecanismos, ambos controlados por vos desde **Configuración → API keys /
Webhooks**:

| | Dirección | Para qué | Dónde se configura |
|---|---|---|---|
| **API keys** | Vos llamás a Market (pull) | Leer o escribir datos bajo demanda: crear un pedido, consultar un contacto, enviar un mensaje | Configuración → API keys |
| **Webhooks** | Market te avisa a vos (push) | Reaccionar al instante a algo que pasó: un pago confirmado, un pedido nuevo, un ticket respondido — sin tener que preguntar todo el tiempo | Configuración → Webhooks |

Ambos son **de alcance acotado**: una key o un webhook solo ve o recibe lo que
vos explícitamente le diste permiso, y si tu cuenta es de un tenant, solo lo
que pertenece a tu propio sitio — nunca datos de otro cliente de la
plataforma.

## Dónde probar en vivo

**[/api](/api)** es el explorador público: lista todos los endpoints
disponibles (se actualiza solo cuando se instala un plugin nuevo), con el
permiso que necesita cada uno y un probador que llama al endpoint real desde
tu navegador, usando la key que pegues ahí.

## Guías

- [Autenticación, permisos y límites](/documentacion/api-autenticacion)
- [Webhooks: recibir eventos en tiempo real](/documentacion/api-webhooks)
