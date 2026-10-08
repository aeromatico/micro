Create OctoberCMS 4 Tailor blueprints — the built-in headless CMS content modeling system.

Tailor is OctoberCMS 4's native content builder. No plugin needed for content — define blueprints in YAML, get backend UI + API automatically.

## Usage
`/october-tailor <type> <Handle> [fields...]`

**Types:** `entry`, `global`, `stream`, `mixin`

**Examples:**
- `/october-tailor entry Blog\Post title:string slug:string content:richtext published_at:date`
- `/october-tailor global Site\Settings site_name:string logo:fileupload contact_email:string`
- `/october-tailor stream Store\Order total:decimal status:select customer_name:string`
- `/october-tailor mixin Seo meta_title:string meta_description:text og_image:fileupload`

---

## Blueprint directory

Blueprints go in: `/www/wwwroot/micro.clouds.com.bo/app/blueprints/`

Handle `Blog\Post` → file `app/blueprints/blog/post.yaml`

---

## Blueprint types

### `entry` — Structured content (Blog posts, Products, Pages)

```yaml
handle: Blog\Post
type: entry
name: Post
drafts: true
multisite: false

primaryNavigation:
    label: Blog
    icon: icon-pencil
    order: 200

navigation:
    label: Posts
    icon: icon-file-text

fields:
    title:
        label: Título
        type: text
        required: true

    slug:
        label: Slug
        type: text
        preset:
            field: title
            type: slug

    content:
        label: Contenido
        type: richeditor
        size: huge
        tab: Contenido

    excerpt:
        label: Extracto
        type: textarea
        size: small
        tab: Contenido

    image:
        label: Imagen destacada
        type: fileupload
        mode: image
        tab: Media

    published_at:
        label: Publicar el
        type: datepicker
        mode: datetime
        tab: Configuración

    is_featured:
        label: Destacado
        type: checkbox
        default: false
        tab: Configuración

    # Mixin for SEO (if SEO mixin exists)
    # _seo:
    #     source: Seo

# Include columns for list view
primaryColumn: title
```

### `global` — Site-wide settings (single record)

```yaml
handle: Site\Settings
type: global
name: Configuración del sitio

primaryNavigation:
    label: Configuración
    icon: icon-cog
    order: 999

fields:
    site_name:
        label: Nombre del sitio
        type: text
        required: true

    site_description:
        label: Descripción
        type: textarea
        size: small

    logo:
        label: Logo
        type: fileupload
        mode: image
        imageWidth: 400
        imageHeight: 200

    social_links:
        label: Redes sociales
        type: repeater
        form:
            fields:
                platform:
                    label: Red social
                    type: dropdown
                    options:
                        instagram: Instagram
                        facebook: Facebook
                        twitter: Twitter
                        linkedin: LinkedIn
                url:
                    label: URL
                    type: text
```

### `stream` — Event log / activity feed (append-only)

```yaml
handle: Store\Order
type: stream
name: Pedidos

fields:
    order_number:
        label: Número de pedido
        type: text
        required: true

    customer_name:
        label: Cliente
        type: text

    customer_email:
        label: Email
        type: text

    total:
        label: Total
        type: number

    status:
        label: Estado
        type: dropdown
        options:
            pending: Pendiente
            processing: Procesando
            completed: Completado
            cancelled: Cancelado
        default: pending

    items:
        label: Artículos
        type: repeater
        form:
            fields:
                name:
                    label: Producto
                    type: text
                quantity:
                    label: Cantidad
                    type: number
                price:
                    label: Precio
                    type: number
```

### `mixin` — Reusable field group (include in other blueprints)

```yaml
handle: Seo
type: mixin
name: SEO

fields:
    meta_title:
        label: Meta título
        type: text
        tab: SEO

    meta_description:
        label: Meta descripción
        type: textarea
        size: small
        tab: SEO

    og_image:
        label: Imagen Open Graph
        type: fileupload
        mode: image
        imageWidth: 1200
        imageHeight: 630
        tab: SEO
```

---

## Using Tailor content in CMS pages

In `.htm` page/partial files, access Tailor content via `{% use %}`:

```twig
---
[collection posts]
handle = "Blog\Post"
---

{# Access global settings #}
{% use 'Site\Settings' as settings %}
{{ settings.site_name }}

{# Loop over entry collection #}
{% for post in posts.limit(10).get %}
    <article>
        <h2>{{ post.title }}</h2>
        <p>{{ post.excerpt }}</p>
        <a href="{{ post.slug }}">Leer más</a>
    </article>
{% endfor %}

{# Single entry by slug #}
{% set post = tailor.findEntry('Blog\Post', {slug: :slug}) %}
{{ post.content|raw }}
```

---

## After creating blueprint YAML

Run:
```bash
/www/server/php/84/bin/php artisan october:migrate
```

This creates the necessary database tables automatically.

Report:
1. Blueprint file path created
2. Backend URL to manage content: `https://micro.clouds.com.bo/admin/tailor/...`
3. How to use in CMS pages with code examples
4. Twig variables available
