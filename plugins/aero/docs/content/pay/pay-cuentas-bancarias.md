# Formulario: Cuentas bancarias

**Ruta:** `Bolivia Pay → Cuentas`
**Controlador:** `Aero\Pay\Controllers\BankAccounts`
**Modelo:** `Aero\Pay\Models\BankAccount`
**Permiso:** `aero.pay.manage_accounts`

Conecta un comercio o cuenta con la que vas a cobrar. Cada cuenta es de un tipo
distinto y define cómo se generan y confirman los pagos.

## Pestaña Configuración

| Campo | Reglas | Para qué sirve |
|-------|--------|----------------|
| **Etiqueta** | requerido | Nombre para identificarla (ej. *Cuenta principal BNB*). |
| **Banco** | requerido | Proveedor: BNB, Banco Económico, Correo, PayPal o NOWPayments. |
| **Entorno** | requerido | `Sandbox / Certificación` o `Producción`. |
| **Estado** | — | `Activa` o `Inactiva`. Solo las activas se pueden usar para generar QR. |
| **Credenciales (JSON)** | — | Llaves propias de cada proveedor (abajo). |

> [!WARNING]
> Una cuenta en **sandbox** nunca se elige sola como cuenta por defecto. Para
> usarla hay que indicarla a propósito.

### Credenciales según el proveedor

| Proveedor | Claves del JSON |
|-----------|-----------------|
| **BNB** | `accountId`, `authorizationId`, `destinationAccountId`; opcional `auth_base_url`, `qr_base_url`. |
| **Banco Económico** | `username`, `password`, `aes_key`, `account_credit`; opcional `base_url`. |
| **PayPal** | `client_id`, `client_secret`, `mode` (`sandbox`/`live`), `webhook_id`. |
| **NOWPayments** | `api_key`, `ipn_secret`. |
| **Correo** | Usa los campos del formulario, no el JSON (excepto *Patrón manual*). |

> [!NOTE]
> `webhook_secret` (opcional) valida el webhook de pago **entrante**. El
> `branchCode` de Banco Económico no va aquí: es por transacción (ver
> [Sucursales](pay-sucursales)).

### Si el proveedor es "Correo (sin API de banco)"

Cobras con tu **QR fijo** (interbancario o personal) y el sistema detecta los
pagos leyendo el correo del banco.

| Campo | Para qué sirve |
|-------|----------------|
| **Banco / billetera** | Catálogo de plantillas de correo. *Patrón manual* si no está. |
| **Correo Gmail** | Cuenta que recibe los avisos del banco. |
| **App Password de Gmail** | Contraseña de aplicación de 16 caracteres (requiere 2FA). |
| **Imagen del QR** | El QR fijo que se muestra a todos los pagadores. |
| **Vencimiento del QR** | Si el QR impreso caduca, la fecha para renovarlo. |
| **Instrucciones si no se detecta el pago** | Qué hacer cuando el correo no llega (ej. registrar el pago a mano). |

## Pestaña Webhooks

Dos webhooks distintos, no confundir:

| Dirección | Para qué sirve |
|-----------|----------------|
| **Entrante (banco → tú)** | Confirma el pago al instante. Se genera un **secreto** y se le entrega al banco junto con la URL. |
| **Saliente (tú → tu sistema)** | Envía un POST a tu URL cuando un QR se paga, anula o vence. Se valida con la firma `X-Pay-Signature`. |

- **Webhook saliente — URL de tu sistema**: a dónde notificamos.
- Sin el secreto entrante, la confirmación depende de la conciliación periódica (puede tardar un poco).

## Pestaña Transacciones

Historial de pagos de esa cuenta y, en cuentas de correo/QR estático, el botón
para **registrar un pago manual** (ver [Transacciones](pay-transacciones)).

## Acciones

| Acción | Qué hace |
|--------|----------|
| **Probar conexión** | Consulta al proveedor y confirma que las credenciales funcionan. |
| **Generar secreto de webhook entrante** | Crea `webhook_secret` si aún no existe. |
| **Generar secreto de webhook saliente** | Crea el secreto para firmar tus notificaciones. |

> [!CAUTION]
> Las credenciales se guardan **cifradas**. Si editas el JSON, revisa no borrar
> claves existentes: el formulario reemplaza el contenido completo.
