---
name: docs-plugin
description: Documenta las funciones de un plugin de Aero en el plugin Docs (solo panel del tenant), genera su seeder, actualiza la versión del plugin, y publica en local. Úsala cuando el usuario pida "documentar el plugin X", "documentación de aero/pay", "agregar funcionalidades de notify a la documentación", o continuar el flujo de documentación plugin por plugin.
---

# Workflow: documentar un plugin en Aero.Docs

Documenta, plugin por plugin, **solo las funciones que ve el rol tenant**
(paneles tipo *Sitio Web*, *Bolivia Pay*, *CRM*). **Nunca** documentar el panel
de superadmin/plataforma salvo que el usuario lo pida explícitamente.

## Convenciones del proyecto

- Idioma: **español**.
- Slug de categoría del plugin: `<plugin>-funciones` (hijo "Funciones del panel"), raíz `<plugin>`.
- Slug de cada artículo: `<plugin>-<tema>` y **el nombre del archivo es el slug**: `content/<plugin>/<plugin>-<tema>.md`.
- Enlazar entre artículos con el **slug relativo** (ej. `[Páginas](sites-formulario-pagina)`).
- Callouts soportados: `> [!NOTE]`, `> [!TIP]`, `> [!IMPORTANT]`, `> [!WARNING]`, `> [!CAUTION]`.
- Cada artículo documenta **un formulario/función** con: Ruta, Controlador, Modelo, Permiso; para qué sirve; tabla de campos; reglas/comportamiento; callouts; enlaces relacionados.
- No usar emojis en el contenido salvo que el original ya los use.

## Distinción clave: dos versiones

- `aero_docs_articles.version` — **número de revisión del artículo**. Se incrementa
  **solo** automáticamente en `Article::beforeSave()` (y `afterSave()` crea la
  instantánea en `aero_docs_article_versions`). **No lo asignes nunca** en seeders.
- `aero_docs_articles.plugin_version` — **versión del plugin documentada**. La
  fija el seeder al correr, leyendo la última versión de
  `<plugin>/updates/version.yaml`. Es la que permite detectar docs viejas.

## Entorno (rutas y comandos)

- **Proyecto (único):** `/www/wwwroot/micro.clouds.com.bo`. Este servidor es local y fuente de verdad.
  - `artisan` SIEMPRE como el usuario web y con el entorno intacto:
    `sudo -u www /www/server/php/84/bin/php artisan tinker --execute="..."`.
    **Nunca** `REDIS_PASSWORD=` vacío (rompe con `NOAUTH`) ni artisan como root
    (crea archivos en `storage/` que rompen el sitio).
- **Producción:** no se toca desde aquí. El código y los seeders viajan con el flujo normal
  (`git push` → allá `git pull` + `git submodule update` + `october:migrate`, con respaldo de BD).
- **Sitio público:** `https://market.com.bo/documentacion`.
  - Categoría: `/documentacion/categoria/<slug>`
  - Artículo: `/documentacion/<slug>`
- Plugin Docs: `Aero.Docs`; la versión instalada se registra en
  `system_plugin_versions` (columna `version`).

## Pasos

### 1. Analizar el plugin (solo tenant)

- Lee `plugins/aero/<plugin>/Plugin.php` (`pluginDetails`, `registerNavigation`,
  `registerPermissions`). Identifica el menú y marca lo que es de **tenant**.
- Enumera formularios: `models/*/fields.yaml`, `create_fields.yaml`,
  `config_form.yaml`, y controladores que montan widgets propios (SettingsModel
  o `makeWidget(Form::class, ...)`), más relaciones (`config_relation.yaml`,
  `*_form.yaml`).
- Para cada formulario, lee modelo + controlador para entender intención,
  validaciones y reglas de negocio reales. **No inventar**: documentar solo lo
  que existe en el código.

### 2. Escribir el contenido

Crea `plugins/aero/docs/content/<plugin>/` con un `.md` por función y un
`<plugin>-vista-general.md` de portada. Revisa que los slugs no colisionen con
los existentes (la columna `aero_docs_articles.slug` es única global).

### 3. Crear el seeder

`plugins/aero/docs/updates/seed_<plugin>_docs.php` (plantilla):

```php
<?php

use Aero\Docs\Models\Article;
use Aero\Docs\Models\Category;
use October\Rain\Database\Updates\Seeder;

return new class extends Seeder
{
    public function run(): void
    {
        $root = Category::firstOrNew(['slug' => '<plugin>']);
        $root->fill(['name' => '<Nombre>', 'icon' => '📦', 'description' => '...', 'is_active' => true]);
        $root->save();

        $forms = Category::firstOrNew(['slug' => '<plugin>-funciones']);
        $forms->fill(['parent_id' => $root->id, 'name' => 'Funciones del panel', 'icon' => '📋', 'description' => '...', 'is_active' => true]);
        $forms->save();

        $dir = plugins_path('aero/docs/content/<plugin>');
        $articles = [
            ['file' => '<plugin>-vista-general', 'title' => '...', 'sort' => 0, 'featured' => true],
            // ...
        ];

        foreach ($articles as $item) {
            $path = $dir . '/' . $item['file'] . '.md';
            if (!is_file($path)) { continue; }

            $article = Article::firstOrNew(['slug' => $item['file']]);
            $article->fill([
                'category_id'    => $forms->id,
                'title'          => $item['title'],
                'content'        => file_get_contents($path),
                'plugin_version' => $this->pluginVersion('aero/<plugin>'),
                'sort_order'     => $item['sort'],
                'is_published'   => true,
                'is_featured'    => (bool) ($item['featured'] ?? false),
            ]);
            $article->save(); // NO asignar 'version': es la revisión, se autoincrementa
        }
    }

    /** Última versión declarada en updates/version.yaml del plugin. */
    protected function pluginVersion(string $handle): ?string
    {
        $path = plugins_path($handle . '/updates/version.yaml');
        if (!is_file($path)) { return null; }

        preg_match_all('/^\s*([0-9]+\.[0-9]+\.[0-9]+):/m', file_get_contents($path), $m);
        if (empty($m[1])) { return null; }

        usort($m[1], 'version_compare');

        return end($m[1]) ?: null;
    }
};
```

### 4. Registrar la actualización de Docs

En `plugins/aero/docs/updates/version.yaml` agrega el siguiente número de versión
de Docs (revisa que no exista ya; el sync agrega versiones solo) con el seeder:

```yaml
1.0.X:
    - "Documentación de las funciones del panel (tenant) de Aero.<Plugin>."
    - seed_<plugin>_docs.php
```

Si el sync del plugin Docs trajo migraciones nuevas, córrelas antes (ver más abajo).

### 5. Actualizar el documento general de la oferta

En `plugins/aero/docs/content/plugins/funcionalidades.md`, agrega/actualiza la
sección del plugin con **Funcionalidad · Descripción breve · Cualidades de
impacto · Casos de uso** y la línea **Versión documentada**. No hace falta otro
seeder: vuelve a correr `seed_oferta_docs.php`.

### 6. Publicar en local

```bash
# correr el seeder (repite por plugin y por seed_oferta_docs)
sudo -u www /www/server/php/84/bin/php artisan tinker --execute="\$s = require base_path('plugins/aero/docs/updates/seed_<plugin>_docs.php'); \$s->run();"
```

Verifica en BD: `plugin_version` seteado y `version` (revisión) incrementado. Si el `version.yaml`
de Docs tiene versiones pendientes que no son tuyas, no subas `system_plugin_versions`: repórtalo.

### 7. Producción

No se hace aquí. Los seeders están en `version.yaml`, así que `october:migrate` los siembra al
desplegar. Deja el paso anotado en el reporte para que lo haga una persona.

### 8. Verificar en vivo

Comprueba con `curl` que la categoría y **cada** artículo devuelven `200` en
`https://market.com.bo/documentacion/...`. Reportar los links.

## Checklist final

- [ ] Solo funciones de tenant.
- [ ] Un artículo por formulario con Ruta/Controlador/Modelo/Permiso y campos.
- [ ] Slug único por artículo y archivo = slug.
- [ ] Seeder fija `plugin_version` (nunca `version`), `tenant_id = null` e `is_global = true`.
- [ ] `version.yaml` de Docs con nueva versión y seeder.
- [ ] Documento general de la oferta actualizado.
- [ ] Sembrado en local (producción llega con el despliegue normal).
- [ ] Links verificados (200).

## Automatización (vigilante + agente `docs-sync`)

- **Vigilante determinista:** `.opencode/docs-sync-watch.py` (cron cada 30 min). Compara la
  `plugin_version` documentada con la última de `updates/version.yaml`; **solo llama al modelo si
  hay algo desactualizado**. Guardas: no corre si `plugins/aero/docs/` tiene cambios sin commitear
  o editados hace poco, si hay lock, tope diario, backoff por fallos; se pausa solo si algo se
  escribe fuera de docs. Config: `.opencode/docs-sync.json` (`dry_run` true = simulación).
- **Agente:** `.opencode/agent/docs-sync.md`, permisos mínimos (escribe solo en `plugins/aero/docs/`,
  sin git de escritura ni ssh). Lo invoca el vigilante con un brief ya calculado.
- **Comandos:** `docs-sync-watch.py status | run | bootstrap <plugins> | pause | resume | reset <plugin>`
  y `/docs-sync <plugins>` a mano. Plugins sin documentar: `bootstrap` manual.
- **Commits:** los hace `git-agent`; espera mientras el lock del vigilante esté vivo.
- **Hook post-commit:** opcional, solo avisa al vigilante (`bin/docs-sync-install.sh --with-hook`).
- **Log:** `.opencode/docs-sync.log`.

Reglas: idempotente (`firstOrNew`), no re-documentar si nada cambia para el tenant, un solo proceso a la vez.

> [!NOTE]
> **Docs internas (futuro):** la documentación interna/operativa se automatizará
> más adelante con un flujo similar, sobre este mismo plugin pero en otro ámbito
> (documentación de plataforma, no tenant). Cuando se defina, se agregará aquí.
