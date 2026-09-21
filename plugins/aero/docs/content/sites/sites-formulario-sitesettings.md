# Formulario: Configuración de sitio

**Ruta:** `Sitio Web → Configuración de sitio`
**Controlador:** `Aero\Sites\Controllers\SiteSettings` (panel del tenant)
**Permiso:** `aero.sites.manage_seo`
**Modelos:** `Tenant`, `ContactConfig`, `SeoConfig`, `Notify\Channel`

Es el centro de configuración del tenant activo. A diferencia de los formularios
basados en `fields.yaml`, aquí un mismo controlador monta varios *Form widgets*
sobre modelos distintos y los organiza en pestañas. Cada pestaña guarda con su
propio handler AJAX, así que se puede guardar una sección sin tocar las demás.

> [!WARNING]
> Si no hay un tenant asociado al sitio activo, el formulario muestra un aviso y
> no renderiza ninguna pestaña. Elige un sitio con tenant en el selector del backend.

## Pestañas

### General

| Campo | Para qué sirve |
|-------|----------------|
| **Rubro del negocio** (`niche_type`) | Afecta el prompt sugerido al generar con IA y las recomendaciones de temas. **No reescribe** el contenido ya generado. |

### Contacto

Datos públicos del negocio. Se guardan en `ContactConfig` (handler
`onSaveContactInfo`).

| Campo | Notas |
|-------|-------|
| **Email de contacto** | Validado como email. |
| **Teléfono** | Texto libre, formato internacional. |
| **WhatsApp** | Con código de país, sin espacios. Se usa para el enlace `wa.me`. |
| **Dirección** | Texto libre. |
| **Latitud / Longitud** | Opcionales; habilitan la ubicación en mapa. Validadas en rango. |

### Formulario

Controla el formulario de contacto del micrositio (`onSaveContactConfig`).

| Campo | Para qué sirve |
|-------|----------------|
| **Formulario de contacto activo** | Interruptor maestro. Apagado, el formulario no aparece. |
| **Mensaje de éxito** | Texto que ve el visitante tras enviar. |

### Mensajes

Lista de solo lectura con los últimos 50 envíos del formulario
(`ContactSubmission`), con estado: **Pendiente**, **Enviado**, **Fallido** o
**Parcial**. Es el histórico de leads del sitio.

### Notificaciones

Administración de los **canales de notificación** del tenant (email, WhatsApp,
Telegram, SMS…). Cada canal define a dónde llegan los avisos del sitio. Ver la
guía [Canales de notificación](sites-canales-notificacion).

> [!NOTE]
> Esta pestaña solo aparece si el plugin **Aero.Notify** está instalado.
> Aero.Sites no lo requiere: es una dependencia blanda.

### SEO

Parámetros de indexación y redes sociales del sitio (`onSaveSeo`). Ver el
detalle en [Configuración SEO](sites-formulario-seoconfig).

## Qué pasa al guardar

- `onSaveGeneral` conserva el valor previo si llega vacío y **solo** actualiza el rubro.
- Los campos vacíos de contacto se normalizan a `null`; lat/lng solo se guardan si son numéricos.
- Los canales con `config` vacío se guardan sin claves nulas.
- La imagen OG usa *deferred binding*: se confirma con `commitDeferred()` al guardar SEO.
