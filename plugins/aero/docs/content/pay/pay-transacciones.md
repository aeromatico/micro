# Función: Transacciones

**Ruta:** `Bolivia Pay → Transacciones`
**Controlador:** `Aero\Pay\Controllers\Transactions`
**Modelo:** `Aero\Pay\Models\Payment`
**Permiso:** `aero.pay.view_qr`

Historial de **todos los pagos recibidos**, sin importar de dónde vinieron. Es
una pantalla de solo lectura: un pago es un hecho ocurrido, no se edita.

## Filtros

| Filtro | Para qué sirve |
|--------|----------------|
| **Rango de fechas** | Pagos entre dos fechas. |
| **Banco** | Solo los bancos que tienes conectados. |
| **Origen** | `Tienda`, `Sistema (Pagos)`, `API externa` o `Cobranzas (CRM)`. |
| **Tipo de QR** | `QR dinámico`, `QR estático` o `Registrado a mano`. |

## Totales

Sobre la misma consulta filtrada se muestra un **total agrupado por moneda**, así
que siempre coincide con lo que ves en pantalla. Se recalcula al aplicar filtros,
buscar u ordenar.

## Registrar un pago manual

Cuando una transferencia no se detectó automáticamente (por ejemplo, el correo
del banco no llegó), se registra a mano **desde la cuenta**, no desde aquí:

**`Cuentas → abrir la cuenta → pestaña Transacciones → Crear`**

| Campo | Reglas | Para qué sirve |
|-------|--------|----------------|
| **Monto recibido** | requerido | Importe de la transferencia. |
| **Moneda** | — | BOB o USD. |
| **Pagador** | — | Quién transfirió. |
| **Fecha del pago** | requerido | Fecha de la transferencia. |
| **Hora aproximada** | — | Hora, si la sabes. |
| **Referencia (opcional)** | — | Dato que identifique qué QR se pagó. |

El pago queda registrado con origen *manual*, y así podrás distinguirlo del
resto.

> [!TIP]
> Usa la búsqueda para encontrar por pagador o por transacción. Si un pago no
> aparece, revisa el rango de fechas antes de registrarlo a mano.
