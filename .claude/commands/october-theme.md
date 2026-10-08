Set up Tailwind CSS + Alpine.js + Pines in the active OctoberCMS theme.

## Usage
`/october-theme setup` — Configure Tailwind + Alpine.js + Pines in the demo theme
`/october-theme build` — Build/compile Tailwind CSS
`/october-theme new <name>` — Create a new theme from scratch with Pines preconfigured

## Instructions

### For `setup`:

Check if the theme at `/www/wwwroot/micro.clouds.com.bo/themes/demo/` already has Tailwind configured.

If not, do the following:

**1. Check/create package.json in theme directory:**
```json
{
  "name": "micro-theme",
  "private": true,
  "scripts": {
    "dev": "npx tailwindcss -i ./assets/css/app.css -o ./assets/css/compiled.css --watch",
    "build": "npx tailwindcss -i ./assets/css/app.css -o ./assets/css/compiled.css --minify"
  },
  "devDependencies": {
    "tailwindcss": "^3.4.0",
    "@tailwindcss/typography": "^0.5.0",
    "@tailwindcss/forms": "^0.5.0",
    "@tailwindcss/aspect-ratio": "^0.4.0"
  }
}
```

**2. Create/update tailwind.config.js in theme directory:**
```js
/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './**/*.htm',
    './assets/js/**/*.js',
  ],
  theme: {
    extend: {
      colors: {
        primary: {
          50:  '#eef2ff',
          100: '#e0e7ff',
          500: '#6366f1',
          600: '#4f46e5',
          700: '#4338ca',
          900: '#312e81',
        },
      },
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui'],
      },
    },
  },
  plugins: [
    require('@tailwindcss/typography'),
    require('@tailwindcss/forms'),
  ],
}
```

**3. Create `assets/css/app.css`:**
```css
@tailwind base;
@tailwind components;
@tailwind utilities;

/* Pines requirement */
[x-cloak] { display: none !important; }

@layer base {
  html { @apply scroll-smooth; }
  body { @apply antialiased text-gray-700 bg-white; }
}

@layer components {
  .btn-primary {
    @apply inline-flex items-center px-6 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-full hover:bg-indigo-700 transition-colors shadow-sm;
  }
  .btn-secondary {
    @apply inline-flex items-center px-6 py-2.5 border border-gray-200 text-gray-700 text-sm font-semibold rounded-full hover:bg-gray-50 transition-colors;
  }
  .card {
    @apply bg-white rounded-2xl border border-gray-100 shadow-sm;
  }
}
```

**4. Update the default layout to include Alpine.js and compiled CSS:**

In the `<head>` section of `themes/demo/layouts/default.htm`, ensure these are included:
```html
<link href="{{ 'assets/css/compiled.css'|theme }}" rel="stylesheet">
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
```

**5. Install dependencies and build:**
```bash
cd /www/wwwroot/micro.clouds.com.bo/themes/demo && npm install && npm run build
```

### For `build`:
Run:
```bash
cd /www/wwwroot/micro.clouds.com.bo/themes/demo && npm run build
```

### For `new <name>`:
Create a new theme directory at `/www/wwwroot/micro.clouds.com.bo/themes/<name>/` with:
- `theme.yaml` — theme configuration
- `layouts/default.htm` — base layout with Alpine.js + Tailwind CDN
- `pages/index.htm` — homepage
- `partials/site/header.htm` — navbar partial
- `partials/site/footer.htm` — footer partial
- `assets/css/app.css` — Tailwind source
- `package.json` + `tailwind.config.js`

The layout should include Alpine.js from CDN and reference the compiled CSS. Use a clean, modern design with Pines patterns.

Report what was done and how to activate the new theme (via backend or .env `ACTIVE_THEME=<name>`).
