# Formulario: Configuración SEO

**Ruta:** `Sitio Web → Configuración de sitio → pestaña SEO`
**Controlador:** `Aero\Sites\Controllers\SiteSettings`
**Modelo:** `Aero\Sites\Models\SeoConfig`
**Permiso:** `aero.sites.manage_seo`

Agrupa los parámetros que afectan cómo el sitio aparece en buscadores y redes
sociales. Existe un registro `SeoConfig` por tenant.

## Campos

| Campo | Tipo | Reglas | Para qué sirve |
|-------|------|--------|----------------|
| **Formato del título de página** | text | requerido | Plantilla del `<title>`. |
| **Descripción por defecto** | textarea | — | Se usa cuando la página no tiene descripción propia. |
| **Imagen OG por defecto** | fileupload | 1200×630 | Imagen para redes cuando la página no tiene una. |
| **Google Analytics ID** | text | — | Identificador de medición, ej: `G-XXXXXXXXXX`. |
| **Habilitar sitemap.xml** | checkbox | — | Publica el sitemap de páginas. |
| **Contenido de robots.txt** | codeeditor | — | Reglas de rastreo para buscadores. |

## Formato del título

El campo acepta dos marcadores:

| Marcador | Se reemplaza por |
|----------|------------------|
| `%s` | El título de la página actual. |
| `{name}` | El nombre del sitio. |

```text
%s | {name}   →   Servicios | Clínica Dental Sonrisa
```

El modelo aplica el formato en `buildTitle($pageTitle)`: primero sustituye
`{name}` y luego hace `sprintf` con `%s`. El valor por defecto del sistema es
`%s | {name}`.

> [!WARNING]
> Si el formato no contiene `%s`, el título de la página se descarta. Si no
> contiene `{name}`, el nombre del sitio no aparece.

## Herencia y prioridad de metadatos

Para cada página, el SEO se resuelve por capas: primero los campos propios de la
página (`meta_title`, `meta_description`, `og_image`) y, si están vacíos, los
valores por defecto de este formulario.

> [!TIP]
> Define una descripción por defecto razonable: evita que páginas sin metadatos
> propios aparezcan sin resumen en Google.
