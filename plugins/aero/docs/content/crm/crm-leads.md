# Formulario: Leads

**Ruta:** `CRM → Leads`
**Controlador:** `Aero\Crm\Controllers\Leads`
**Modelo:** `Aero\Crm\Models\Lead`
**Permiso:** `aero.crm.manage_leads`

Registra **prospectos** que todavía no son una oportunidad formal. Es el paso
previo al [Pipeline](crm-pipeline): calificas el lead y, si avanza, lo
conviertes en contacto/negocio.

## Campos

| Campo | Reglas | Para qué sirve |
|-------|--------|----------------|
| **Nombre** | requerido | Nombre del prospecto. |
| **Estado** | — | Nuevo, Contactado, Calificado o Descartado. |
| **Email** | — | Correo. |
| **Teléfono** | — | Teléfono. |
| **Empresa** | — | Nombre de la empresa (texto libre). |
| **Origen** | — | De dónde vino (texto libre). |
| **Responsable** | — | Usuario del backend que lo atiende. |

## Estados

```text
nuevo  →  contactado  →  calificado   (pasa al pipeline)
                     └→  descartado   (se archiva)
```

> [!TIP]
> Mantén los leads **nuevos** con poco volumen: contáctelos o descártelos
> pronto para que el listado refleje solo prospectos reales.
