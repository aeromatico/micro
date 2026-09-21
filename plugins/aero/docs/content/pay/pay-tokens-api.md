# Formulario: Tokens de API

**Pantalla:** Tokens de API de Bolivia Pay (`/backend/aero/pay/apitokens`)
**Controlador:** `Aero\Pay\Controllers\ApiTokens`
**Modelo:** `Aero\Pay\Models\ApiToken`
**Permiso:** `aero.pay.manage_accounts`

Emite tokens **Bearer** para la API REST de pagos (`/api/v1/pay` y su alias
`/api/v1/qrbo`). Sirven para que tus sistemas generen y consulten QR sin entrar
al panel.

## Campos

| Campo | Reglas | Para qué sirve |
|-------|--------|----------------|
| **Usuario (referencia)** | opcional | Quién administra el token. El aislamiento real es por sitio. |
| **Nombre** | requerido, máx. 100 | Para qué se usará (ej. *Integración tienda*). |
| **Expira** | opcional | Fecha de vencimiento. Vacío = no expira. |
| **Permisos** | — | Alcance del token (por defecto, `*`). |

## Permisos disponibles

| Permiso | Qué habilita |
|---------|--------------|
| `qrbo.qr.read` | Consultar QR y sus pagos. |
| `qrbo.qr.create` | Generar QR de cobro. |
| `qrbo.qr.cancel` | Anular QR. |
| `*` | Todos los anteriores. |

## Seguridad y ciclo de vida

- El token se genera al crear el registro: **40 caracteres aleatorios**.
- Se guarda **hasheado** (SHA-256) y también cifrado, para poder **revelarlo**
  después desde la pantalla.
- Puedes **Revelar** el token cuando lo necesites o **Regenerar** para
  reemplazarlo; al regenerar, el anterior deja de funcionar de inmediato.

> [!CAUTION]
> Trata el token como una contraseña. Si sospechas que se filtró, regéneralo.

## Endpoints principales

| Método | Ruta | Requiere |
|--------|------|----------|
| `POST` | `/api/v1/pay/qr` | `qrbo.qr.create` |
| `GET` | `/api/v1/pay/qr` | `qrbo.qr.read` |
| `GET` | `/api/v1/pay/qr/{id}` | `qrbo.qr.read` |
| `DELETE` | `/api/v1/pay/qr/{id}` | `qrbo.qr.cancel` |

Se envían con `Authorization: Bearer TU_TOKEN`.

> [!NOTE]
> Si el plugin **Aero.Api** está instalado, estos mismos permisos se publican en
> su gateway y una key suya puede abrir esta área, comportándose igual que un
> token de aquí.

## Webhooks hacia tu sistema

El webhook **saliente** se configura por cuenta bancaria (ver
[Cuentas bancarias](pay-cuentas-bancarias)). Cuando un QR se paga, anula o
vence, se hace un `POST` a tu URL con la firma `X-Pay-Signature`; valídala con
el secreto generado en esa pantalla.

El webhook **entrante** (banco → plataforma) es el que hace instantánea la
confirmación; requiere entregar al banco la URL y el secreto generados en la
cuenta.

> [!TIP]
> Al generar un QR sin `bank_account_id`, se usa la cuenta activa **de
> producción** más antigua del sitio. Una cuenta de sandbox nunca se elige
> sola: pásala explícitamente para usarla.
