# Stack Tecnológico

## Backend

| Capa | Tecnología | Versión |
|------|-----------|---------|
| CMS | OctoberCMS | 4.x (october/rain ^4.2) |
| Framework | Laravel | 12.62.x |
| PHP | PHP-FPM | 8.4.17 |
| Base de datos | MySQL | via AApanel |
| Cache | Redis | 7.4.7 |
| Sessions | Redis | 7.4.7 |
| Queue | Redis | driver `redis` |

## Frontend

| Capa | Tecnología | Versión |
|------|-----------|---------|
| CSS | Tailwind CSS | ^3.4 |
| Interactividad | Alpine.js | ^3.x (CDN) |
| Componentes UI | Pines (thedevdojo) | latest |
| Templating | Twig | (via OctoberCMS) |
| Build tool | Tailwind CLI | via npx |

## Arquitectura OctoberCMS 4

```
OctoberCMS 4
├── Módulos del core (/modules)
│   ├── system/     — Core, migraciones, configuración
│   ├── backend/    — Panel administrativo
│   ├── cms/        — Motor de páginas/temas
│   ├── editor/     — Editor visual
│   ├── media/      — Gestor de medios
│   ├── dashboard/  — Dashboard admin
│   └── tailor/     — CMS headless nativo (blueprints)
│
├── Plugins (/plugins/vendor/name)
│   └── Plugin.php  — Punto de entrada de cada plugin
│
└── Themes (/themes/name)
    ├── pages/      — Páginas (.htm con Twig)
    ├── layouts/    — Layouts base
    ├── partials/   — Componentes reutilizables
    ├── content/    — Bloques de contenido editables
    └── assets/     — CSS, JS, imágenes
```

## Principios de desarrollo

### Backend
- **Plugins** para toda lógica de negocio — nunca modificar el core
- **Tailor Blueprints** para contenido estructurado sin código PHP
- **Jobs + Redis** para tareas pesadas o diferidas
- **Events/Listeners** para desacoplar acciones del sistema
- **Model Observers** para lifecycle hooks limpios
- **API Resources** para cualquier endpoint REST

### Frontend
- **100% Pines** (Tailwind + Alpine.js) — sin Bootstrap, sin jQuery
- Alpine.js para interactividad reactiva en el cliente
- Twig para lógica de presentación y datos del servidor
- AJAX nativo de OctoberCMS (`data-request`) para formularios
- Tailwind CLI para compilar en producción (nunca CDN de Tailwind en prod)

## Convenciones de nombrado

### Plugins
- Directorio: `plugins/vendor/plugin/` (todo lowercase)
- Namespace PHP: `Vendor\Plugin\` (PascalCase)
- Ejemplo: `plugins/micro/blog/` → `Micro\Blog\`

### Modelos
- Singular PascalCase: `Post`, `Category`, `OrderItem`
- Tabla: `{vendor}_{plugin}_{model_plural}` → `micro_blog_posts`

### Componentes CMS
- Tag de uso: `{vendorPlugin}{Component}` → `microBlogPostList`

### Rutas API
- Prefijo: `api/v1/`
- Recursos en plural snake_case: `api/v1/blog-posts`, `api/v1/order-items`

## Decisiones técnicas tomadas

| Decisión | Razón |
|----------|-------|
| PHP 8.4 | Versión más reciente, mejor rendimiento |
| Redis para cache y sessions | Mejor rendimiento vs file storage |
| Pines sobre otros UI frameworks | Sin dependencias JS, Tailwind puro, Alpine.js nativo |
| Tailor para contenido CMS | No requiere código PHP para gestionar contenido |
| Queue con Redis | Ya disponible en el servidor, sin infra extra |
| Cloudflare como proxy | SSL automático, CDN, protección DDoS |

## Compatibilidad notable

- `mb_split()` removida en PHP 8.0 — polyfill en `bootstrap/app.php`
- Redis extension compilada via PECL (no disponible en apt para PHP 8.4)
- PHP CLI usa `php-cli.ini` separado de FPM — ambos tienen `extension=redis.so`
