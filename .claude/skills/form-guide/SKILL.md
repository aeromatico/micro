---
name: form-guide
description: Genera una guía interactiva de capacitación (HTML autocontenido) que replica un formulario del backend de OctoberCMS a partir de su YAML, con guía paso a paso, validación y lógica de triggers. Úsala cuando pidan una guía/demo de un formulario (ej. /backend/aero/pay/bankaccounts/create) o cuando el brief de docs-sync liste formularios sin guía o desactualizados.
---

# Guía interactiva de un formulario

Produce **un archivo HTML autocontenido** que reconstruye un formulario del backend y lo acompaña de una
guía paso a paso. Es material de capacitación: el usuario practica sin tocar datos reales.

La referencia de calidad es `.claude/skills/form-guide/reference.html` (guía de *Nueva cuenta bancaria*).
**Léela entera antes de escribir** y replica su estructura y su nivel de detalle. No la copies con sus datos.

## Entradas (las fuentes reales, en este orden)

1. `plugins/aero/<plugin>/controllers/<controlador>/config_form.yaml`: `name`, `modelClass`, `form`, `create`/`update`.
2. El `fields.yaml` que apunta `form`: campos, `type`, `span`, `tab`, `required`, `default`, `comment`,
   `trigger`, `dependsOn`, `context`, `options`.
3. El modelo (`modelClass`): `$rules` (obligatorios, máximos) y los métodos `get<Campo>Options()` que dan las
   opciones de los dropdowns. **Usa las opciones reales**; si vienen de un registro dinámico (drivers,
   catálogos), léelo del código que lo registra.
4. El controlador: handlers `on*` (botones como "Probar conexión") y `formExtendModel`.
5. `config_relation.yaml` y los `columns_*.yaml`/`*_fields.yaml` que referencia (pestañas con tablas/listas).
6. Los partials `_*.htm` del controlador (paneles solo de edición, con su texto real).

**No inventes** campos, opciones ni reglas. Si algo no queda claro, omítelo o márcalo como ejemplo.

## Estructura del HTML

Documento completo (`<!doctype html>`), un solo archivo, sin recursos locales:

1. **Primera línea tras el doctype**, el metadato que lee `docs:import`:
   `<!--guide {"title":"Nueva cuenta bancaria","article":"<slug del artículo de docs>","sort":10} -->`
   (`article` es el slug del `.md` que documenta ese formulario, si existe.)
2. `<title>` corto y específico: `Guía: <nombre del formulario>`.
3. Tokens de color en `:root` con tema claro y oscuro (`prefers-color-scheme` + `[data-theme]`), `body` con fondo
   explícito. Tipografía: IBM Plex Sans + IBM Plex Mono desde Google Fonts con fallback.
4. Layout: guía a la izquierda (sticky en escritorio) y réplica del formulario a la derecha. En móvil, la guía
   arriba. Sin scroll horizontal; gutter mínimo de 16 px.
5. Formulario fiel al YAML: mismo orden, columnas (`span`), pestañas (`tab`), etiquetas, `comment`,
   asterisco en `required`, valores `default`, opciones reales de los dropdowns.
6. **Lógica**: `trigger` (mostrar/ocultar campos según otro campo) y validación (`required`, `max`, JSON
   válido, URL) con errores en línea y toast. Interruptor "Mostrar reglas técnicas" que muestra cada `trigger`.
7. **Guía**: pasos generados de los campos; cada paso con explicación, ejemplo, un aviso de error común
   ("Ojo") y botón "Rellenar ejemplo". Barra de progreso, lista de pasos clicable y sincronía con el foco.
   Los pasos cambian con los `trigger`.
8. **Modo create → update** al pulsar Crear: aparecen los campos `context: update` (botones como "Probar
   conexión" simulados, paneles de solo lectura, pestañas de relaciones con tabla de ejemplo y "Nuevo").
9. Todo dato es de ejemplo, marcado como tal. La demo **no guarda nada**.

## Reglas técnicas (el HTML se sirve en un iframe `sandbox="allow-scripts"` con CSP estricta)

- Scripts externos solo desde `https://cdnjs.cloudflare.com` (casi nunca hacen falta); estilos externos solo
  Google Fonts. Todo lo demás, en línea. Imágenes solo `data:`/`blob:`.
- Sin `fetch`, sin `localStorage` obligatorio, sin `alert/confirm/prompt`, sin `window.print`, sin descargas.
- Cada control con `id` estable; foco visible; respeta `prefers-reduced-motion`.
- Usa `textContent`/nodos DOM al insertar texto del usuario; `innerHTML` solo con cadenas propias.
- Español neutro, directo, voz activa. Botones que dicen exactamente lo que hacen.
- Comprueba la sintaxis del script antes de terminar (`docs:import` lo hace por ti con `node --check`).

## Salida

Escribe `plugins/aero/docs/content/guides/<plugin>/<slug>.html` con el **slug exacto** que te dieron
(`guia-<plugin>-<controlador>`), y ejecuta:

```bash
sudo -u www /www/server/php/84/bin/php artisan docs:import <plugin>
```

Queda como **borrador** (o como propuesta pendiente si ya había una publicada). Nunca publiques.

## Uso interactivo (pulir con Claude Design)

Con un formulario concreto: genera el HTML como arriba, ábrelo en el navegador o publícalo como artifact,
afínalo con el usuario y vuelve a guardarlo en `content/guides/`. El siguiente `docs:import` lo deja como
propuesta pendiente de aprobación.
