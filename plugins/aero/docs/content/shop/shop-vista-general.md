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

## Permisos

| Permiso | Desbloquea |
|---------|-----------|
| `aero.shop.manage_products` | Productos |
| `aero.shop.manage_collections` | Colecciones |
| `aero.shop.manage_orders` | Pedidos, Clientes y Cocina |
| `aero.shop.manage_inventory` | Inventario |
| `aero.shop.manage_payment_gateways` | Métodos de pago |
| `aero.shop.manage_settings` | Configuración de tienda |

**Versión documentada:** 1.8.0
