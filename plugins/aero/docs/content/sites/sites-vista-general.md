# El panel Sitio Web

Esta documentación cubre **todo lo que ve el dueño de un micrositio** al entrar
al backend: el panel **Sitio Web**. No incluye la administración de la
plataforma (tenants, dominios raíz, catálogos), que es exclusiva del
superadministrador.

## Cómo se entra

El backend siempre trabaja sobre un **sitio activo**. El dueño no elige un
tenant en cada formulario: el sistema ya sabe cuál es el suyo. Todas las
pantallas de esta guía operan sobre ese sitio.

## Menú

| Sección | Ruta | Para qué sirve |
|---------|------|----------------|
| **Página de inicio** | `Sitio Web → Página de inicio` | Editar la portada: identidad, contenido con IA, plantilla. |
| **Páginas** | `Sitio Web → Páginas` | Crear y editar páginas internas (Sobre nosotros, Servicios…). |
| **Configuración de sitio** | `Sitio Web → Configuración de sitio` | Rubro, contacto, formulario, mensajes, notificaciones y SEO. |

> [!NOTE]
> Si el sitio no está activado, el menú **Sitio Web** se reduce a
> *Configuración de sitio*, que es donde vive el interruptor para reactivarlo.

## Recorrido recomendado

```text
1. Configura el rubro y los datos de contacto
        ▼
2. Elige un tema y ajusta tu identidad visual (logo, colores, tipografía)
        ▼
3. Genera la página de inicio con IA o edítala a mano
        ▼
4. Crea las páginas internas que necesites
        ▼
5. Ajusta SEO y publica
```

## Guías de cada formulario

- [Configuración de sitio](sites-formulario-sitesettings) — el centro de ajustes.
- [Identidad visual (Branding)](sites-formulario-branding) — logo, colores y tipografía.
- [Página de inicio](sites-formulario-inicio) — la portada y la generación con IA.
- [Plantilla del sitio](sites-formulario-plantilla) — header, footer y HTML propio.
- [Páginas](sites-formulario-pagina) — las páginas internas del sitio.
- [Canales de notificación](sites-canales-notificacion) — a dónde llegan los avisos.
- [Configuración SEO](sites-formulario-seoconfig) — cómo te ven los buscadores.
- [Configuración de contacto](sites-formulario-contactconfig) — datos y formulario.

## Permisos que habilitan este panel

| Permiso | Desbloquea |
|---------|-----------|
| `aero.sites.manage_pages` | Página de inicio y Páginas |
| `aero.sites.manage_seo` | Configuración de sitio y SEO |
| `aero.sites.manage_contact` | Datos de contacto y canales |
| `aero.sites.view_submissions` | Ver los mensajes recibidos |
