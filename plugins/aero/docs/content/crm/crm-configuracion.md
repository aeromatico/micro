# Formulario: Configuración de CRM

**Ruta:** `CRM → Configuración de CRM`
**Controlador:** `Aero\Crm\Controllers\CrmSettings`
**Modelo:** `Aero\Crm\Models\CrmSettings`
**Permiso:** `aero.crm.manage_settings`

Activa el CRM para tu sitio y ajusta el comportamiento de las cobranzas.

## Campos

| Campo | Para qué sirve |
|-------|----------------|
| **CRM activado** | Interruptor maestro. Al activarlo se crea el **pipeline de ventas por defecto** del sitio. |
| **Recordatorios automáticos de cobranza activados** | Corta general del módulo de cobranzas. |
| **Días entre recordatorios (modo simple)** | Solo se usa si en *Automatización* no hay ninguna regla activa. |
| **Plantilla del mensaje** | Mensaje por defecto (y respaldo de las reglas que no definan una). |
| **Cuenta bancaria para cobranzas (QRBO)** | Si la eliges, cada recordatorio incluye el QR de pago. Se administra en Bolivia Pay → Cuentas. |

## Variables de la plantilla

`{{contacto}}`, `{{monto}}`, `{{moneda}}`, `{{concepto}}`, `{{vencimiento}}`.

## Modo simple vs. cascada

| Modo | Cuándo aplica |
|------|---------------|
| **Simple** | No hay reglas activas en *Automatización*: repite un único recordatorio cada *N* días mientras el cobro siga vencido. |
| **Cascada** | Hay reglas activas: se usa el calendario y las plantillas de cada regla (ver [Automatización de cobranzas](crm-automatizacion-cobranzas)). |

> [!NOTE]
> Con una cuenta bancaria **con API** el cobro se marca pagado solo al detectar
> el pago. Con una cuenta **estática** (sin API), la confirmación queda a mano.

> [!TIP]
> Si el CRM está apagado, el menú de clientes (Empresas, Contactos, Leads,
> Pipeline, etc.) no aparece. La sección *Tickets* y *Departamentos* puede
> seguir disponible.
