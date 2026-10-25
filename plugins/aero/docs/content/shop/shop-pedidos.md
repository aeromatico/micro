# Formulario: Pedidos

**Ruta:** `Tienda → Pedidos`
**Controlador:** `Aero\Shop\Controllers\Orders`
**Modelo:** `Aero\Shop\Models\Order`
**Permiso:** `aero.shop.manage_orders`

Registra las compras recibidas. La mayoría de los datos del pedido son de
**solo lectura** (se generan en el checkout); lo que se edita desde aquí son
las **notas internas** y las **acciones de estado**.

## Pestaña Pedido

| Campo | Para qué sirve |
|-------|----------------|
| **Número de pedido** | Identificador del pedido (con el prefijo configurado). |
| **Estado** | Ver estados abajo. |
| **Cliente** | Comprador. |
| **Método de pago** | Cómo se paga. |
| **Sucursal** | Solo lectura. Nombre de la sucursal elegida en el checkout (si la tienda [maneja sucursales](shop-configuracion)). |
| **Referencia de pago reportada** | Referencia que dejó el comprador. |
| **Moneda / Subtotal / Total** | Importes del pedido. |
| **Comentario del cliente** | Nota del comprador. |
| **Notas internas** | **Editable**: notas para el equipo. |

En pedidos de **restaurante** se muestran además (solo lectura):

| Campo | Para qué sirve |
|-------|----------------|
| **Tipo de pedido** | Comer aquí, Recoger o Delivery. |
| **Mesa** | Solo en *Comer aquí*. |
| **Programado para** | Fecha y hora si el cliente programó el pedido. |

### Número de pedido y número del día

En restaurantes cada pedido recibe además un **número del día** (1, 2, 3…) que
vuelve a 1 cada día, según la zona horaria de la tienda. Es el número corto que
ve el comensal y que se dice en el salón. El **número de pedido** (ej. `PED-000018`)
no cambia ni se reinicia. En pedidos anteriores sin número del día, el número
corto es el final del número de pedido (`PED-000018` → `18`). El comensal puede
usar **Consultar mi pedido** con su número del día y su celular.

### Checkout del restaurante

- **Comer en local** aparece primero y viene marcado.
- El celular es opcional: sin él se crea un cliente invitado por pedido y se conserva el nombre.
- Bajo el número de mesa se avisa que, si la mesa no tiene número, lo indique al mesero o en caja.
- Pago: **Efectivo** (pasa a caja) o **Pago QR** (el comensal espera en la página del pedido y se confirma solo). El Pago QR en el local se puede apagar en [Configuración](shop-configuracion).
- El botón dice **Pedir ahora**.

Los bloques de **pago** y **dirección de envío** se muestran según el pedido.
La dirección de envío incluye, si el cliente compartió su ubicación, el enlace
**Ver ubicación en el mapa** con las coordenadas y su etiqueta. La confirmación
pública del pedido también enlaza el mapa.

## Pestaña Artículos

Detalle de los productos comprados: producto, variante, SKU, precio unitario,
cantidad y subtotal. Es una **instantánea** de lo comprado (no cambia si luego
editas el producto).

## Pestaña Historial

Registro de cambios de estado, con fecha y nota.

## Estados

| Estado | Significado |
|--------|-------------|
| `pending` | Pendiente. |
| `awaiting_payment` | Esperando pago. |
| `paid` | Pagado. |
| `fulfilled` | Despachado / entregado. |
| `cancelled` | Cancelado. |
| `refunded` | Reembolsado. |

## Acciones

| Acción | Qué hace |
|--------|----------|
| **Marcar como pagado** | Confirma el pago (el stock ya se reservó al crear el pedido). |
| **Marcar como despachado/entregado** | Marca el pedido como cumplido. |
| **Cancelar pedido** | Cancela y **libera el stock** reservado, con un motivo. |

> [!NOTE]
> Con una cuenta de **QR estático** (sin API de banco), el pago no se confirma
> solo: el pedido muestra un aviso para que verifiques la transacción en
> *Bolivia Pay → Cuentas → Transacciones* antes de marcar pagado.

> [!NOTE]
> En restaurantes, el avance en cocina (nuevo, preparando, listo, entregado) se
> gestiona en [Cocina](shop-cocina); al marcar *Entregado* el pedido pasa a despachado.

> [!NOTE]
> En el modo [Marketplace](shop-comerciantes), una compra con productos de varios
> comerciantes se divide en un pedido por comerciante. Cada comerciante ve y
> gestiona solo sus pedidos; el administrador ve todos.

> [!TIP]
> Al cambiar de estado se pueden enviar avisos al cliente (pago, envío,
> cancelación) por el plugin de notificaciones, si están configurados.

> [!NOTE]
> Los pedidos del local también se consultan y cobran desde la caja del punto de venta (ver la documentación de POS).

### Botón de emergencia

En la página del pedido, quien come en el local (**Comer en local**) ve el botón **Emergencia**. Antes de enviar pide confirmar que es una emergencia real. Al confirmar, se avisa al personal con el **número de mesa** (si el pedido no tiene mesa, con el número del pedido y el nombre): la **Cocina** y la **caja** (POS) lo muestran en una franja clara hasta que alguien lo marca como **Atendida**. El cliente ve «Avisamos al personal» y, cuando lo atienden, «El personal ya vio tu aviso». Para evitar abusos, se puede volver a avisar desde el mismo pedido a los 2 minutos y hay un límite por hora. El botón se puede apagar en [Configuración](shop-configuracion).

### Mientras el cliente espera

En la página del pedido, un recuadro **Mientras esperas** muestra las promociones o avisos que escribiste en [Configuración](shop-configuracion) (una línea por mensaje). Quien pidió **Comer en local** ve además el **WiFi del local** con la red y la clave, si las configuraste. Solo aplica a tiendas de tipo *Restaurante*; desaparece cuando el pedido se entrega, se cancela o se reembolsa.

### Opinión del cliente

Cuando el pedido termina, la misma página pide al cliente estrellas y un comentario, según la política elegida en Configuración. Las opiniones se revisan en [Opiniones de los clientes](shop-opiniones).

**Versión documentada:** 1.13.1
