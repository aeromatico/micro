# Formulario: Cobranzas

**Ruta:** `CRM → Cobranzas`
**Controlador:** `Aero\Crm\Controllers\Collections`
**Modelo:** `Aero\Crm\Models\CollectionItem`
**Permiso:** `aero.crm.manage_collections`

Registra **cobros pendientes** y da seguimiento a su pago, con recordatorios
automáticos o manuales. Cada cobro puede tener varios destinatarios.

## Campos

| Campo | Reglas | Para qué sirve |
|-------|--------|----------------|
| **Listas (opcional)** | — | Organiza el cobro en listas. **No** define quién recibe el recordatorio. |
| **Contactos** | — | Personas que reciben el recordatorio (una o varias). |
| **Concepto** | requerido | Qué se cobra (ej. *Mensualidad de agosto*). |
| **Monto** | requerido | Importe. |
| **Moneda** | — | BOB o USD. |
| **Fecha de vencimiento** | requerido | Cuándo vence. |
| **Estado** | — | Pendiente, Pagado o Anulado. |
| **Notas** | — | Observaciones internas. |
| **Último recordatorio enviado** | solo lectura | Fecha del último aviso. |
| **Recordatorios enviados** | solo lectura | Cuántos se mandaron. |

## Acciones del listado

| Acción | Qué hace |
|--------|----------|
| **Marcar como pagado** | Marca los cobros seleccionados como pagados. |
| **Enviar recordatorio** | Manda el recordatorio ahora (WhatsApp vía Hello). |
| **Generar QR** | Genera/regenera el QR de cobro contra la cuenta configurada. |

El envío manual omite los cobros cuyos contactos no tengan WhatsApp vinculado.

```text
pendiente  →  pagado   (se registra el pago)
      └─────→  anulado  (se deja sin efecto)
```

> [!NOTE]
> El QR de cobro se emite contra la **cuenta bancaria para cobranzas** elegida
> en [Configuración de CRM](crm-configuracion). Con una cuenta con API, el cobro
> se marca pagado solo; con una estática, lo confirmas tú.

> [!TIP]
> Si quieres que los recordatorios salgan solos según un calendario, configura
> reglas en [Automatización de cobranzas](crm-automatizacion-cobranzas).
