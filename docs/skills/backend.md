# Skills de Backend

Stack: **OctoberCMS 4 + Laravel 12 + PHP 8.4 + MySQL + Redis**

---

## /october-plugin

Crea el scaffold completo de un nuevo plugin.

**Archivo:** `.claude/commands/october-plugin.md`

### Sintaxis
```
/october-plugin <Vendor> <PluginName> [descripción]
```

### Ejemplos
```
/october-plugin Micro Blog "Blog con posts, categorías y tags"
/october-plugin Micro Ecommerce "Tienda online con carrito y pedidos"
/october-plugin Micro Crm "CRM básico para gestión de clientes"
```

### Qué crea
```
plugins/{vendor}/{plugin}/
├── Plugin.php          — Clase principal (registros, hooks, schedule)
├── updates/
│   └── version.yaml    — Registro de migraciones
└── lang/en/lang.php    — Traducciones base
```

### Post-creación
Inmediatamente después usa `/october-crud` para agregar modelos o `/october-tailor` si el contenido es gestionado por el cliente.

---

## /october-crud

**El skill más potente.** Crea un CRUD completo de backend en un solo comando.

**Archivo:** `.claude/commands/october-crud.md`

### Sintaxis
```
/october-crud <Vendor>/<Plugin> <ModelName> [campo:tipo ...]
```

### Tipos de campo soportados

| Tipo | Widget backend | Columna lista |
|------|---------------|---------------|
| `string` | text | text |
| `text` / `longtext` | textarea | text |
| `richtext` | richeditor | text |
| `markdown` | markdown | text |
| `number` / `integer` | number | number |
| `decimal` | number (step 0.01) | number |
| `boolean` / `checkbox` | checkbox | switch |
| `datepicker` / `datetime` | datepicker (datetime) | datetime |
| `date` | datepicker (date) | date |
| `color` | colorpicker | text |
| `image` / `fileupload` | fileupload (image) | image |
| `files` | fileupload (multi) | — |
| `tags` | taglist | text |
| `relation` | relation widget | via relation |
| `select` | dropdown | text |
| `repeater` | repeater | — |
| `code` | codeeditor | — |

### Ejemplos
```
/october-crud Micro/Blog Post \
  title:string slug:string content:richtext \
  published_at:datepicker is_featured:checkbox image:fileupload

/october-crud Micro/Ecommerce Product \
  name:string sku:string price:decimal stock:number \
  description:richtext image:fileupload is_active:checkbox \
  category:relation tags:tags

/october-crud Micro/Events Event \
  title:string starts_at:datepicker ends_at:datepicker \
  location:string capacity:number description:text image:fileupload
```

### Qué crea (15+ archivos)
```
plugins/{vendor}/{plugin}/
├── models/{Model}.php                      — Eloquent model completo
├── models/{model}/
│   ├── fields.yaml                         — Form fields backend
│   ├── columns.yaml                        — List columns
│   └── scopes.yaml                         — Filtros del listado
├── controllers/{Models}.php                — Controller con behaviors
├── controllers/{models}/
│   ├── config_form.yaml                    — Formulario CRUD
│   ├── config_list.yaml                    — Listado con search
│   └── _list_toolbar.htm                   — Botones crear/eliminar
└── updates/
    ├── version.yaml                        — Actualizado
    └── create_{table}_table.php            — Migración
```

Y actualiza `Plugin.php` con navegación y permisos.

---

## /october-model

Crea solo el modelo + migración + controller base (sin el CRUD completo).

**Archivo:** `.claude/commands/october-model.md`

### Cuándo usar
- Cuando ya tienes el plugin y quieres agregar un modelo más simple
- Cuando prefieres configurar manualmente los fields/columns YAML
- Modelos de soporte (pivot, log) que no necesitan UI completa

### Sintaxis
```
/october-model <Vendor>/<Plugin> <ModelName> [campo:tipo ...]
```

---

## /october-component

Crea un componente CMS (PHP + template Twig) dentro de un plugin.

**Archivo:** `.claude/commands/october-component.md`

### Cuándo usar
Los componentes son la forma de llevar lógica PHP a las páginas del tema. Se declaran en la cabecera de la página y se renderizan en el Twig.

### Sintaxis
```
/october-component <Vendor>/<Plugin> <ComponentName> [descripción]
```

### Ejemplos
```
/october-component Micro/Blog PostList "Lista paginada de posts por categoría"
/october-component Micro/Blog FeaturedPost "Post destacado para la homepage"
/october-component Micro/Ecommerce CartWidget "Widget del carrito en el header"
/october-component Micro/Ecommerce ProductFilter "Filtros Ajax para catálogo"
```

### Qué crea
```
plugins/{vendor}/{plugin}/components/
├── {ComponentName}.php        — Clase con defineProperties(), onRun(), handlers AJAX
└── {componentname}/
    └── default.htm            — Template Twig del componente
```

Y registra el componente en `Plugin.php`.

### Uso en página .htm
```
[microBlogPostList]
postsPerPage = 10
category = "{{ :category }}"
==
{% component 'microBlogPostList' %}
```

---

## /october-relation

Agrega relaciones entre modelos y configura el backend RelationController.

**Archivo:** `.claude/commands/october-relation.md`

### Sintaxis
```
/october-relation <Vendor>/<Plugin> <ParentModel> <tipo> <ChildModel> [--pivot-fields campo:tipo]
```

### Tipos de relación

| Tipo | Descripción | Migración |
|------|-------------|-----------|
| `hasMany` | Un Post tiene muchos Comments | FK en tabla hija |
| `hasOne` | Un User tiene un Profile | FK en tabla hija |
| `belongsTo` | Comment pertenece a Post | FK en tabla actual |
| `belongsToMany` | Post ↔ Category (N:M) | Tabla pivot |
| `attachOne` | Post tiene una imagen (File) | system_files |
| `attachMany` | Post tiene muchas imágenes | system_files |
| `morphMany` | Polimórfico (tags, comments) | morphs |

### Ejemplos
```
/october-relation Micro/Blog Post hasMany Comment
/october-relation Micro/Blog Post belongsToMany Category --pivot-fields sort_order:integer
/october-relation Micro/Blog Post attachOne FeaturedImage
/october-relation Micro/Blog Post attachMany Gallery
/october-relation Micro/Ecommerce Product hasMany ProductVariant
```

### Qué crea/modifica
- Agrega `$hasMany`, `$belongsTo`, etc. al modelo
- Agrega la relación inversa al modelo hijo
- Crea migración de tabla pivot (para belongsToMany)
- Agrega `RelationController` behavior al controller padre
- Crea `config_relation.yaml` con la configuración del widget

---

## /october-api

Crea un endpoint REST API completo con Controller, Resource y Collection.

**Archivo:** `.claude/commands/october-api.md`

### Sintaxis
```
/october-api <Vendor>/<Plugin> <ResourceName> [methods] [--auth|--public]
```

### Ejemplos
```
/october-api Micro/Blog Post index,show --public
/october-api Micro/Ecommerce Product index,show,store,update,destroy --auth
/october-api Micro/Orders Order index,store --auth
```

### Métodos disponibles
`index`, `show`, `store`, `update`, `destroy`

### Qué crea
```
plugins/{vendor}/{plugin}/
├── routes.php                              — Rutas bajo api/v1/
├── http/controllers/api/
│   └── {Resource}Controller.php           — Con search, filter, sort, paginate
└── http/resources/
    ├── {Resource}Resource.php             — Transforma un modelo a JSON
    └── {Resource}Collection.php           — Lista paginada con meta
```

### Respuesta paginada estándar
```json
{
    "data": [{ "id": 1, "title": "..." }],
    "meta": { "current_page": 1, "last_page": 5, "per_page": 15, "total": 73 }
}
```

---

## /october-tailor

Crea blueprints de Tailor — el CMS headless nativo de OctoberCMS 4.

**Archivo:** `.claude/commands/october-tailor.md`

### Cuándo usar Tailor vs Plugin
- **Tailor**: contenido que gestiona el cliente (blog, páginas, configuración del sitio)
- **Plugin**: lógica de negocio, integraciones, funcionalidad custom

### Sintaxis
```
/october-tailor <tipo> <Handle> [campos...]
```

### Tipos

| Tipo | Uso | Registros |
|------|-----|-----------|
| `entry` | Contenido estructurado (posts, productos) | Múltiples |
| `global` | Configuración del sitio | Uno solo |
| `stream` | Feed de eventos (pedidos, logs) | Append-only |
| `mixin` | Grupo de campos reutilizable | — (se incluye) |

### Ejemplos
```
/october-tailor entry Blog\Post title:string slug:string content:richtext published_at:date image:fileupload
/october-tailor global Site\Settings site_name:string logo:fileupload footer_text:textarea
/october-tailor stream Store\Order total:decimal status:select customer_name:string
/october-tailor mixin Seo meta_title:string meta_description:text og_image:fileupload
```

### Uso en páginas Twig
```twig
{# Colección de entries #}
[collection posts]
handle = "Blog\Post"
==
{% for post in posts.where('is_active', true).limit(10).get %}
    <h2>{{ post.title }}</h2>
{% endfor %}

{# Global settings #}
{% use 'Site\Settings' as settings %}
<title>{{ settings.site_name }}</title>
```

---

## /october-event

Sistema de eventos completo: crear eventos, listeners, subscribers y hooks del core.

**Archivo:** `.claude/commands/october-event.md`

### Sintaxis
```
/october-event <Vendor>/<Plugin> <action> <EventName> [ListenerName]
```

### Acciones

| Acción | Descripción |
|--------|-------------|
| `fire` | Crea clase de evento dispatchable |
| `listen` | Crea listener (con ShouldQueue para Redis) |
| `subscribe` | Crea subscriber que agrupa múltiples listeners |
| `hook` | Muestra cómo hacer hook en eventos del core CMS |

### Ejemplos
```
/october-event Micro/Blog fire PostPublished
/october-event Micro/Blog listen PostPublished NotifySubscribersListener
/october-event Micro/Blog subscribe PostActivity
/october-event Micro/Blog hook cms.page.beforeDisplay
```

### Hooks del core más útiles
- `cms.page.beforeDisplay` — antes de renderizar una página
- `cms.page.postprocess` — modificar HTML de salida
- `backend.list.extendColumns` — agregar columnas a cualquier listado
- `backend.form.extendFields` — agregar campos a cualquier formulario
- `model.{vendor}.{plugin}.{model}.afterSave` — post-save de un modelo

---

## /october-job

Crea un Job para la queue de Redis.

**Archivo:** `.claude/commands/october-job.md`

### Sintaxis
```
/october-job <Vendor>/<Plugin> <JobName> [--scheduled "cron"] [--chain]
```

### Ejemplos
```
/october-job Micro/Blog SendWeeklyDigest --scheduled "0 8 * * 1"
/october-job Micro/Ecommerce ProcessOrder
/october-job Micro/Media OptimizeImages
```

### Qué crea
- `jobs/{JobName}.php` con `ShouldQueue`, reintentos, timeout, `failed()`
- Opcional: `console/{JobName}Command.php` para dispatch manual
- Opcional: registro en `registerSchedule()` con expresión cron
- Config de supervisor para producción (`/etc/supervisor/conf.d/`)

### Worker en producción
```bash
/www/server/php/84/bin/php artisan queue:work redis \
  --sleep=3 --tries=3 --timeout=60 --max-time=3600
```

---

## /october-mail

Crea Mailable + template Twig + registro en Plugin.php.

**Archivo:** `.claude/commands/october-mail.md`

### Sintaxis
```
/october-mail <Vendor>/<Plugin> <MailName> [--queue]
```

### Ejemplos
```
/october-mail Micro/Blog PostPublishedNotification --queue
/october-mail Micro/Ecommerce OrderConfirmation --queue
/october-mail Micro/Auth PasswordReset
```

### Qué crea
- `mail/{MailName}.php` — Mailable con `ShouldQueue` si `--queue`
- `views/mail/{mailname}.htm` — Template Twig (HTML + plain text)
- Registro en `Plugin.php::registerMailTemplates()`

### Envío
```php
// Inmediato
Mail::to($user)->send(new OrderConfirmation($order));

// Queued
Mail::to($user)->queue(new OrderConfirmation($order));

// OctoberCMS helper
\Mail::sendTo($email, 'micro.ecommerce::order_confirmation', $data);
```

---

## /october-scope

Agrega scopes Eloquent, filtros de backend, cache Redis, o Model Observers.

**Archivo:** `.claude/commands/october-scope.md`

### Sintaxis
```
/october-scope <Vendor>/<Plugin> <ModelName> <tipo> [opciones]
```

### Tipos

| Tipo | Descripción |
|------|-------------|
| `scope` | Local query scopes en el modelo |
| `filter` | Filtros en el listado del backend (scopes.yaml) |
| `search` | Scope de búsqueda fulltext |
| `cache` | Cache Redis de queries costosas con invalidación |
| `observer` | Model Observer para lifecycle hooks |

### Ejemplos
```
/october-scope Micro/Blog Post scope published,recent,featured
/october-scope Micro/Blog Post filter status,date_range,category
/october-scope Micro/Blog Post search title,content,tags
/october-scope Micro/Blog Post cache
/october-scope Micro/Blog Post observer
```

---

## /october-command

Crea un comando Artisan con progress bar y scheduling opcional.

**Archivo:** `.claude/commands/october-command.md`

### Sintaxis
```
/october-command <Vendor>/<Plugin> <CommandName> <signature> [--scheduled "cron"]
```

### Ejemplos
```
/october-command Micro/Blog CleanOldDrafts "blog:clean-drafts {--days=30}" --scheduled "0 3 * * *"
/october-command Micro/Ecommerce SyncInventory "shop:sync-inventory {source}"
/october-command Micro/Reports GenerateMonthly "reports:monthly"
```

### Qué crea
- `console/{CommandName}.php` con signature, progress bar, tabla resumen, `failed()`
- Registro en `Plugin.php::register()`
- Si `--scheduled`: entrada en `registerSchedule()` con `withoutOverlapping()`

### Verificar cron del sistema
```bash
# El scheduler de Laravel debe correr cada minuto
crontab -l | grep artisan
# Si falta:
# * * * * * /www/server/php/84/bin/php /www/wwwroot/micro.clouds.com.bo/artisan schedule:run >> /dev/null 2>&1

# Ver tareas programadas
/www/server/php/84/bin/php artisan schedule:list
```
