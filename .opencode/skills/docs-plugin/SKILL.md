---
name: docs-plugin
description: Documenta las funciones de un plugin de Aero en el plugin Docs (solo panel del tenant), genera su seeder, actualiza la versión del plugin, y publica en local y producción. Úsala cuando el usuario pida "documentar el plugin X", "documentación de aero/pay", "agregar funcionalidades de notify a la documentación", o continuar el flujo de documentación plugin por plugin.
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

- **Dev/repo:** `/root/projects/micro.clouds.com.bo`.
  - `artisan` necesita `REDIS_PASSWORD=` vacío para evitar
    `PhpRedisConnector AUTH ...`:
    `REDIS_PASSWORD= php artisan tinker --execute="..."`
- **Producción:** `root@89.117.150.163:/www/wwwroot/micro.clouds.com.bo`, PHP en
  `/www/server/php/84/bin/php`.
  - El código se **sincroniza solo** de dev → prod. Antes de sembrar, verifica
    con `ssh` que los archivos nuevos existan en prod.
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
REDIS_PASSWORD= php artisan tinker --execute="\$s = require base_path('plugins/aero/docs/updates/seed_<plugin>_docs.php'); \$s->run();"
```

Verifica en BD: `plugin_version` seteado y `version` (revisión) incrementado.

### 7. Publicar en producción

```bash
# 1) confirmar que el código nuevo esté sincronizado
ssh root@89.117.150.163 'ls /www/wwwroot/micro.clouds.com.bo/plugins/aero/docs/updates/seed_<plugin>_docs.php'

# 2) sembrar + registrar versión de Docs + limpiar caché
ssh root@89.117.150.163 'cd /www/wwwroot/micro.clouds.com.bo && /www/server/php/84/bin/php artisan tinker --execute="
\$s = require base_path(\"plugins/aero/docs/updates/seed_<plugin>_docs.php\");
\$s->run();
DB::table(\"system_plugin_versions\")->where(\"code\",\"Aero.Docs\")->update([\"version\"=>\"<docs_version>\"]);
" && /www/server/php/84/bin/php artisan cache:clear'
```

Si el plugin Docs tenía migraciones pendientes (columnas/tablas nuevas), córrelas
con `require base_path('...').up();` antes del seeder, y sube también el
`system_plugin_versions` de Docs.

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
- [ ] Local y producción sembrados; `system_plugin_versions` de Docs actualizado.
- [ ] Links verificados (200).

## Automatización (agente `docs-sync`)

La documentación tenant se mantiene sola ante commits nuevos:

- **Agente:** `.opencode/agent/docs-sync.md` (`opencode run --agent docs-sync`).
  Detecta plugins tocados por un commit, compara la `plugin_version` documentada
  con la actual y documenta (nuevo) o actualiza (existente) siguiendo esta skill.
- **Comando manual:** `/docs-sync [commit|rango|plugins]`.
- **Disparador:** hook `post-commit` (`.opencode/hooks/post-commit`) que corre el
  agente en segundo plano con lock cuando el commit toca `plugins/aero/*`
  (excluye `plugins/aero/docs/`, evita bucles). Se instala con
  `bin/docs-sync-install.sh` y se desinstala con `--remove`.
- **Log:** `.opencode/docs-sync.log`.

Reglas del modo automático:

- No re-documentar si el diff no cambia funciones visibles del panel.
- Idempotente: seeders con `firstOrNew` (actualizan, no duplican).
- No disparar si el commit solo toca docs.
- Respetar el lock para no solapar procesos.

> [!NOTE]
> **Docs internas (futuro):** la documentación interna/operativa se automatizará
> más adelante con un flujo similar, sobre este mismo plugin pero en otro ámbito
> (documentación de plataforma, no tenant). Cuando se defina, se agregará aquí.
