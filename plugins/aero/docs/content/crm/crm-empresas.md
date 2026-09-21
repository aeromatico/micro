# Formulario: Empresas

**Ruta:** `CRM → Empresas`
**Controlador:** `Aero\Crm\Controllers\Companies`
**Modelo:** `Aero\Crm\Models\Company`
**Permiso:** `aero.crm.manage_companies`

Registro de organizaciones (clientes, proveedores, aliados). Sirve como eje
para asociar contactos y negocios.

## Campos

| Campo | Tipo | Reglas | Para qué sirve |
|-------|------|--------|----------------|
| **Nombre** | text | requerido | Nombre de la empresa. |
| **Sitio web** | text | — | URL del sitio. |
| **Rubro** | text | — | Industria o sector. |
| **Teléfono** | text | — | Teléfono de contacto. |
| **Dirección** | text | — | Dirección física. |
| **Redes sociales / mensajería** | repeater | — | WhatsApp, Telegram, redes, etc., con su número/usuario/URL. |
| **Responsable** | dropdown | — | Usuario del backend a cargo de la empresa. |

## Redes sociales

El campo *Redes sociales / mensajería* es un repetidor: agregas cada red
(WhatsApp, Telegram, Instagram, Facebook, LinkedIn, Twitter/X, TikTok, YouTube,
Sitio web u **Otro**) con su valor. Se usa para saber por dónde contactar a la
empresa.

> [!TIP]
> Asignar un **Responsable** facilita filtrar y repartir la cartera. Solo
> aparecen usuarios del equipo del sitio.
