---
name: service-offer
description: Redacta la página comercial (oferta) de un servicio de Aero.Services a partir de la información base que dio una persona y de la evidencia real de los plugins ligados, y la deja como PROPUESTA para aprobar. Úsala cuando el brief de docs-sync liste una PÁGINA DEL SERVICIO, o cuando pidan completar /plugin/{slug}.
---

# Completar la página de un servicio

Un servicio (Backend → Servicios) trae **información base** que puso una persona (nombre, planes, categorías,
notas de los plugins ligados). Tu trabajo es completar lo que falta: **resumen, descripción, características,
requisitos y la página HTML**, como una **propuesta**. Nunca se aplica sola: una persona la aprueba después.

## La clave: Plataforma → Plugins ligados

Cada plugin ligado tiene una **relación**, y de ella depende cómo hablas de él:
- `built_with` («Construido con el plugin»): es la **base** del servicio. Su documentación y sus guías son las
  del servicio. Aquí sale casi toda la oferta.
- `integrates` («Se integra con»): capacidad de conexión; el servicio funciona sin él.
- `recommended` («Recomendado para»): a quién más le conviene. **No** lo presentes como función del servicio.

## Pasos

1. Obtén la base y la evidencia (solo lectura):
   `sudo -u www /www/server/php/84/bin/php artisan services:offer-proposal <id> --evidence`
   Trae `service` (lo que dio la persona: respétalo, no lo contradigas) y `plugins` (README, menú, permisos,
   componentes, modelos, rutas, changelog y documentos publicados con su `id`).
2. Redacta el JSON y escríbelo en `plugins/aero/docs/content/offers/<slug>.json`.
3. Valídalo (y déjalo como propuesta):
   `sudo -u www /www/server/php/84/bin/php artisan services:offer-proposal <id> --from-json=plugins/aero/docs/content/offers/<slug>.json`
   Si da error (p. ej. no hay exactamente 10 casos de uso), corrige el JSON y repite. Es tu única verificación.
4. Reporta: qué usaste de la información base, qué omitiste por falta de evidencia y las dudas.

No ejecutes `services:build-offer` ni `services:offer-apply`: aplicar no es tu decisión.

## Reglas de redacción

- Español neutro, claro, sin exageraciones; beneficios concretos para un negocio boliviano.
- Usa **solo** capacidades que aparezcan en la evidencia. No inventes funciones, integraciones, precios ni cifras.
  Si algo no está claro, omítelo.
- Si el servicio ya tiene `summary`/`description`/`features` escritos por una persona, **conserva su intención y
  tono** y complétalos; no los reemplaces por algo distinto.

## Contrato del JSON

Devuelve un objeto con estas claves:

```json
{
  "summary": "una línea, máx. 160 caracteres",
  "description": "Markdown, 2-4 párrafos: qué es, para quién, qué resuelve",
  "features": ["ítem corto"],
  "requirements": ["qué se necesita del cliente (puede ser [])"],
  "code": "HTML",
  "docs_article_ids": [ids de la lista de documentos de la evidencia, los más útiles]
}
```

- `features`: 6-10 ítems de «Qué incluye».
- `code` es un fragmento HTML **sin** `<html>/<head>/<body>`, sin `<script>`, sin `<style>`, sin estilos en línea,
  con clases **Tailwind CSS 3** y **solo los colores del tema** (modo oscuro por defecto): texto `text-ink` y
  `text-ink-dim`, tarjetas `bg-canvas-elev` con `border border-edge`, acento `text-accent` / `bg-accent text-accent-fg`.
  **Nunca** `gray-*`, `white`, `black` ni colores fijos. Secciones, en este orden, cada una en `<section>` con `<h2>`:
  1. **Hero:** `<h1>` con el nombre del servicio + promesa en un párrafo.
  2. **Características:** rejilla de tarjetas (título + 1-2 líneas) basadas en capacidades reales.
  3. **Casos de uso:** SIEMPRE **exactamente 10** escenarios concretos y distintos (quién, qué problema, cómo lo
     resuelve), cada uno un `<li data-usecase>` dentro de un `<ul>`. Si la evidencia da menos de 10 situaciones,
     completa con variantes reales por tipo de negocio o de equipo, sin inventar funciones.
  4. **Cómo se conecta con tu plataforma:** solo si hay plugins `integrates`/`recommended`.
  5. **Preguntas frecuentes:** 5-7 con `<details><summary>…</summary><p>…</p></details>`, respaldadas por la evidencia.
  No incluyas sección de documentación: la agrega el sistema con `docs_article_ids`.
- El JSON debe ser válido: comillas dobles escapadas dentro de `code`, sin comas finales.

## Referencia

Las ofertas ya aplicadas son el modelo de calidad y estructura: `services:offer-proposal <id> --evidence` de un
servicio con `has_code: true` muestra su `plugin_links`, y su HTML está en `storage/app/service-offers/<slug>.html`.

## Servicios de la categoría «Rubros»

Usan `themes/master/partials/site/rubro.htm`: la plantilla pinta el hero (nombre, resumen, plugins ligados con su nota y
planes), «Qué incluye» (de `features`), «Para quién es» (de `description`) y «Qué necesitas» (de `requirements`).
Por eso el `code` de un rubro **no lleva hero**: empieza en «La ventaja de tenerlo todo conectado» (rejilla de
tarjetas: qué gana el cliente por cada plugin integrado), luego «Casos de uso» (10) y «Preguntas frecuentes».
Pon una `note` útil en cada plugin ligado: se muestra tal cual en la tarjeta de integraciones. Modelo: `offers/gimnasios.json`.
