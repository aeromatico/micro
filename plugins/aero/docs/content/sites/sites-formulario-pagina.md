# Formulario: Páginas

**Ruta:** `Sitio Web → Páginas`
**Controlador:** `Aero\Sites\Controllers\Pages`
**Modelo:** `Aero\Sites\Models\Page`
**Permiso:** `aero.sites.manage_pages`

Crea y edita las páginas internas del sitio (por ejemplo *Sobre nosotros*,
*Servicios*, *Contacto*). La **página de inicio** (slug vacío) no se edita aquí:
tiene su propio flujo con IA en *Contenidos*.

## Pestaña Contenido

| Campo | Tipo | Reglas | Para qué sirve |
|-------|------|--------|----------------|
| **Tipo de contenido** | balloon-selector | `puck` / `richeditor` / `code` | Determina qué editor se muestra debajo. |
| **Título** | text | requerido, 2–200 | Título de la página (también base del slug). |
| **Slug (URL)** | text | opcional, `alpha_dash` | Ruta pública. **Vacío = homepage**. |
| **Layout** | dropdown | requerido | Plantilla visual: `default`, `home`, `full`, `landing`, `contact`. |

### Editores según el tipo de contenido

| Tipo | Campo visible | Descripción |
|------|---------------|-------------|
| **Editor Visual** (`puck`) | `puck_data` | Editor de bloques arrastrables, con generación por IA. |
| **Editor HTML** (`richeditor`) | `content_richeditor` | Redactor de texto enriquecido. |
| **Código** (`code`) | `content_raw` | HTML propio, se guarda y sirve tal cual. |

> [!NOTE]
> `content_richeditor` y `content_raw` escriben sobre la misma columna `content`
> mediante `valueFrom`. El editor visual guarda su estructura en `puck_data` y el
> HTML resultante en `content`.

### Publicación y SEO

| Campo | Pestaña | Para qué sirve |
|-------|---------|----------------|
| **Publicado** | Contenido | Si está apagado, la página no se sirve. |
| **Imagen destacada (OG)** | SEO | Imagen 1200×630 para redes sociales. |
| **Meta título** | SEO | Vacío = usa el título de la página. |
| **Meta descripción** | SEO | Resumen para buscadores. |

## Comportamiento

- El listado se **acota al tenant activo** y **excluye el slug vacío** (la home).
- Al crear, el `tenant_id` se inyecta automáticamente.
- Al guardar, `formBeforeSave()` copia el contenido correcto a `content` según
  el modo elegido, de modo que el front siempre lee una sola columna.
- Ordenable: el orden define la secuencia en menús y en la navegación del sitio.

> [!WARNING]
> El slug es la URL pública de la página. Cambiarlo rompe enlaces existentes.
