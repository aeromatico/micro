---
name: docs-plugin
description: Documenta las funciones de un plugin de Aero en el plugin Docs (solo panel del tenant) como borrador para revisar. Úsala cuando pidan "documentar el plugin X", "documentación de aero/pay", actualizar la doc tras un cambio de versión, o cuando el brief de docs-sync liste plugins desactualizados.
---

# Workflow: documentar un plugin en Aero.Docs

Documenta **solo las funciones que ve el rol tenant** (paneles tipo *Sitio Web*, *Bolivia Pay*, *CRM*).
Nunca el panel de superadmin/plataforma salvo que se pida explícitamente. Todo entra como **borrador**.

## Convenciones

- Idioma: **español**. Sin emojis salvo que el original ya los use.
- Un artículo por formulario/función: `plugins/aero/docs/content/<plugin>/<plugin>-<tema>.md`.
  **El nombre del archivo es el slug** y debe ser único (no colisionar con los existentes).
- Portada del plugin: `<plugin>-vista-general.md`.
- Enlazar entre artículos con el slug relativo (ej. `[Páginas](sites-formulario-pagina)`).
- Callouts: `> [!NOTE]`, `> [!TIP]`, `> [!IMPORTANT]`, `> [!WARNING]`, `> [!CAUTION]`.
- Cada artículo documenta un formulario/función con: Ruta, Controlador, Modelo, Permiso; para qué sirve;
  tabla de campos; reglas y comportamiento reales; callouts; enlaces relacionados.
- Termina con `**Versión documentada:** x.y.z`.
- Front matter opcional al inicio del `.md` (lo lee `docs:import`):

```markdown
---
title: Cuentas bancarias
sort: 10
featured: false
---
# Cuentas bancarias
```

Sin front matter, el título sale del primer `# Título` y el orden se asigna al final de la categoría.

## Dos versiones distintas

- `aero_docs_articles.version` es la **revisión del artículo**. Es automática; no la toques.
- `plugin_version` es la **versión del plugin documentada**. Sale sola de la última versión de
  `plugins/aero/<plugin>/updates/version.yaml` al importar.

## Pasos

### 1. Analizar (solo lo que indica el brief)

Lee `plugins/aero/<plugin>/Plugin.php` (menú, permisos), los `fields.yaml`, `config_form.yaml`,
`config_relation.yaml`, el modelo y el controlador de cada formulario afectado. Lee también las notas
de versión del brief. **No inventes**: documenta solo lo que existe en el código.

### 2. Escribir el contenido

- Plugin nuevo (`bootstrap`): un `.md` por función más la vista general.
- Plugin desactualizado: reescribe **solo** los artículos afectados por las versiones nuevas y añade los de
  funciones nuevas. Parte del contenido existente en `content/<plugin>/` para no perder redacción buena.
- Si algo no cambia para el tenant (refactor, superadmin, infraestructura), no toques nada.

### 3. Documento general de la oferta (solo si cambió la oferta)

`plugins/aero/docs/content/plugins/funcionalidades.md`: sección del plugin con Funcionalidad, Descripción
breve, Cualidades de impacto y Casos de uso, más su línea de versión documentada.

### 4. Importar (es tu verificación)

```bash
sudo -u www /www/server/php/84/bin/php artisan docs:import <plugin>
```

`docs:import` **nunca pisa lo publicado**:
- artículo nuevo → borrador (`is_published = false`);
- artículo publicado con contenido distinto → "cambios pendientes de aprobar";
- contenido igual → solo actualiza la versión documentada.

Si imprime un error, corrígelo y vuelve a ejecutar. No hay seeders PHP ni cambios en `version.yaml`.

## Checklist

- [ ] Solo funciones de tenant, y solo lo que existe en el código.
- [ ] Un artículo por formulario, archivo = slug único.
- [ ] Ruta / Controlador / Modelo / Permiso y tabla de campos en cada uno.
- [ ] `docs:import` sin errores.
- [ ] Reporte corto con lo escrito y las dudas.

## Automatización

Vigilante determinista: `.claude/agents/docs-sync/docs-sync-watch.py` (cron cada 30 min). Compara la
versión documentada con la de `version.yaml` y el `source_hash` de cada formulario; solo llama a Claude
(`claude -p`) si hay algo desactualizado. Comandos: `status | run | bootstrap <plugins> | pause | resume | reset <plugin>`.
Los commits los hace `git-agent`; la aprobación la hace una persona en Docs → Guías interactivas / Artículos.
