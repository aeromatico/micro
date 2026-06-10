# Convenciones del Proyecto

## PHP / OctoberCMS

### Nombrado
| Elemento | Convención | Ejemplo |
|----------|-----------|---------|
| Plugin namespace | `Vendor\Plugin` | `Micro\Blog` |
| Directorio plugin | `vendor/plugin` (lowercase) | `micro/blog` |
| Modelos | PascalCase singular | `Post`, `BlogCategory` |
| Tablas | `vendor_plugin_model_plural` | `micro_blog_posts` |
| Controladores backend | PascalCase plural | `Posts`, `BlogCategories` |
| Componentes CMS | PascalCase | `PostList`, `FeaturedPosts` |
| Tag de componente | `vendorPluginComponent` (camelCase) | `microBlogPostList` |
| Jobs | Verbo + Nombre | `ProcessOrder`, `SendDigest` |
| Events | PastTense sustantivo | `PostPublished`, `OrderPlaced` |
| Listeners | Acción + Target | `SendNotificationEmail`, `UpdateInventory` |
| Mail | Nombre descriptivo | `OrderConfirmation`, `WelcomeEmail` |
| Commands | Verbo + Nombre | `CleanDrafts`, `SyncInventory` |
| Artisan signature | `vendor:action` | `blog:clean-drafts` |

### Modelos — Estructura obligatoria

```php
class Post extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    public $table = 'micro_blog_posts';
    protected $guarded = [];
    protected $dates = ['deleted_at', 'published_at'];
    public $rules = [
        'title' => 'required|max:255',
        'slug'  => 'required|unique:micro_blog_posts,slug',
    ];
    // Relations, scopes, accessors...
}
```

### Plugin.php — Métodos a implementar siempre

```php
public function pluginDetails(): array     // Obligatorio
public function registerComponents(): array // Si tiene componentes CMS
public function registerNavigation(): array // Si tiene backend UI
public function registerPermissions(): array// Si tiene backend UI
public function registerMailTemplates(): array // Si envía emails
public function registerSchedule($schedule): void // Si tiene tareas cron
public function boot(): void               // Events, Observers, Hooks
```

## Twig / CMS Pages

### Estructura de página .htm

```
title = "Título"
url = "/url"
layout = "default"
meta_title = "SEO title"
meta_description = "SEO description"

[componentTag]
property = "value"
==
<?php
// PHP opcional — $this->page['var'] = valor;
?>
==
{{-- Contenido Twig aquí --}}
```

### Reglas Twig
- Usar `{{ variable }}` para output escapado (siempre)
- Usar `{{ variable|raw }}` solo para HTML confiable (richtext de la DB)
- Usar `{% partial 'nombre' param=value %}` para incluir partials
- Usar `{% use 'Tailor\Handle' as alias %}` para contenido Tailor
- Variables de página: `{{ this.page.title }}`, `{{ this.theme.name }}`
- Assets: `{{ 'assets/css/compiled.css'|theme }}` (genera URL con versión)

## Alpine.js / Pines

### Reglas Alpine
- `x-data` siempre en el elemento contenedor raíz del componente
- `x-cloak` en elementos ocultos por defecto — requiere `[x-cloak] { display: none }` en CSS
- Estado complejo → extraer a `Alpine.data('nombre', () => ({...}))` en un archivo JS
- No mezclar lógica de negocio en Alpine — solo estado UI
- Nombres de estado en camelCase: `isOpen`, `activeTab`, `searchQuery`

### Estructura de componente Pines

```html
<div x-data="{
    // Estado inicial
    open: false,
    items: [],
    
    // Métodos
    toggle() { this.open = !this.open; },
    
    // Computed (getters)
    get count() { return this.items.length; }
}" class="...">

    {{-- Template Twig + Alpine directives --}}
    <button @click="toggle()" :class="open ? 'active' : ''">
        <span x-text="open ? 'Cerrar' : 'Abrir'"></span>
    </button>
    
    <div x-show="open" x-transition x-cloak>
        {{-- contenido --}}
    </div>

</div>
```

## Tailwind CSS

### Clases preferidas por elemento

| Elemento | Clases base |
|----------|-------------|
| Botón primario | `px-6 py-2.5 bg-indigo-600 text-white font-semibold rounded-full hover:bg-indigo-700 transition` |
| Botón secundario | `px-6 py-2.5 border border-gray-200 text-gray-700 font-semibold rounded-full hover:bg-gray-50 transition` |
| Card | `bg-white rounded-2xl border border-gray-100 shadow-sm p-6` |
| Input | `w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none text-sm` |
| Label | `block text-sm font-medium text-gray-700 mb-1` |
| Sección | `py-16 px-4` con contenedor `max-w-5xl mx-auto` |
| Heading H1 | `text-4xl sm:text-5xl font-extrabold text-gray-900` |
| Heading H2 | `text-3xl font-bold text-gray-900` |
| Lead text | `text-xl text-gray-500 max-w-2xl` |

### Breakpoints usados
- `sm:` — 640px (mobile landscape)
- `md:` — 768px (tablet)
- `lg:` — 1024px (desktop)
- Contenedor máximo: `max-w-5xl` (960px) o `max-w-6xl` (1152px)

## API REST

### Estructura de respuesta estándar

```json
// Listado paginado
{
    "data": [...],
    "meta": {
        "current_page": 1,
        "last_page": 5,
        "per_page": 15,
        "total": 73
    }
}

// Registro individual
{
    "data": { "id": 1, "title": "...", "created_at": "2026-01-15T08:00:00Z" }
}

// Error de validación (422)
{
    "message": "The given data was invalid.",
    "errors": { "title": ["The title field is required."] }
}

// Error genérico
{
    "message": "Resource not found."
}
```

### Prefijo de rutas
- Todas las rutas API bajo `api/v1/`
- Recursos en plural kebab-case: `/api/v1/blog-posts`, `/api/v1/product-categories`

## Git (cuando se configure)

### Ramas
- `main` — producción estable
- `develop` — integración
- `feature/nombre-descriptivo` — nuevas funcionalidades
- `fix/nombre-bug` — correcciones

### Commits
```
feat: agregar CRUD de productos
fix: corregir paginación en listado de posts  
docs: actualizar documentación de API
refactor: extraer lógica de precios a servicio
chore: actualizar dependencias composer
```

### No commitear
- `.env`
- `vendor/`
- `node_modules/`
- `themes/*/assets/css/compiled.css` (build artifact)
- `storage/` (excepto la estructura de directorios)
