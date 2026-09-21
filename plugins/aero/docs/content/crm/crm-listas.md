# Formulario: Listas

**Ruta:** `CRM → Listas`
**Controlador:** `Aero\Crm\Controllers\ContactLists`
**Modelo:** `Aero\Crm\Models\ContactList`
**Permiso:** `aero.crm.manage_contacts`

Agrupa contactos para organizarlos (por ejemplo, *Clientes VIP*, *Campaña
agosto*, *Pendientes de cobro*). Las listas se usan luego en cobranzas,
campañas y filtros.

## Campos

| Campo | Tipo | Reglas | Para qué sirve |
|-------|------|--------|----------------|
| **Nombre de la lista** | text | requerido | Nombre visible. |
| **Color** | colorpicker | — | Color para reconocerla a simple vista. |
| **Descripción** | textarea | — | Nota sobre su propósito. |
| **Contactos** | relation | — | Contactos incluidos (solo del mismo sitio). |

> [!TIP]
> Usa colores distintos por tipo de lista (cobranzas en rojo, clientes en
> verde, prospectos en azul) para leerlas de un vistazo.
