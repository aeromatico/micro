Create a new reusable OctoberCMS partial with Pines (Tailwind + Alpine.js).

## Usage
`/october-partial <path/name> [description]`

**Examples:**
- `/october-partial site/hero "Hero section con imagen de fondo y CTA"`
- `/october-partial ui/pricing-card "Tarjeta de precio con toggle mensual/anual"`
- `/october-partial forms/contact "Formulario AJAX de contacto con validación"`

## What to create

Given `$ARGUMENTS`:
- **path/name** = relative path inside `themes/demo/partials/` (e.g. `site/hero` → `partials/site/hero.htm`)
- **description** = what the partial should do/look like

Create: `/www/wwwroot/micro.clouds.com.bo/themes/demo/partials/{path/name}.htm`

Create intermediate directories as needed.

## Template structure

```twig
{{--
    Partial: {name}
    {description}
    Usage: {% partial '{path/name}' %}
    Params: {% partial '{path/name}' param1='value' %}
--}}

<div x-data="{
    {# Alpine.js state #}
}" class="...">

    {# Tailwind + Pines markup here #}

</div>
```

## Pines UI patterns by category

### Navigation / Headers
```html
<nav x-data="{ mobileOpen: false }" class="bg-white border-b border-gray-100 sticky top-0 z-40">
    <div class="max-w-6xl mx-auto px-4 flex items-center justify-between h-16">
        <a href="/" class="text-xl font-bold text-indigo-600">Logo</a>
        <div class="hidden md:flex items-center gap-6 text-sm font-medium text-gray-600">
            <a href="/" class="hover:text-indigo-600 transition">Inicio</a>
        </div>
        <button @click="mobileOpen = !mobileOpen" class="md:hidden p-2 rounded-lg hover:bg-gray-100">☰</button>
    </div>
    <div x-show="mobileOpen" x-cloak class="md:hidden px-4 pb-4 space-y-2 text-sm">
        <a href="/" class="block py-2 text-gray-700 hover:text-indigo-600">Inicio</a>
    </div>
</nav>
```

### Cards / Content
```html
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition">
        <div class="w-12 h-12 rounded-xl bg-indigo-50 flex items-center justify-center mb-4">
            <svg class="w-6 h-6 text-indigo-600">...</svg>
        </div>
        <h3 class="text-lg font-semibold text-gray-900 mb-2">Título</h3>
        <p class="text-gray-500 text-sm leading-relaxed">Descripción breve.</p>
    </div>
</div>
```

### Forms (with OctoberCMS AJAX)
```html
<form data-request="onSendContact" data-request-success="success = true" x-data="{ success: false }">
    {{ form_token() }}
    
    <div x-show="!success" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
            <input type="text" name="name" required
                class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input type="email" name="email" required
                class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none text-sm">
        </div>
        <button type="submit" data-attach-loading
            class="w-full py-2.5 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition">
            Enviar
        </button>
    </div>
    
    <div x-show="success" x-cloak class="text-center py-8">
        <div class="text-4xl mb-3">✅</div>
        <p class="text-gray-700 font-medium">¡Mensaje enviado con éxito!</p>
    </div>
</form>
```

### Hero Sections
```html
<section class="relative min-h-[70vh] flex items-center bg-gradient-to-br from-indigo-900 via-indigo-800 to-purple-900">
    <div class="absolute inset-0 opacity-10 bg-[radial-gradient(circle_at_1px_1px,white_1px,transparent_0)] bg-[size:40px_40px]"></div>
    <div class="relative max-w-5xl mx-auto px-6 py-20 text-center">
        <h1 class="text-5xl sm:text-6xl font-extrabold text-white mb-6 leading-tight">
            Título Principal
        </h1>
        <p class="text-xl text-indigo-200 max-w-2xl mx-auto mb-8">Subtítulo descriptivo aquí.</p>
        <div class="flex flex-wrap gap-4 justify-center">
            <a href="#" class="px-8 py-3 bg-white text-indigo-700 font-semibold rounded-full hover:bg-indigo-50 transition shadow-lg">CTA Principal</a>
            <a href="#" class="px-8 py-3 border border-white/30 text-white font-semibold rounded-full hover:bg-white/10 transition">Secundario</a>
        </div>
    </div>
</section>
```

Build the partial based on the description, combining Twig templating (for dynamic data from OctoberCMS) with Pines/Alpine.js interactivity. Report the file path and usage snippet.
