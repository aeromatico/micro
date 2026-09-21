# Formulario: Detalle del QR

**Ruta:** `Bolivia Pay → Generar código → (abrir un QR)`
**Controlador:** `Aero\Pay\Controllers\QrCodes`
**Modelo:** `Aero\Pay\Models\QrCode`

Muestra la ficha del QR emitido y los pagos que recibió. Todos los campos son
de **solo lectura**: un QR es un documento emitido, no se edita.

## Campos

| Campo | Para qué sirve |
|-------|----------------|
| **Referencia interna** | Identificador propio del QR. |
| **ID de QR del banco** | Identificador que devolvió el banco. |
| **Banco** | Proveedor con el que se generó. |
| **Estado** | `pending`, `paid`, `cancelled`, `expired` (según el ciclo de vida). |
| **Monto / Moneda** | Importe total del cobro. |
| **Descripción** | Texto del cobro (incluye sucursal y facturación). |
| **Sucursal** | Código de la sucursal asociada. |
| **NIT del cliente** | Si el manejo de impuestos estaba activo. |
| **Impuesto aplicado (%)** | Porcentaje usado. |
| **Importe del impuesto** | Monto del impuesto calculado. |
| **Fecha de vencimiento** | Hasta cuándo se puede pagar. |
| **Uso único** | Si admite uno o varios pagos. |

## Imagen del QR

Se muestra la imagen para escanear o descargar. También está disponible por
URL pública (por referencia), pensada para enviarla por WhatsApp u otros canales.

## Pagos recibidos

Relación de solo lectura con los pagos confirmados sobre ese QR. Si el QR es de
uso único y ya está pagado, aparece el pago correspondiente.

## Ciclo de vida

```text
pending  →  paid        (pagado)
         →  expired     (venció sin pagarse)
         →  cancelled   (anulado)
```

La confirmación llega por **webhook** (instantánea, si está configurado) o por
la **conciliación periódica** como respaldo. Cuando el QR cambia de estado, si
la cuenta tiene **webhook saliente** configurado, se notifica a tu sistema.

> [!TIP]
> Para conciliar un pago que no se detectó, usa la pestaña **Transacciones** de
> la cuenta y registra el pago a mano (ver [Transacciones](pay-transacciones)).
