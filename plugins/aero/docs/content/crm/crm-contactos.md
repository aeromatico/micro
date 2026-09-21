# Formulario: Contactos

**Ruta:** `CRM → Contactos`
**Controlador:** `Aero\Crm\Controllers\Contacts`
**Modelo:** `Aero\Crm\Models\Contact`
**Permiso:** `aero.crm.manage_contacts`

Registro de personas. Es la base del CRM: los contactos se vinculan a empresas,
listas, negocios, cobranzas y tickets.

## Pestaña General

| Campo | Para qué sirve |
|-------|----------------|
| **Nombre / Apellido** | Identificación de la persona. |
| **Email / Teléfono** | Datos de contacto. |
| **Redes sociales / mensajería** | Repetidor con cada red y su valor. |
| **Empresa** | Empresa a la que pertenece (solo del mismo sitio). |
| **Responsable** | Usuario del backend que lo atiende. |
| **Origen** | Cómo llegó: sitio web, referido, WhatsApp, redes, feria, llamada en frío, publicidad, compra en tienda u otro. |
| **Cliente de tienda vinculado** | Enlace a un cliente de la tienda (si el email o teléfono coincide se enlaza solo). |
| **Listas** | Listas a las que pertenece, útiles para cobranzas y campañas. |

## Pestaña Acciones

| Sección | Para qué sirve |
|---------|----------------|
| **Acciones Hello** | Atajos de mensajería (WhatsApp) sobre este contacto. |
| **Acciones Shop** | Atajos de la tienda para este contacto. |

> [!NOTE]
> El vínculo con **Cliente de tienda** se resuelve por email o teléfono, pero
> puedes elegirlo a mano si no se detectó solo.

> [!TIP]
> Aprovecha **Listas** desde el inicio: las cobranzas y campañas se organizan
> mucho más rápido agrupando contactos.
