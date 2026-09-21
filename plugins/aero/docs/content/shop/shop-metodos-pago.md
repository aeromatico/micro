# Formulario: Métodos de pago

**Ruta:** `Tienda → Métodos de pago`
**Controlador:** `Aero\Shop\Controllers\PaymentGateways`
**Modelo:** `Aero\Shop\Models\PaymentGateway`
**Permiso:** `aero.shop.manage_payment_gateways`

Define **cómo puede pagar** el comprador. Puedes combinar cobro automático por
QR (Bolivia Pay) y métodos offline.

## Campos

| Campo | Reglas | Para qué sirve |
|-------|--------|----------------|
| **Nombre visible al comprador** | requerido | Lo que ve el cliente en el checkout. |
| **Driver** | — | `Pagos offline` o `Bolivia Pay`. |
| **Cuenta bancaria (QRBO)** | — | Solo con *Bolivia Pay*: cuenta que emite el QR. Se administra en Bolivia Pay → Cuentas. |
| **Activo** | — | Si está apagado, no se ofrece. |
| **Instrucciones de pago** | — | Texto que ve el comprador: datos de cuenta, WhatsApp, efectivo, etc. |

## Tipos de cobro

| Driver | Cómo funciona |
|--------|---------------|
| **Pagos offline** | El cliente paga fuera del sistema (transferencia, efectivo, contra entrega); las instrucciones explican cómo. |
| **Bolivia Pay** | Se genera un **QR de pago** contra la cuenta elegida. |

> [!NOTE]
> Con una cuenta de **QR dinámico** (con API) el pedido puede confirmarse solo
> al pagarse; con un **QR estático** debes confirmar el pago a mano desde
> *Pedidos*.

> [!TIP]
> Crea varias opciones (QR, transferencia, efectivo contra entrega) y activa
> las que use tu negocio. Usa instrucciones claras para las opciones offline.
