# Estructura del Proyecto

```
/www/wwwroot/micro.clouds.com.bo/
│
├── .env                        # Variables de entorno (NO commitear)
├── .claude/
│   └── commands/               # Skills de Claude Code (ver /docs/skills/)
├── artisan                     # CLI de Laravel
├── composer.json
│
├── app/
│   ├── blueprints/             # Tailor blueprints globales (author, category, post, config)
│   └── ...                     # Providers, Models globales si aplica
│
├── bootstrap/
│   └── app.php                 # Inicialización de Laravel + polyfill mb_split()
│
├── config/                     # Configuración de Laravel
│   ├── app.php
│   ├── cache.php               # driver: redis
│   ├── database.php
│   ├── queue.php               # driver: redis
│   └── session.php             # driver: redis
│
├── docs/                       # ← Esta documentación
│   ├── README.md
│   ├── entorno.md
│   ├── stack.md
│   ├── estructura.md
│   ├── workflows.md
│   ├── convenciones.md
│   └── skills/
│       ├── README.md
│       ├── frontend.md
│       └── backend.md
│
├── modules/                    # Core de OctoberCMS (NO modificar)
│   ├── backend/                # Panel admin
│   ├── cms/                    # Motor CMS
│   ├── editor/
│   ├── media/
│   ├── system/
│   ├── dashboard/
│   └── tailor/                 # CMS headless
│
├── plugins/                    # Plugins de desarrollo
│   └── october/demo/           # Plugin demo de OctoberCMS
│       ├── Plugin.php
│       ├── components/
│       └── ...
│
├── storage/
│   ├── app/                    # Archivos subidos
│   ├── framework/cache/        # Cache de Laravel (fallback)
│   └── logs/
│       └── laravel.log         # Log principal
│
├── themes/
│   └── demo/                   # Tema activo (ACTIVE_THEME=demo)
│       ├── theme.yaml
│       ├── pages/              # Páginas .htm (Twig)
│       ├── layouts/            # Layouts base
│       ├── partials/           # Partials reutilizables
│       ├── content/            # Bloques editables
│       └── assets/
│           ├── css/
│           │   ├── app.css     # Fuente Tailwind
│           │   └── compiled.css # Build final
│           └── js/
│
└── vendor/                     # Composer dependencies (NO modificar)
```

## Estructura de un Plugin

Cuando se crea un plugin con `/october-plugin Micro Blog`:

```
plugins/micro/blog/
├── Plugin.php                  # Registro: componentes, menú, permisos, eventos
├── routes.php                  # Rutas HTTP/API del plugin
├── composer.json               # Dependencias propias del plugin
│
├── components/                 # Componentes CMS (PHP + Twig)
│   └── PostList/
│       ├── PostList.php
│       └── default.htm
│
├── console/                    # Comandos Artisan
│   └── CleanDraftsCommand.php
│
├── controllers/                # Controladores del backend
│   └── Posts/
│       ├── Posts.php
│       ├── config_form.yaml
│       ├── config_list.yaml
│       ├── config_relation.yaml
│       └── _list_toolbar.htm
│
├── events/                     # Clases de eventos Laravel
│   └── PostPublished.php
│
├── http/
│   ├── controllers/api/        # Controladores REST API
│   └── resources/              # JSON Resources/Collections
│
├── jobs/                       # Jobs para la queue
│   └── SendDigestJob.php
│
├── lang/                       # Traducciones
│   └── en/lang.php
│
├── listeners/                  # Listeners de eventos
│   └── SendNotificationListener.php
│
├── mail/                       # Mailables
│   └── PostPublishedMail.php
│
├── models/                     # Modelos Eloquent
│   └── Post/
│       ├── Post.php
│       ├── fields.yaml         # Campos del formulario backend
│       ├── columns.yaml        # Columnas del listado backend
│       └── scopes.yaml         # Filtros del listado
│
├── observers/                  # Model Observers
│   └── PostObserver.php
│
├── subscribers/                # Event Subscribers
│   └── PostActivitySubscriber.php
│
├── updates/                    # Migraciones
│   ├── version.yaml
│   └── create_posts_table.php
│
└── views/mail/                 # Templates de email (Twig)
    └── post_published.htm
```

## Estructura del Tema

```
themes/demo/
├── theme.yaml                  # Metadatos del tema
├── package.json                # Tailwind CLI build
├── tailwind.config.js          # Configuración Tailwind
│
├── blueprints/                 # Tailor blueprints del tema
│   ├── blog/                   # Entry types: author, category, post
│   ├── fields/                 # Mixins de campos reutilizables
│   ├── pages/                  # Blueprints de páginas
│   └── site/                   # Blueprints de configuración global
│
├── pages/                      # Una página = una URL
│   ├── index.htm               # → /
│   ├── about.htm               # → /about
│   ├── contact.htm             # → /contact
│   ├── blog/                   # → /blog y /blog/:slug
│   ├── wiki/                   # → /wiki y entradas wiki
│   ├── api/                    # → rutas de API CMS
│   ├── 404.htm                 # Página de error
│   └── sitemap.htm             # Sitemap XML
│
├── layouts/                    # Scaffolds reutilizables
│   ├── default.htm             # Layout principal
│   ├── home.htm                # Layout homepage
│   ├── blog.htm                # Layout de blog
│   ├── wiki.htm                # Layout de wiki
│   ├── api.htm                 # Layout API
│   └── external.htm            # Layout externo (sin nav)
│
├── partials/                   # Fragmentos Twig
│   ├── site/                   # Header, footer, head (meta, CSS, JS)
│   ├── blog/                   # Partials del blog
│   ├── about/                  # Partials de about
│   ├── wiki/                   # Partials de wiki
│   ├── blocks/                 # Bloques de contenido genéricos
│   ├── controls/               # Controles UI (pagination, etc.)
│   └── elements/               # Elementos UI atómicos (Pines)
│
├── content/                    # Bloques editables desde el admin
└── assets/
    ├── css/
    │   ├── app.css             # @tailwind directives
    │   └── compiled.css        # Output (gitignore en producción)
    ├── js/
    └── images/
```
