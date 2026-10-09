# Tienda — Vista general

**Tienda** te permite vender en línea desde tu sitio: catálogo con variantes,
inventario, colecciones, pedidos y clientes, con cobro por **Bolivia Pay** o
pago offline.

Todo funciona por sitio (tenant): tu catálogo, stock y pedidos están aislados.
La tienda se **activa por sitio** desde su configuración.

## Menú

| Sección | Para qué sirve |
|---------|----------------|
| **Productos** | Catálogo: físicos y digitales, con variantes. |
| **Colecciones** | Agrupaciones y categorías de productos. |
| **Pedidos** | Compras recibidas y su estado. |
| **Clientes** | Compradores de la tienda. |
| **Métodos de pago** | Cómo se cobra (Bolivia Pay u offline). |
| **Cocina** | Tablero de pedidos del restaurante (solo en tipo *Restaurante*). |
| **Inventario** | Movimientos de stock (visible si el inventario está activo). |
| **Configuración de tienda** | Activar la tienda, tipo de tienda (estándar, WhatsApp, restaurante), horario, sucursales, moneda, inventario e invitados. |

## Flujo de una compra

```text
Catálogo  →  Carrito  →  Checkout  →  Pedido (reserva stock)
                                          │
                    pago confirmado ──────┴────→ Pagado → Despachado
```

## Guías de cada función

- [Configuración de tienda](shop-configuracion) — activar y ajustar la tienda.
- [Productos](shop-productos) — el catálogo.
- [Variantes y opciones](shop-variantes) — tallas, colores y SKU por combinación.
- [Colecciones](shop-colecciones) — organizar el catálogo.
- [Pedidos](shop-pedidos) — compras y su estado.
- [Clientes](shop-clientes) — compradores.
- [Métodos de pago](shop-metodos-pago) — cobro online u offline.
- [Cocina](shop-cocina) — pantalla de cocina del restaurante.
- [Inventario](shop-inventario) — movimientos de stock.

## Costo de envío

En **Configuración de tienda** hay un campo **Costo de envío**: se cobra **una vez por pedido** que incluya productos que requieren envío (los digitales no lo pagan). Con `0` el envío es gratis. El checkout web y el pedido por chat muestran este costo.

> [!NOTE]
> En una tienda de tipo *Restaurante* este campo no aparece: se usa **Costo de delivery**, y en el chat el envío se muestra solo como aviso de delivery.

## Compra por chat (Workflows)

Si usas **Workflows** con un chat real (por ejemplo WhatsApp por Hello), la tienda aporta nodos para armar un asistente de compra. Cada cliente tiene su propio carrito y su última lista de productos mostrada.

| Nodo | Qué hace |
|------|----------|
| **Tienda › Menú de categorías** | Muestra las categorías numeradas |
| **Tienda › Productos de una categoría** | Lista los productos de la categoría elegida por su número |
| **Tienda › Ver producto (tarjeta)** | Envía foto, precio con descuento, descripción, opciones y stock; deja lista la compra rápida («1», «1 x3», «sí») |
| **Buscar productos** | Busca en el catálogo |
| **Agregar al pedido** | Suma productos al carrito del cliente |
| **Ver o editar pedido** | Muestra el carrito; entiende órdenes como «quitar 2» |
| **Confirmar pedido** | Crea el pedido; en restaurante toma la mesa («mesa 5») o, si no hay ubicación en el flujo, la última que el cliente compartió en el chat (dentro de 6 horas) |

Si el cliente compartió su ubicación en el chat, el pedido la guarda y la dirección y la ciudad pasan a ser opcionales.

## Permisos

| Permiso | Desbloquea |
|---------|-----------|
| `aero.shop.manage_products` | Productos |
| `aero.shop.manage_collections` | Colecciones |
| `aero.shop.manage_orders` | Pedidos, Clientes y Cocina |
| `aero.shop.manage_inventory` | Inventario |
| `aero.shop.manage_payment_gateways` | Métodos de pago |
| `aero.shop.manage_settings` | Configuración de tienda |

**Versión documentada:** 1.9.5
