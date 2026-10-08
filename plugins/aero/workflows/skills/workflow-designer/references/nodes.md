# Catálogo de nodos de Aero.Workflows

> Generado automáticamente desde `NodeRegistry` por `php artisan workflows:skill-catalog`. No editar a mano.

Cada nodo tiene `id`, `type`, `position` y `data`. `data` guarda los campos de la tabla. Las conexiones (`edges`) pueden llevar `sourceHandle` para elegir la salida.

⚠ = nodo con efectos reales (cobra, envía o llama URLs). Solo se usa después de que la persona aprobó el plan; el workflow siempre queda en borrador hasta que ella lo publique.

## `trigger.event`

- **Nombre:** Evento de la plataforma
- **Categoría:** trigger
- **Salidas:** una sola; la conexión no lleva `sourceHandle`.

## `trigger.manual`

- **Nombre:** Manual / prueba
- **Categoría:** trigger
- **Salidas:** una sola; la conexión no lleva `sourceHandle`.

## `trigger.message`

- **Nombre:** Mensaje entrante
- **Categoría:** trigger
- **Salidas:** una sola; la conexión no lleva `sourceHandle`.

## `trigger.webhook`

- **Nombre:** Webhook entrante
- **Categoría:** trigger
- **Salidas:** una sola; la conexión no lleva `sourceHandle`.

## `logic.condition`

- **Nombre:** Condición (sí / no)
- **Categoría:** logic
- **Salidas (`sourceHandle`):** `true` (sí), `false` (no)

| Campo (`data.key`) | Tipo | Opciones | Ayuda |
|---|---|---|---|
| `left` (Valor) | text |  | Ej: {{ trigger.plan }} |
| `op` (Operador) | select | `eq`, `neq`, `contains`, `gt`, `lt`, `empty`, `notempty` |  |
| `right` (Comparar con) | text |  |  |

## `logic.delay`

- **Nombre:** Esperar
- **Categoría:** logic
- **Salidas:** una sola; la conexión no lleva `sourceHandle`.

| Campo (`data.key`) | Tipo | Opciones | Ayuda |
|---|---|---|---|
| `seconds` (Segundos (máx. 86400)) | number |  |  |

## `logic.set`

- **Nombre:** Guardar variable
- **Categoría:** logic
- **Salidas:** una sola; la conexión no lleva `sourceHandle`.

| Campo (`data.key`) | Tipo | Opciones | Ayuda |
|---|---|---|---|
| `name` (Nombre) | text |  | Luego: {{ vars.nombre }} |
| `value` (Valor) | text |  |  |

## `action.http` ⚠

- **Nombre:** Llamar URL / Connector
- **Categoría:** action
- **Salidas:** una sola; la conexión no lleva `sourceHandle`.

| Campo (`data.key`) | Tipo | Opciones | Ayuda |
|---|---|---|---|
| `connector_id` (Connector (recomendado)) | connector |  | Las credenciales viven cifradas en el Connector. |
| `url` (URL https (si no hay Connector)) | text |  |  |
| `method` (Método) | select | `GET`, `POST`, `PUT`, `PATCH`, `DELETE` |  |
| `payload` (Datos (JSON)) | json |  |  |

## `action.location`

- **Nombre:** Capturar ubicación
- **Categoría:** action
- **Salidas (`sourceHandle`):** `found` (con ubicación), `not_found` (sin ubicación)

| Campo (`data.key`) | Tipo | Opciones | Ayuda |
|---|---|---|---|
| `source` (Texto o enlace a analizar) | text |  | Vacío = el mensaje que disparó el flujo (la ubicación de WhatsApp, coordenadas o un enlace de Maps). |
| `latitude` (Latitud (si ya la tienes)) | text |  |  |
| `longitude` (Longitud (si ya la tienes)) | text |  |  |
| `save_as` (Guardar en la variable) | text |  | Por defecto «ubicacion»: {{ vars.ubicacion.lat }}, .lng, .coords, .maps_url, .name |
| `center_lat` (Cobertura: latitud del centro) | text |  | Opcional. Con centro y radio se calcula in_zone y distance_km. |
| `center_lng` (Cobertura: longitud del centro) | text |  |  |
| `radius_km` (Cobertura: radio (km)) | number |  |  |

## `action.message` ⚠

- **Nombre:** Enviar mensaje (Hello)
- **Categoría:** action
- **Salidas:** una sola; la conexión no lleva `sourceHandle`.

| Campo (`data.key`) | Tipo | Opciones | Ayuda |
|---|---|---|---|
| `to` (Teléfono destino) | text |  | Ej: {{ trigger.data.0.contact.phone }} |
| `body` (Mensaje) | textarea |  |  |
| `account_id` (ID de cuenta (opcional)) | number |  |  |

## `action.notify` ⚠

- **Nombre:** Notificar (Notify)
- **Categoría:** action
- **Salidas:** una sola; la conexión no lleva `sourceHandle`.

| Campo (`data.key`) | Tipo | Opciones | Ayuda |
|---|---|---|---|
| `event` (Evento del catálogo) | text |  |  |
| `context` (Contexto (JSON)) | json |  |  |

## `action.reply` ⚠

- **Nombre:** Responder al remitente (Hello)
- **Categoría:** action
- **Salidas:** una sola; la conexión no lleva `sourceHandle`.

| Campo (`data.key`) | Tipo | Opciones | Ayuda |
|---|---|---|---|
| `body` (Mensaje) | textarea |  | Se envía a quien escribió el mensaje que disparó el workflow. |

## `action.respond`

- **Nombre:** Responder (valor de retorno)
- **Categoría:** action
- **Salidas:** una sola; la conexión no lleva `sourceHandle`.

| Campo (`data.key`) | Tipo | Opciones | Ayuda |
|---|---|---|---|
| `value` (Valor) | textarea |  | Es lo que recibe quien llamó (p. ej. la IA). |

## `hello.reply_buttons` ⚠

- **Nombre:** Hello › Responder con botones
- **Categoría:** action
- **Importante:** Responde al remitente del mensaje que disparó el flujo, solo dentro de las 24 h de su último mensaje y con una cuenta de la API oficial de WhatsApp.
- **Salidas:** una sola; la conexión no lleva `sourceHandle`.

| Campo (`data.key`) | Tipo | Opciones | Ayuda |
|---|---|---|---|
| `body` (Texto del mensaje) | textarea |  | Obligatorio, hasta 1024 caracteres. Admite {{ vars.nombre }}. |
| `buttons` (Botones (uno por línea)) | textarea |  | De 1 a 3, hasta 20 caracteres cada uno. Formato: «Texto» o «Texto \| id». El id es lo que llega cuando lo tocan (si no lo pones, se genera del texto). |

## `hello.reply_call` ⚠

- **Nombre:** Hello › Responder con botón de llamada
- **Categoría:** action
- **Importante:** Responde al remitente del mensaje que disparó el flujo, solo dentro de las 24 h de su último mensaje y con una cuenta de la API oficial de WhatsApp.
- **Salidas:** una sola; la conexión no lleva `sourceHandle`.

| Campo (`data.key`) | Tipo | Opciones | Ayuda |
|---|---|---|---|
| `body` (Texto del mensaje) | textarea |  | Obligatorio, hasta 1024 caracteres. Admite {{ vars.nombre }}. |
| `call_text` (Texto del botón (opcional)) | text |  | Hasta 20 caracteres. Requiere WhatsApp Business Calling activado en el número. |

## `hello.reply_link` ⚠

- **Nombre:** Hello › Responder con enlace
- **Categoría:** action
- **Importante:** Responde al remitente del mensaje que disparó el flujo, solo dentro de las 24 h de su último mensaje y con una cuenta de la API oficial de WhatsApp.
- **Salidas:** una sola; la conexión no lleva `sourceHandle`.

| Campo (`data.key`) | Tipo | Opciones | Ayuda |
|---|---|---|---|
| `body` (Texto del mensaje) | textarea |  | Obligatorio, hasta 1024 caracteres. Admite {{ vars.nombre }}. |
| `cta_text` (Texto del botón) | text |  | Obligatorio, hasta 20 caracteres. |
| `cta_url` (Enlace) | text |  | URL que empiece con http:// o https://. |

## `hello.reply_list` ⚠

- **Nombre:** Hello › Responder con menú
- **Categoría:** action
- **Importante:** Responde al remitente del mensaje que disparó el flujo, solo dentro de las 24 h de su último mensaje y con una cuenta de la API oficial de WhatsApp.
- **Salidas:** una sola; la conexión no lleva `sourceHandle`.

| Campo (`data.key`) | Tipo | Opciones | Ayuda |
|---|---|---|---|
| `body` (Texto del mensaje) | textarea |  | Obligatorio, hasta 1024 caracteres. Admite {{ vars.nombre }}. |
| `list_button` (Texto del botón que abre el menú) | text |  | Hasta 20 caracteres. Por defecto «Ver opciones». |
| `list_section` (Título de la sección (opcional)) | text |  |  |
| `list_rows` (Opciones (una por línea)) | textarea |  | De 1 a 10. Formato: «Título \| Descripción \| id». Título hasta 24 y descripción hasta 72 caracteres; descripción e id son opcionales («Título \|\| id» para poner solo el id). |

## `hello.reply_location_request` ⚠

- **Nombre:** Hello › Pedir ubicación
- **Categoría:** action
- **Importante:** Responde al remitente del mensaje que disparó el flujo, solo dentro de las 24 h de su último mensaje y con una cuenta de la API oficial de WhatsApp.
- **Salidas:** una sola; la conexión no lleva `sourceHandle`.

| Campo (`data.key`) | Tipo | Opciones | Ayuda |
|---|---|---|---|
| `body` (Texto del mensaje) | textarea |  | Obligatorio, hasta 1024 caracteres. Admite {{ vars.nombre }}. |

## `pay.cancel` ⚠

- **Nombre:** Cobros › Anular un cobro pendiente
- **Categoría:** action
- **Salidas (`sourceHandle`):** `cancelled` (anulado), `not_cancellable` (ya no se puede), `not_found` (no existe), `failed` (el banco no lo anuló)

| Campo (`data.key`) | Tipo | Opciones | Ayuda |
|---|---|---|---|
| `reference` (Referencia del cobro) | text |  | Ej: {{ vars.cobro.reference }} |
| `save_as` (Guardar en la variable) | text |  | Por defecto «cobro». |

## `pay.charge` ⚠

- **Nombre:** Cobros › Generar cobro (QR / enlace)
- **Categoría:** action
- **Salidas (`sourceHandle`):** `created` (cobro creado), `failed` (no se pudo)

| Campo (`data.key`) | Tipo | Opciones | Ayuda |
|---|---|---|---|
| `amount` (Monto) | text |  | Ej: 150 o {{ vars.pedido.total }}. Debe ser mayor a 0. |
| `currency` (Moneda) | select | `BOB`, `USD` | Por defecto: BOB. |
| `description` (Concepto) | text |  | Lo que verá el pagador. Ej: «Pedido 1042». |
| `reference` (Referencia propia (opcional)) | text |  | Tu número de pedido/factura. Debe ser único por cobro. |
| `bank_account_id` (ID de cuenta (opcional)) | number |  | Vacío = la única cuenta activa del tenant. Con varias cuentas hay que indicar cuál (Pagos → Cuentas). |
| `due_days` (Días de vigencia) | number |  | Vacío = el vencimiento configurado en Pagos (máx. 90). |
| `save_as` (Guardar en la variable) | text |  | Por defecto «cobro»: {{ vars.cobro.reference }}, .image_url, .pay_url, .text, .status |

## `pay.status`

- **Nombre:** Cobros › Consultar estado de un cobro
- **Categoría:** action
- **Salidas (`sourceHandle`):** `paid` (pagado), `pending` (pendiente), `expired` (vencido), `cancelled` (anulado), `not_found` (no existe)

| Campo (`data.key`) | Tipo | Opciones | Ayuda |
|---|---|---|---|
| `reference` (Referencia del cobro) | text |  | Ej: {{ vars.cobro.reference }} (la que devuelve «Generar cobro»). |
| `refresh` (Reconsultar al banco) | select | `1`, `0` | Por defecto: Sí (solo si sigue pendiente). |
| `save_as` (Guardar en la variable) | text |  | Por defecto «cobro»: {{ vars.cobro.status }}, .paid_at, .payer_name |

## `pay.summary`

- **Nombre:** Cobros › Resumen de pagos recibidos
- **Categoría:** action
- **Salidas (`sourceHandle`):** `found` (con pagos), `empty` (sin pagos)

| Campo (`data.key`) | Tipo | Opciones | Ayuda |
|---|---|---|---|
| `period` (Período) | select | `today`, `yesterday`, `week`, `month` | Por defecto: Hoy. |
| `save_as` (Guardar en la variable) | text |  | Por defecto «resumen_pagos»: {{ vars.resumen_pagos.text }}, .count, .totals. Datos internos del negocio: no los envíes a clientes. |

## `shop.cart`

- **Nombre:** Tienda › Ver o editar pedido
- **Categoría:** action
- **Salidas (`sourceHandle`):** `found` (con productos), `empty` (vacío), `not_found` (no entendí)

| Campo (`data.key`) | Tipo | Opciones | Ayuda |
|---|---|---|---|
| `action` (Acción) | select | `view`, `remove`, `clear` |  |
| `item` (Producto a quitar) | text |  | Número de la línea del pedido o nombre. Vacío = lo que escribió el cliente. |
| `contact` (Cliente (teléfono)) | text |  | Vacío = el cliente que escribió. Para pruebas: un teléfono, ej. 59170000000. |
| `save_as` (Guardar en la variable) | text |  | Por defecto «pedido». |

## `shop.cart_add` ⚠

- **Nombre:** Tienda › Agregar al pedido
- **Categoría:** action
- **Salidas (`sourceHandle`):** `added` (agregado), `choose` (debe elegir), `unavailable` (no disponible), `not_found` (no entendí)

| Campo (`data.key`) | Tipo | Opciones | Ayuda |
|---|---|---|---|
| `product` (Producto elegido) | text |  | Vacío = lo que escribió el cliente: «2» (de la última lista), «2 x3» (producto 2, cantidad 3) o el nombre («2 camisetas»). |
| `product_id` (ID de producto (fijo)) | number |  |  |
| `quantity` (Cantidad) | number |  | Vacío = la que diga el cliente, o 1. |
| `contact` (Cliente (teléfono)) | text |  | Vacío = el cliente que escribió. Para pruebas: un teléfono, ej. 59170000000. |
| `save_as` (Guardar en la variable) | text |  | Por defecto «pedido»: {{ vars.pedido.text }}, .cart_total, .cart_count |

## `shop.categories`

- **Nombre:** Tienda › Menú de categorías
- **Categoría:** action
- **Salidas (`sourceHandle`):** `found` (con categorías), `empty` (sin categorías)

| Campo (`data.key`) | Tipo | Opciones | Ayuda |
|---|---|---|---|
| `title` (Título del menú) | text |  | Por defecto: «¿Qué te gustaría ver?» |
| `footer` (Texto al final) | text |  | Por defecto: «Responde con el número o el nombre.» |
| `parent` (Subcategorías de…) | text |  | Vacío = categorías principales. O el nombre/código de una categoría. |
| `include_empty` (Incluir categorías sin productos) | select | `1`, `0` | Por defecto: No. |
| `max_items` (Máximo de categorías) | number |  | Por defecto 10 (máx. 30). |
| `save_as` (Guardar en la variable) | text |  | Por defecto «categorias»: {{ vars.categorias.text }}, .items, .count |

## `shop.checkout` ⚠

- **Nombre:** Tienda › Confirmar pedido
- **Categoría:** action
- **Salidas (`sourceHandle`):** `created` (pedido creado), `empty` (pedido vacío), `needs_address` (falta dirección), `error` (error)

| Campo (`data.key`) | Tipo | Opciones | Ayuda |
|---|---|---|---|
| `location` (Ubicación de entrega) | text |  | Vacío = la capturada con «Capturar ubicación» o, si no hay, la última que el cliente compartió en el chat (últimas 6 h). |
| `address` (Dirección (texto)) | text |  | Vacío = la que el cliente escribió con el pedido: «confirmar Av. Arce 123, La Paz». |
| `city` (Ciudad) | text |  |  |
| `order_type` (Tipo de pedido (restaurante)) | select | `delivery`, `pickup`, `dine_in` |  |
| `table` (Mesa (comer en local)) | text |  | Vacío = lo que escribió el cliente: «mesa 5». |
| `payment_gateway_id` (ID del método de pago) | number |  | Vacío = queda pendiente, sin cobro. |
| `customer_name` (Nombre del cliente) | text |  | Vacío = el nombre de su contacto. |
| `notes` (Notas del pedido) | text |  |  |
| `contact` (Cliente (teléfono)) | text |  | Vacío = el cliente que escribió. Para pruebas: un teléfono, ej. 59170000000. |
| `save_as` (Guardar en la variable) | text |  | Por defecto «orden»: {{ vars.orden.text }}, .order_number, .total, .tracking_url |

## `shop.product`

- **Nombre:** Tienda › Ver producto (tarjeta)
- **Categoría:** action
- **Salidas (`sourceHandle`):** `found` (tarjeta lista), `choose` (debe elegir), `not_found` (no entendí)

| Campo (`data.key`) | Tipo | Opciones | Ayuda |
|---|---|---|---|
| `product` (Producto a mostrar) | text |  | Vacío = lo que escribió el cliente: «ver 2» (de la última lista), «2» o el nombre. |
| `product_id` (ID de producto (fijo)) | number |  |  |
| `send` (Enviar la tarjeta al cliente) | select | `auto`, `1`, `0` | Envía la foto con el texto por WhatsApp. Por defecto, solo cuando escribió un cliente. |
| `account_id` (ID de cuenta de WhatsApp (opcional)) | number |  |  |
| `contact` (Cliente (teléfono)) | text |  | Vacío = el cliente que escribió. |
| `save_as` (Guardar en la variable) | text |  | Por defecto «producto»: {{ vars.producto.text }}, .image_url, .product.name, .sent |

## `shop.products`

- **Nombre:** Tienda › Productos de una categoría
- **Categoría:** action
- **Salidas (`sourceHandle`):** `found` (con productos), `empty` (categoría vacía), `not_found` (no entendí)

| Campo (`data.key`) | Tipo | Opciones | Ayuda |
|---|---|---|---|
| `category` (Categoría elegida) | text |  | Vacío = lo que escribió el cliente: el número del menú, el nombre o el código de la categoría. |
| `category_id` (ID de categoría (fijo)) | number |  | Opcional: para mostrar siempre la misma categoría. |
| `query` (Buscar dentro (texto)) | text |  |  |
| `limit` (Máximo de productos) | number |  | Por defecto 8 (máx. 20). |
| `only_in_stock` (Solo con stock) | select | `1`, `0` | Por defecto: No (los agotados se marcan). |
| `show_description` (Mostrar descripción) | select | `1`, `0` | Por defecto: Sí. |
| `footer` (Texto al final) | text |  |  |
| `save_as` (Guardar en la variable) | text |  | Por defecto «productos»: {{ vars.productos.text }}, .items, .category |

## `shop.search`

- **Nombre:** Tienda › Buscar productos
- **Categoría:** action
- **Salidas (`sourceHandle`):** `found` (con resultados), `empty` (sin resultados)

| Campo (`data.key`) | Tipo | Opciones | Ayuda |
|---|---|---|---|
| `query` (Texto a buscar) | text |  | Vacío = lo que escribió el cliente. Entiende frases: «busco una camiseta negra». |
| `category` (Solo en la categoría…) | text |  | Opcional: nombre o código. |
| `limit` (Máximo de resultados) | number |  | Por defecto 8 (máx. 20). |
| `only_in_stock` (Solo con stock) | select | `1`, `0` | Por defecto: No. |
| `show_description` (Mostrar descripción) | select | `1`, `0` | Por defecto: Sí. |
| `footer` (Texto al final) | text |  |  |
| `contact` (Cliente (teléfono)) | text |  | Vacío = el cliente que escribió. Para pruebas: un teléfono, ej. 59170000000. |
| `save_as` (Guardar en la variable) | text |  | Por defecto «busqueda»: {{ vars.busqueda.text }}, .items |

