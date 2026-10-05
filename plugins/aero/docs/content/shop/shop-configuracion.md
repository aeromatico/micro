# Formulario: Configuración de tienda

**Ruta:** `Tienda → Configuración de tienda`
**Controlador:** `Aero\Shop\Controllers\ShopSettings`
**Modelo:** `Aero\Shop\Models\ShopSettings`
**Permiso:** `aero.shop.manage_settings`

Activa la tienda del sitio y define sus reglas generales. Mientras la tienda
esté apagada, el menú de catálogo y pedidos no aparece.

## Campos generales

| Campo | Para qué sirve |
|-------|----------------|
| **Tienda activada** | Interruptor maestro del módulo. |
| **Moneda base** | Moneda en la que se expresan los precios. |
| **Usar sistema de inventario** | Si se apaga, la tienda no valida ni descuenta stock (útil para catálogos bajo pedido, servicios, etc.) y se oculta el menú *Inventario*. |
| **Permitir compra como invitado** | Habilita el checkout sin registrarse. |
| **Prefijo de número de pedido** | Prefijo de los números de pedido (ej. `ORD-`). |
| **Umbral de stock bajo** | Cantidad a partir de la cual se considera stock bajo. |

## Tipo de tienda

| Opción | Qué hace |
|--------|----------|
| **Tienda estándar** | Checkout normal (valor por defecto). |
| **Tienda para WhatsApp** | El checkout solo pide el celular del cliente (prefijo 591) y manda el pedido al chat. |
| **Restaurante** | Agrega extras por plato, tipo de pedido, mesas por QR, tiempos y la pantalla de [Cocina](shop-cocina). |

### Tienda para WhatsApp

| Campo | Para qué sirve |
|-------|----------------|
| **Envío del pedido** | **WhatsApp Api**: el cliente abre WhatsApp con el pedido ya escrito hacia el número de la tienda. **Market Api**: el pedido se envía desde una de tus cuentas de Hello. |
| **Número de WhatsApp de la tienda** | Con código de país, solo dígitos (ej. `59171234567`). Solo con *WhatsApp Api*. |
| **Cuenta de Hello** | Cuenta de WhatsApp que enviará los pedidos (se administran en Hello → Cuentas). Solo con *Market Api*. |

### Restaurante

| Campo | Para qué sirve |
|-------|----------------|
| **Tipos de pedido que aceptas** | Comer aquí, Recoger, Delivery. |
| **Costo de delivery** | Cargo para pedidos a domicilio. |
| **Cantidad de mesas** | Cada mesa tiene un QR (`/tienda?mesa=N`) que prellena la mesa del pedido. |
| **Preparación por defecto (min)** | Se usa si el plato no define su propio tiempo. |
| **Capacidad de cocina (pedidos a la vez)** | Hasta este número de pedidos en cocina no se suma demora. |
| **Minutos extra por pedido en espera** | Por cada pedido por encima de la capacidad. |
| **Minutos extra en «modo ocupado»** | Se suman cuando cocina enciende el modo ocupado. |
| **Tiempo de reparto (min)** | Se suma solo a pedidos de delivery. |
| **Anticipación mínima de pedidos programados (min)** | Un pedido programado debe hacerse con al menos este tiempo. |
| **Aceptar pedidos fuera de horario** | Si está activo, el cliente puede programar su pedido; si no, la tienda se bloquea al cerrar. |

Con mesas configuradas aparece el botón **Generar QR de mesas**: genera un QR
por mesa listo para imprimir (requiere un dominio principal del sitio).

## Horario de la tienda (todos los tipos)

| Opción | Qué hace |
|--------|----------|
| **Abierto 24 horas** | Sin restricción (por defecto). |
| **Por horario** | Muestra *Zona horaria* y *Horarios*: una fila por día con *Abre* y *Cierra* (HH:MM) o *Cerrado todo el día*. Repite el día para turnos partidos; un cierre menor a la apertura cruza la medianoche. |
| **Atención en línea** | Aparece el interruptor **Recibiendo pedidos ahora**: tú abres y cierras manualmente. |

Fuera de horario la tienda no acepta pedidos: se cierran el checkout y el pedido.

## Sucursales (todos los tipos)

| Campo | Para qué sirve |
|-------|----------------|
| **Manejar sucursales** | Apagado, la tienda es un solo negocio. Encendido, el checkout pide elegir una sucursal. |
| **Sucursales** | Lista con el botón *Agregar sucursal*. Cada una tiene **Nombre**, **Teléfono**, **Dirección**, **Ubicación** (en el mapa, o latitud y longitud como números si no hay selector de mapa) y el interruptor **Activa**. |

- Las filas sin nombre se descartan al guardar.
- Solo las sucursales **activas** se ofrecen en el checkout; si el interruptor está apagado no se pide nada.
- El cliente debe elegir una sucursal existente y activa; si no, el checkout responde «Elige la sucursal para tu pedido.».
- El pedido guarda el **nombre** de la sucursal elegida y lo muestra en [Pedidos](shop-pedidos).

## Monedas

Además de la moneda base, puedes agregar otras monedas con su **tipo de cambio**.
Se administran en la misma pantalla:

| Acción | Qué hace |
|--------|----------|
| **Agregar / actualizar** | Añade una moneda con su tipo de cambio. |
| **Eliminar** | Quita la moneda del sitio. |

> [!NOTE]
> Si el inventario está apagado, el sistema no reserva ni descuenta stock al
> vender, y la sección *Inventario* deja de mostrarse.

> [!TIP]
> Deja la **compra como invitado** activada para reducir la fricción; puedes
> vincular al comprador con un cliente después.

**Versión documentada:** 1.8.0
