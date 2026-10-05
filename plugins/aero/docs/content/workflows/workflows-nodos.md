---
title: Nodos disponibles
sort: 20
---
# Nodos disponibles

| Nodo | Categoría | Opciones | Notas |
|------|-----------|----------|-------|
| Manual / Evento / Mensaje entrante / Webhook | Disparador | — | Punto de partida; entrega los datos en `trigger`. |
| Condición | Lógica | Valor, operador, comparar con | Salidas *sí* y *no*. Operadores: igual, distinto, contiene, mayor, menor, vacío, no vacío. |
| Guardar variable | Lógica | Nombre, valor | Disponible luego como `{{ vars.nombre }}`. |
| Esperar | Lógica | Segundos (máx. 86400) | Pausa la ejecución y la retoma después. |
| Llamar URL / Connector | Acción | Connector o URL https, método, datos | Con **Connector**, las credenciales quedan cifradas y no se guardan en el flujo. |
| Enviar mensaje (Hello) | Acción | Teléfono, mensaje, cuenta | Requiere Hello. La cuenta debe ser tuya. |
| Notificar (Notify) | Acción | Evento del catálogo, contexto JSON | Requiere Notify. |
| [Capturar ubicación](workflows-nodo-ubicacion) | Acción | Texto a analizar, latitud/longitud, variable, cobertura | Extrae una coordenada de lo que comparta el cliente. Salidas *con ubicación* y *sin ubicación*. |
| [Tienda › Menú de categorías](workflows-nodos-tienda) | Acción | Título, subcategorías de, incluir vacías, máximo | Arma el menú numerado de las categorías de tu tienda, listo para enviar. Requiere Tienda. |
| [Tienda › Productos de una categoría](workflows-nodos-tienda) | Acción | Categoría, límite, solo con stock, descripción | Lista los productos de la categoría que eligió el cliente (número o nombre). Requiere Tienda. |
| [Tienda › Buscar productos](workflows-nodos-tienda-pedido) | Acción | Texto, categoría, límite, solo con stock | Busca por texto libre («busco camisetas negras»). Requiere Tienda. |
| [Tienda › Ver producto (tarjeta)](workflows-nodos-tienda-pedido) | Acción | Producto, enviar, cuenta de WhatsApp | Arma y envía la tarjeta del producto con su foto y deja lista la compra rápida. Requiere Tienda. |
| [Tienda › Agregar al pedido](workflows-nodos-tienda-pedido) | Acción | Producto, cantidad | Agrega al pedido del cliente: «2», «2 x3» o «quiero una taza». Requiere Tienda. |
| [Tienda › Ver o editar pedido](workflows-nodos-tienda-pedido) | Acción | Acción, producto a quitar | Muestra, quita un producto o vacía el pedido. Requiere Tienda. |
| [Tienda › Confirmar pedido](workflows-nodos-tienda-pedido) | Acción | Ubicación, dirección, método de pago | Crea el pedido real de la tienda. Requiere Tienda. |
| Responder | Acción | Valor | Es lo que recibe quien llamó al workflow, por ejemplo la IA. |

> [!NOTE]
> Otros módulos pueden sumar sus propios nodos; aparecerán automáticamente en el editor.

> [!WARNING]
> Si un nodo falla, la ejecución termina con error y queda registrado el paso que falló.

**Versión documentada:** 1.1.0
