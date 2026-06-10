# Skills de Frontend

Stack: **OctoberCMS 4 + Tailwind CSS 3 + Alpine.js 3 + Pines UI**

---

## /october-theme

Configura Tailwind CSS + Alpine.js + Pines en el tema activo.

**Archivo:** `.claude/commands/october-theme.md`

### Modos

#### `setup` — Configurar Tailwind en tema existente
```
/october-theme setup
```

Crea/actualiza en `themes/demo/`:
- `package.json` con devDependencies de Tailwind
- `tailwind.config.js` con `content` apuntando a todos los `.htm`
- `assets/css/app.css` con directivas `@tailwind` + capa `[x-cloak]`
- Agrega Alpine.js CDN y el CSS compilado al layout `default.htm`
- Instala dependencias y hace el primer build

#### `build` — Compilar CSS para producción
```
/october-theme build
```

Ejecuta `npm run build` → genera `assets/css/compiled.css` minificado.

#### `new <nombre>` — Crear tema desde cero
```
/october-theme new mi-tema
```

Crea un tema completo con Pines preconfigurado: layout, páginas base, partials de navbar/footer, Tailwind + Alpine.js listos.

### Build manual
```bash
cd /www/wwwroot/micro.clouds.com.bo/themes/demo
npm run dev    # watch mode (desarrollo)
npm run build  # producción (minificado)
```

---

## /october-page

Crea una página CMS nueva con Twig + Pines/Tailwind/Alpine.js.

**Archivo:** `.claude/commands/october-page.md`

### Sintaxis
```
/october-page "<título>" <url> [layout] [descripción]
```

### Ejemplos
```
/october-page "Inicio" / default "Hero con gradiente, features y CTA"
/october-page "Contacto" /contacto default "Formulario AJAX con mapa lateral"
/october-page "Precios" /precios default "3 planes con toggle mensual/anual"
/october-page "Nosotros" /nosotros blank "Timeline del equipo con fotos"
```

### Qué crea
- Archivo `themes/demo/pages/{slug}.htm`
- Sección INI con metadatos
- Sección PHP opcional vacía
- Sección Twig con Pines components según la descripción

### Estructura generada
```
title = "Contacto"
url = "/contacto"
layout = "default"
meta_title = "Contacto"
meta_description = "..."
==
<?php ?>
==
<section class="py-16 px-4">
    <div class="max-w-5xl mx-auto">
        {{-- Pines components según descripción --}}
    </div>
</section>
```

### Patrones Pines disponibles
- Alertas, notificaciones toast
- Modales con `x-dialog`
- Accordions / FAQ
- Tabs
- Formularios con AJAX de OctoberCMS (`data-request`)
- Hero sections con gradientes
- Grids de cards

---

## /october-partial

Crea un partial reutilizable con Pines/Tailwind/Alpine.js.

**Archivo:** `.claude/commands/october-partial.md`

### Sintaxis
```
/october-partial <path/nombre> [descripción]
```

### Ejemplos
```
/october-partial site/hero "Hero oscuro con gradiente y dos CTAs"
/october-partial site/header "Navbar sticky con mobile menu"
/october-partial ui/pricing-card "Tarjeta de precio con badge popular"
/october-partial forms/contact "Formulario de contacto con validación AJAX"
/october-partial blog/post-card "Card de post con imagen, categoría, título y fecha"
/october-partial ui/stats "4 métricas con iconos y animación contador"
```

### Qué crea
- Archivo `themes/demo/partials/{path/nombre}.htm`
- Crea directorios intermedios si no existen
- Comentario de uso en cabecera
- HTML completo con Alpine.js state + Tailwind classes

### Uso en página
```twig
{% partial 'site/hero' %}
{% partial 'blog/post-card' post=post %}
{% partial 'ui/pricing-card' plan=plan featured=true %}
```

### Patrones de partials disponibles

| Categoría | Patrones |
|-----------|---------|
| **Navegación** | navbar sticky, mega-menu, breadcrumbs, pagination |
| **Hero** | hero con imagen, hero con gradiente, hero split |
| **Cards** | post card, product card, team member, testimonial |
| **Formularios** | contacto AJAX, suscripción, login, búsqueda |
| **Layouts** | sidebar layout, feature sections, CTA sections |
| **Feedback** | alert, toast, empty state, loading skeleton |

---

## /pines

Agrega un componente específico de Pines UI a un archivo existente.

**Archivo:** `.claude/commands/pines.md`

### Sintaxis
```
/pines <tipo> [archivo-destino] [opciones]
```

### Tipos disponibles

| Tipo | Descripción |
|------|-------------|
| `hero` | Banner/hero section |
| `navbar` | Barra de navegación |
| `pricing` | Planes de precios con toggle |
| `table` | Tabla con búsqueda y ordenación |
| `modal` | Diálogo/modal |
| `toast` | Notificaciones temporales |
| `dropdown` | Menú desplegable |
| `form` | Formulario con validación |
| `gallery` | Galería con lightbox |
| `stats` | Tarjetas de métricas/KPIs |
| `timeline` | Timeline vertical |
| `stepper` | Wizard multi-paso |
| `carousel` | Carrusel de imágenes/contenido |
| `sidebar` | Layout con sidebar |
| `accordion` | Preguntas/respuestas colapsables |

### Ejemplos
```
/pines navbar themes/demo/partials/site/header.htm "logo, 4 links, botón CTA derecha"
/pines pricing themes/demo/pages/precios.htm "3 planes, toggle mensual/anual, plan Pro destacado"
/pines table themes/demo/partials/admin/tabla.htm "sortable por columna, búsqueda en tiempo real"
/pines modal "sin archivo" "confirmación de borrado con input de verificación"
/pines timeline themes/demo/pages/nosotros.htm "historia de la empresa desde 2020"
```

### Si no se especifica archivo
El componente se genera en la conversación para copiar/pegar.

### Integración con Twig
Los componentes Pines pueden combinarse con datos de OctoberCMS:
```twig
{# Variable Twig → Alpine.js #}
<div x-data="{ items: {{ posts|json_encode|raw }} }">
    <template x-for="item in items">...</template>
</div>
```

---

## Notas importantes de Frontend

### Alpine.js
- Versión: `^3.x` via CDN en el layout
- `x-cloak` requiere `[x-cloak] { display: none !important; }` en el CSS
- Para estado global usar `Alpine.store('nombre', {...})` en un archivo JS

### Tailwind
- **No usar CDN de Tailwind en producción** — siempre compilar con CLI
- El archivo fuente es `themes/demo/assets/css/app.css`
- El compilado es `themes/demo/assets/css/compiled.css`
- Recompilar tras cada cambio de clases en archivos `.htm`

### AJAX de OctoberCMS
- Usar `data-request="onNombreHandler"` en forms
- El handler PHP va en la sección `<?php ?>` de la página/partial o en el componente
- Respuesta parcial: `data-request-update="'partial-name': '#selector'"`
- Sin recarga: `data-request-success="callback()"`
