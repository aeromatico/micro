Add a Pines UI component (Tailwind CSS + Alpine.js) to the current OctoberCMS theme.

All Pines elements are stored locally in `themes/demo/assets/pines/elements/` — **read from there first**.
If the directory is empty, run `bash bin/pines-sync.sh` to download from GitHub.

## Usage
`/pines <element-slug-or-type> [target-file] [options]`

**Examples:**
- `/pines modal themes/microsites/partials/contact-modal.htm "trigger button, formulario contacto"`
- `/pines accordion themes/demo/partials/faq.htm "5 preguntas, borde suave"`
- `/pines toast themes/demo/partials/notifications.htm "éxito/error/warning, posición top-right"`
- `/pines date-picker themes/demo/pages/reservas.htm "rango de fechas"`

## How to use this skill

1. **Match the request to a slug** using the catalog below.
2. **Read the element file**: `themes/demo/assets/pines/elements/{slug}.html`
3. **Read variants if needed**: `themes/demo/assets/pines/elements/{slug}-examples/example-01.html` (etc.)
4. **Choose the best variant** based on the request description.
5. **Read the target file** if it already exists.
6. **Adapt the element**: adjust text, colors, OctoberCMS Twig syntax, dark/light mode.
7. **Write to the target file**.

> If no target file is given, output the adapted code as a code block.

---

## Element catalog

All 47 elements in `themes/demo/assets/pines/elements/`:

### Animation
| Slug | File | Variants |
|------|------|----------|
| marquee | marquee.html | marquee-examples/ |
| marquee-additional | marquee-additional.html | — |
| retro-grid | retro-grid.html | retro-grid-examples/ |
| text-animation | text-animation.html | text-animation-examples/ |
| typing-effect | typing-effect.html | — |

### Display
| Slug | File | Variants |
|------|------|----------|
| accordion | accordion.html | accordion-examples/ |
| badge | badge.html | badge-examples/ |
| banner | banner.html | banner-examples/ |
| button | button.html | button-examples/ |
| card | card.html | card-examples/ |
| image-gallery | image-gallery.html | — |
| quotes | quotes.html | — |
| table | table.html | table-examples/ |

### Feedback
| Slug | File | Variants |
|------|------|----------|
| alert | alert.html | alert-examples/ |
| progress | progress.html | progress-examples/ |
| toast | toast.html | — |

### Forms
| Slug | File | Variants |
|------|------|----------|
| checkbox | checkbox.html | checkbox-examples/ |
| combobox | combobox.html | — |
| date-picker | date-picker.html | date-picker-examples/ |
| radio-group | radio-group.html | — |
| range-slider | range-slider.html | — |
| rating | rating.html | rating-examples/ |
| select | select.html | — |
| switch | switch.html | switch-examples/ |
| text-input | text-input.html | — |
| textarea | textarea.html | textarea-examples/ |
| textarea-auto-resize | textarea-auto-resize.html | textarea-auto-resize-examples/ |

### Navigation
| Slug | File | Variants |
|------|------|----------|
| breadcrumbs | breadcrumbs.html | breadcrumbs-examples/ |
| full-screen-menu | full-screen-menu.html | full-screen-menu-examples/ |
| menubar | menubar.html | — |
| navigation-menu | navigation-menu.html | — |
| pagination | pagination.html | pagination-examples/ |
| sticky-header | sticky-header.html | — |
| table-of-contents | table-of-contents.html | — |
| tabs | tabs.html | tabs-examples/ |

### Overlay
| Slug | File | Variants |
|------|------|----------|
| context-menu | context-menu.html | context-menu-examples/ |
| dropdown-menu | dropdown-menu.html | dropdown-menu-examples/ |
| full-screen-modal | full-screen-modal.html | full-screen-modal-examples/ |
| hover-card | hover-card.html | — |
| modal | modal.html | modal-examples/ |
| popover | popover.html | — |
| slide-over | slide-over.html | slide-over-examples/ |
| tooltip | tooltip.html | tooltip-examples/ |

### Special
| Slug | File | Variants |
|------|------|----------|
| monaco-editor | monaco-editor.html | — |
| video | video.html | video-examples/ |

### Utilities
| Slug | File | Variants |
|------|------|----------|
| command | command.html | command-examples/ |
| copy-to-clipboard | copy-to-clipboard.html | copy-to-clipboard-examples/ |

---

## Alpine.js notes

- All elements use `x-cloak` → the layout/page must include `[x-cloak]{display:none!important}` in CSS.
- Some elements use `x-collapse` → include `@alpinejs/collapse` before Alpine.js:
  ```html
  <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
  ```
- Elements are **pure snippets** — no `<html>`, `<head>`, `<body>` wrappers.

## OctoberCMS / Twig integration

- Use `{{ variable }}` for dynamic content, `{% for item in items %}` for loops.
- For `themes/microsites/`: dark-first, slate-950 background, use `dark:` variants.
- For `themes/demo/`: light mode default, use standard Tailwind neutral/slate palette.
- The `themes/demo/layouts/elements.htm` layout already includes Alpine.js + collapse plugin.

## Updating elements from GitHub

```bash
bash bin/pines-sync.sh           # download / update all elements
bash bin/pines-sync.sh --check   # show version and last sync date
```

Preview all elements at: `/elements`
