Create a new OctoberCMS CMS page with Pines (Tailwind + Alpine.js) styling.

## Usage
`/october-page <title> <url> [layout] [description]`

**Example:** `/october-page "Contacto" /contacto default "Formulario de contacto con mapa"`

## What to create

Given `$ARGUMENTS`, extract:
- **title** = page title (quoted or first words)
- **url** = URL slug starting with /
- **layout** = layout name (default: `default`)
- **description** = rest (optional, describes what the page should contain)

Theme path: `/www/wwwroot/micro.clouds.com.bo/themes/demo/`

### Page file — `pages/{slug}.htm`

OctoberCMS page files have three sections separated by `==`:
1. **INI config** — metadata
2. **PHP code** — optional backend logic (can be empty)
3. **Twig/HTML** — template

```
title = "{title}"
url = "{url}"
layout = "{layout}"
meta_title = "{title}"
meta_description = "{description}"
==
<?php
// PHP section: component queries, AJAX handlers, etc.
// Access URL params: $this->param('slug')
// Pass to template: $this->page->data = ...
?>
==
{{-- {title} page - uses Pines + Tailwind + Alpine.js --}}

<section class="py-12 px-4">
    <div class="max-w-5xl mx-auto">
        <h1 class="text-4xl font-bold text-gray-900 mb-4">{title}</h1>
        
        {{-- Page content here --}}
        
    </div>
</section>
```

## Pines component patterns to use

When the description mentions UI elements, include relevant Pines patterns:

**Alert/Notification:**
```html
<div x-data="{ show: true }" x-show="show" class="flex items-center p-4 rounded-lg bg-blue-50 border border-blue-200">
    <span class="text-blue-700 text-sm">Mensaje aquí</span>
    <button @click="show = false" class="ml-auto text-blue-400 hover:text-blue-600">✕</button>
</div>
```

**Modal:**
```html
<div x-data="{ open: false }">
    <button @click="open = true" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Abrir</button>
    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div @click.outside="open = false" class="bg-white rounded-xl shadow-xl p-6 w-full max-w-md">
            <h3 class="text-lg font-semibold mb-4">Título</h3>
            <p class="text-gray-600 mb-6">Contenido del modal.</p>
            <button @click="open = false" class="px-4 py-2 bg-gray-100 rounded-lg">Cerrar</button>
        </div>
    </div>
</div>
```

**Accordion/FAQ:**
```html
<div x-data="{ active: null }" class="space-y-2">
    <template x-for="(item, i) in items" :key="i">
        <div class="border border-gray-200 rounded-lg overflow-hidden">
            <button @click="active === i ? active = null : active = i"
                class="flex justify-between w-full px-5 py-4 text-left font-medium text-gray-800">
                <span x-text="item.q"></span>
                <span x-text="active === i ? '−' : '+'"></span>
            </button>
            <div x-show="active === i" x-collapse class="px-5 pb-4 text-gray-600" x-text="item.a"></div>
        </div>
    </template>
</div>
```

**Tabs:**
```html
<div x-data="{ tab: 'uno' }" class="w-full">
    <div class="flex border-b border-gray-200 mb-6">
        <button @click="tab = 'uno'" :class="tab === 'uno' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-gray-500'" class="px-6 py-3 font-medium">Tab 1</button>
        <button @click="tab = 'dos'" :class="tab === 'dos' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-gray-500'" class="px-6 py-3 font-medium">Tab 2</button>
    </div>
    <div x-show="tab === 'uno'">Contenido tab 1</div>
    <div x-show="tab === 'dos'">Contenido tab 2</div>
</div>
```

Build the page content based on the description, use appropriate Pines patterns. Keep Twig clean with Alpine.js for interactivity. Report the file created and its URL.
