---
title: Comprar por chat
sort: 27
---
# Comprar por chat: buscar, ver producto, agregar al pedido y confirmar

Con cinco nodos más, tu cliente puede **comprar sin salir del chat**: busca un producto, lo agrega a su pedido y lo confirma. Se suman a [los nodos de la tienda](workflows-nodos-tienda) (menú de categorías y productos). Requieren la **Tienda** activada y solo usan el catálogo de tu cuenta.

## Cómo recuerdan al cliente

Cada cliente (por su teléfono) tiene **su propio pedido** y la tienda recuerda **la última lista que se le mostró**: categorías, productos u opciones. Por eso un «2» significa lo que corresponde en ese momento, aunque llegue en otra conversación:

| Lo último que vio | Su «2» significa |
|-------------------|------------------|
| El menú de categorías | La categoría 2 |
| Una lista de productos (de una categoría o de una búsqueda) | El producto 2 |
| Las opciones de un producto (talla, color…) | La opción 2 |
| La tarjeta de un producto | `1` agrega ese producto |

Al mostrar una tarjeta, «1» agrega ese producto (o su opción, si tiene variantes). Pasadas **24 horas sin actividad**, el pedido y la lista se olvidan.

> [!IMPORTANT]
> Para identificar al cliente, el flujo debe empezar con un **mensaje entrante** (o indicar un teléfono en el campo *Cliente*). Si no hay forma de saber quién es, el nodo falla con un mensaje claro.

## Tienda › Buscar productos

Busca por texto libre y entiende frases naturales: ignora muletillas («hola, busco una…») y funciona con plurales («camisetas») y sin tildes. Si algo coincide con **todas** las palabras, solo muestra eso; si no, muestra lo que más se parezca.

| Opción | Para qué sirve |
|--------|----------------|
| **Texto a buscar** | Vacío = lo que escribió el cliente. |
| **Solo en la categoría…** | Limita la búsqueda a una categoría (nombre o código). |
| **Máximo / Solo con stock / Mostrar descripción / Texto al final** | Igual que en *Productos de una categoría*. |

**Salidas:** *con resultados* y *sin resultados*. Texto listo: `{{ vars.busqueda.text }}`.

## Tienda › Ver producto (tarjeta)

Envía al cliente la **tarjeta del producto**: la foto con un texto que incluye nombre, precio (con el precio anterior tachado y el porcentaje de descuento si lo hay), categoría, descripción, opciones y aviso de stock. Está pensada para **acelerar la compra**: el texto termina diciendo qué responder para agregarlo.

| El cliente escribe | Qué pasa |
|--------------------|----------|
| `ver 2` · `info 2` · `foto 2` | La tarjeta del producto 2 de la última lista. |
| `ver camiseta negra` | La tarjeta de ese producto por nombre. |
| `ver camiseta` (varias coincidencias) | Una lista; responde `ver 1` para elegir. |

**Compra rápida.** Tras ver la tarjeta, el nodo [Agregar al pedido](#) entiende:

| Respuesta | Resultado |
|-----------|-----------|
| `1` · `sí` · `ok` · `agregar` · `quiero` | Agrega el producto de la tarjeta. |
| `1 x3` | Agrega 3 unidades. |
| `2` (si el producto tiene opciones) | Agrega la opción 2; `2 x3` para llevar 3. |

Después de agregar, el cliente vuelve a la lista donde estaba (por ejemplo, los resultados de su búsqueda).

| Opción | Para qué sirve |
|--------|----------------|
| **Producto a mostrar** | Vacío = lo que escribió el cliente. |
| **ID de producto (fijo)** | Para mostrar siempre el mismo producto. |
| **Enviar la tarjeta al cliente** | **Automático** (por defecto): envía solo cuando escribe un cliente real, así una prueba con **Probar** no manda mensajes. **Siempre** envía a su teléfono. **Nunca** solo arma la tarjeta. |
| **ID de cuenta de WhatsApp** | Opcional; por defecto responde por la cuenta que recibió el mensaje. Debe ser de tu cuenta. |

**Datos:** `{{ vars.producto.text }}` (la tarjeta), `{{ vars.producto.image_url }}`, `{{ vars.producto.product.name }}`, `{{ vars.producto.sent }}` (si se envió) y `{{ vars.producto.send_reason }}` (por qué no).

**Salidas:** *tarjeta lista*, *debe elegir* y *no entendí*.

> [!NOTE]
> Si un producto no tiene foto, la tarjeta se envía solo como texto. Si el envío falla, el flujo continúa y la tarjeta queda disponible en `{{ vars.producto.text }}`.

> [!TIP]
> Un producto agotado muestra «Por ahora está agotado» y no ofrece agregarlo.

## Tienda › Agregar al pedido

Entiende cómo escribe la gente:

| El cliente escribe | Qué pasa |
|--------------------|----------|
| `2` | Agrega el producto 2 de la última lista. |
| `2 x3` | Producto 2, cantidad 3. |
| `quiero dos tazas` · `camiseta x3` | Busca por nombre con esa cantidad. |
| `Camiseta negra` (nombre exacto) | Lo agrega. |
| `negra` (solo una palabra) | **No agrega**: sale por *no entendí* para que el flujo busque. |

Valida contra el catálogo: stock (contando lo que ya tiene en el pedido), cantidad mínima y productos disponibles. Los precios siempre salen del catálogo.

**Salidas:**
- **agregado:** `{{ vars.pedido.text }}` trae el pedido actualizado con el total.
- **debe elegir:** hay varias coincidencias o el producto tiene variantes; el texto lista las opciones y el cliente responde con el número.
- **no disponible:** sin stock o por debajo de la cantidad mínima.
- **no entendí:** no se reconoció el producto.

> [!NOTE]
> Los productos que exigen elegir **modificadores** (por ejemplo, complementos obligatorios de un plato) salen por *no disponible* con el aviso de pedirlos desde la tienda web.

## Tienda › Ver o editar pedido

Una de tres acciones: **Ver el pedido**, **Quitar un producto** (por el número de línea del pedido o por nombre) o **Vaciar el pedido**. Salidas: *con productos*, *vacío* y *no entendí*.

## Tienda › Confirmar pedido

Crea el **pedido real** de la tienda, igual que el checkout web (precios del catálogo, stock reservado, numeración, avisos al equipo). El cliente recibe el número de pedido, el total y el enlace de seguimiento. Después de confirmar, el pedido del cliente queda vacío, así que confirmar dos veces no duplica pedidos.

| Opción | Para qué sirve |
|--------|----------------|
| **Ubicación de entrega** | Vacío = la capturada con el nodo [Capturar ubicación](workflows-nodo-ubicacion). También acepta `lat, lng`. |
| **Dirección / Ciudad** | Si el cliente la escribió. Con ubicación no son obligatorias. |
| **Tipo de pedido** | En restaurantes: delivery, recoger o comer en local. |
| **ID del método de pago** | Vacío = el pedido queda pendiente, sin cobro. |
| **Nombre del cliente** | Vacío = el nombre de su contacto. |
| **Notas del pedido** | Se guardan en el pedido. |

Datos del pedido: `{{ vars.orden.order_number }}`, `.total`, `.total_text`, `.tracking_url`, `.text` (confirmación lista).

**Salidas:** *pedido creado*, *pedido vacío*, *falta dirección* (los productos requieren envío y no hay ubicación ni dirección) y *error* (tienda cerrada, stock insuficiente, etc.).

> [!WARNING]
> Confirmar crea un pedido de verdad y notifica a tu equipo. Prueba con un catálogo de prueba antes de activar el flujo.

## Flujo completo de ejemplo

**Ejemplo: comprar por chat (catálogo, búsqueda y pedido)** reúne todo en un solo flujo: menú, productos, búsqueda, tarjeta del producto (`ver 2`), agregar, ver, vaciar y confirmar. Prueba con **Probar** y, por ejemplo, `{"texto":"","telefono":"59170009999"}` y luego `{"texto":"1","telefono":"59170009999"}`. El cliente escribe *pedido* para ver su pedido y *confirmar* para cerrarlo.

> [!TIP]
> En WhatsApp real usa el disparador **Mensaje entrante** y reemplaza `{{ trigger.texto }}` por `{{ trigger.data.0.body }}` en las condiciones. Los nodos de tienda ya leen el mensaje y al cliente por sí solos si dejas esos campos vacíos.

**Versión documentada:** 1.1.0
