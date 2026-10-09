---
title: Ventas por WhatsApp (tienda)
sort: 30
---
# Ventas por WhatsApp (tienda)

**Dónde:** panel de la conversación en la app de chat → Tienda
**Requiere:** la **tienda activada** en tu espacio (Shop) y la función PRO de Chat.

El agente arma un pedido con productos de tu catálogo y se crea un **pedido real**, el mismo que generaría la
tienda web, con descuento de stock. El pedido y la forma de pagarlo se envían al cliente por la misma conversación.

## Qué puede hacer el agente

| Acción | Detalle |
|--------|---------|
| **Buscar productos** | Búsqueda en el catálogo del espacio, con paginación. |
| **Enviar tarjeta de producto** | Foto (si la cuenta admite imágenes), nombre, precio («Desde» si tiene variantes), «Agotado» si no hay stock, resumen y enlace a la tienda. |
| **Crear pedido** | De 1 a 50 líneas (producto, variante opcional, cantidad de 1 a 999). |
| **Reenviar pedido** | Vuelve a mandar el detalle y el pago al cliente. |
| **Cancelar pedido** | Cancela con el motivo «Cancelado desde el chat». |
| **Compartir ubicaciones** | Disponibles también cuando no hay pedido. |

## Datos del pedido

| Campo | Para qué sirve |
|-------|----------------|
| **Cliente** (nombre, teléfono, correo) | Se toman del CRM o del contacto del chat; el agente puede corregirlos. Sin nombre se usa «Cliente». |
| **Medio de pago** | Una de las pasarelas activas de la tienda. |
| **Envío** | Dirección, ciudad, país (2 letras), coordenadas y referencia del lugar. |
| **Notas del cliente** | Hasta 1000 caracteres. |
| **Avisar al cliente al pagar** | Sí por defecto: agradece cuando se confirma el pago. |

## Qué recibe el cliente

Un mensaje con el **número de orden**, las líneas (cantidad, producto, variante y subtotal), el envío si lo hay y el **total**.
Si el medio de pago genera un QR de Pay, se adjunta la imagen del QR; si no, se envían las **instrucciones de pago** de la pasarela.
Termina con el enlace para revisar el detalle y el lugar de destino.

Cuando el pago se confirma, el hilo registra «Pedido … pagado» y, si lo pediste, el cliente recibe un agradecimiento.

> [!NOTE]
> Si el pedido se crea pero el envío al cliente falla (por ejemplo, sin créditos), el pedido **no se pierde**: queda
> en la lista y puedes usar «Reenviar».

> [!WARNING]
> Si no hay stock suficiente, el pedido no se crea y la app lo indica. Cancelar solo es posible mientras el estado
> del pedido lo permita.

**Versión documentada:** 1.2.0
