# Formulario: Automatización de cobranzas

**Ruta:** `CRM → Cobranzas → Automatización` (reglas de recordatorio)
**Controlador:** `Aero\Crm\Controllers\CollectionReminderRules`
**Modelo:** `Aero\Crm\Models\CollectionReminderRule`
**Permiso:** `aero.crm.manage_collections`

Define **cuándo y qué** se envía para perseguir los pagos pendientes. Si hay
alguna regla activa, se usa este esquema en cascada; si no, aplica el modo
simple de [Configuración de CRM](crm-configuracion).

## Campos

| Campo | Reglas | Para qué sirve |
|-------|--------|----------------|
| **Iniciar recordatorio** | requerido | Días antes del vencimiento en que empieza la cobranza: 1, 2, 3, 4, 5, 7 o 10. |
| **Repetir recordatorio** | requerido | Cada cuánto se repite hasta que se registre el pago: 1, 2 o 3 días, semanal, mensual o anual. |
| **Lista** | — | A qué lista aplica. Vacío = **todos los cobros** del sitio. |
| **Regla activa** | — | Si está apagada, se ignora. |
| **Orden** | — | Prioridad de evaluación entre reglas. |
| **Plantilla del mensaje** | — | Texto a enviar. Vacío = usa la plantilla por defecto de la configuración. |

## Variables disponibles

`{{contacto}}`, `{{monto}}`, `{{moneda}}`, `{{concepto}}`, `{{vencimiento}}`.

## Cómo funciona

```text
5 días antes del vencimiento  →  primer recordatorio
        cada N días           →  se repite
        hasta que             →  se marca el pago (o se anula el cobro)
```

- Las reglas se evalúan por **orden**; una regla sin lista aplica a todos los cobros.
- El envío sale por WhatsApp (Aero.Hello); los cobros sin WhatsApp vinculado se omiten.

> [!TIP]
> Para un caso especial (por ejemplo, clientes morosos), crea una regla atada a
> una **lista** con su propia plantilla y ponla con orden prioritario.
