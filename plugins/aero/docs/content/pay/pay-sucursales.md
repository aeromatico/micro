# Formulario: Sucursales

**Ruta:** `Bolivia Pay → Sucursales`
**Controlador:** `Aero\Pay\Controllers\Branches`
**Modelo:** `Aero\Pay\Models\Branch`
**Permiso:** `aero.pay.manage_accounts`

Registra los puntos de cobro del negocio. Al generar un QR puedes indicar de
qué sucursal es el cobro; ese código viaja al banco y ayuda a identificar el
origen de cada pago.

> [!NOTE]
> Esta función se puede apagar en [Configuración de pagos QR](pay-configuracion).
> Si está apagada, el selector de sucursal no aparece al generar un QR aunque
> tengas sucursales registradas.

## Campos

| Campo | Reglas | Para qué sirve |
|-------|--------|----------------|
| **Nombre de la sucursal** | requerido, máx. 150 | Nombre visible (ej. *Sucursal Centro*). |
| **Código (auto-generado)** | solo lectura | Se genera a partir del nombre; es el `branchCode` que se envía al banco. |
| **Ciudad** | — | Ciudad de la sucursal. |
| **Dirección** | — | Dirección física. |
| **WhatsApp** | — | Contacto de la sucursal. |
| **Telegram** | — | Contacto de la sucursal. |

## El código de sucursal

- Tiene **5 caracteres**, en mayúsculas, y se deriva del nombre (ej. *Centro*
  → `CENTR`; si ya existe, `CENT1`).
- Se genera **una sola vez**, al crear la sucursal, y **nunca se regenera**:
  cambiarlo invalidaría los códigos que ya viajaron en QRs emitidos.

> [!WARNING]
> Si necesitas renombrar una sucursal, hazlo por el nombre. El código se
> mantiene para no romper el historial.
