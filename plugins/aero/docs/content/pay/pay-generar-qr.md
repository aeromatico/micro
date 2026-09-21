# Formulario: Generar código QR

**Ruta:** `Bolivia Pay → Generar código`
**Controlador:** `Aero\Pay\Controllers\QrCodes`
**Modelo:** `Aero\Pay\Models\QrCode`
**Permiso:** `aero.pay.manage_accounts`

Emite un **QR de cobro** por una transacción concreta. El QR se genera contra
el banco y queda registrado con su estado y sus pagos.

> [!NOTE]
> Las cuentas del tipo **Correo** no aparecen aquí: son de QR estático y no
> generan un QR por transacción. Sus pagos se detectan solos desde el correo.

## Campos

| Campo | Reglas | Para qué sirve |
|-------|--------|----------------|
| **Producto** (opcional) | — | Si tu tienda está integrada, preselecciona el monto desde un producto. |
| **Cuenta bancaria** | requerido | Solo cuentas activas del sitio actual. |
| **Monto** | requerido, > 0 | Importe a cobrar. |
| **Moneda** | requerido | `BOB` o `USD` (USD solo si está habilitado). |
| **Descripción del pago** | — | Se agrega después del texto automático. |
| **Fecha de vencimiento** | requerido | Se precarga según la configuración; editable por QR. |
| **Sucursal** | opcional | Identifica el punto de cobro (si está habilitado). |
| **Uso único** | — | Activo: un solo pago. Inactivo: admite varios. |
| **Alerta** | opcional | Envía la imagen del QR por WhatsApp y/o correo al generarlo. |

## Reglas de negocio

- El monto **no es modificable por quien paga**: el QR siempre se emite con
  importe fijo.
- La descripción se construye como `[Sucursal - Facturado]` + tu texto.
- Si el **manejo de impuestos** está activo, se pide el **NIT del cliente** y se
  calcula el impuesto según la configuración:
  - *Monto ya incluye impuesto* → el impuesto se calcula y guarda como referencia.
  - *Monto no incluye* → el impuesto se suma y ese es el total del QR.
- La **sucursal** debe pertenecer al sitio; si el manejo de sucursales está
  apagado, se ignora.

## Alertas al generar

Si cargas un destinatario (WhatsApp y/o correo), te enviamos la imagen del QR
apenas se genera, usando las plantillas del plugin de notificaciones (evento
`pay.qr.generated`). Deja ambos vacíos si solo quieres el QR en pantalla.

> [!TIP]
> El **uso único** es lo habitual para un cobro puntual. Desactívalo solo si
> quieres un QR reutilizable (por ejemplo, un mismo monto para varias personas).
