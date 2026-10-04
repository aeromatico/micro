# micro.clouds.com.bo — plataforma SaaS multitenant (Aero.*)

OctoberCMS 4 / Laravel 12 / PHP 8.4 / MySQL / Redis / Tailwind 3 + Alpine.js (Pines). Dev server = fuente de verdad: aquí se genera y se hace `git push origin main` (repo `aeromatico/micro`); producción hace pull. Nunca `git pull`.

## Mapa
- `plugins/aero/*` — 23 plugins, cada uno un módulo del SaaS. `aero/sites` es el núcleo (Tenant, planes, roles, grants). Los demás son independientes o se acoplan de forma blanda (`class_exists()` + eventos `aero.*`).
- `themes/demo` (plataforma), `themes/microsites` (sitios de tenants), `themes/master`, `themes/whatsapp`.
- `.claude/skills/aero-plugin` — **empezar aquí para crear o extender un plugin** (scaffold + gotchas + verificación).
- `.claude/agents/` — `git-agent` (cron cada 10 min, commits por área), `docs-sync` (docs/guías con `claude -p`, manual), `docs-agent`.
- Memoria persistente: `~/.claude/projects/-www-wwwroot-micro-clouds-com-bo/memory/` (estado de cada plugin, incidentes).

## Comandos
```bash
PHP=/www/server/php/84/bin/php          # SIEMPRE este; el `php` del PATH es otra versión
sudo -u www $PHP artisan <cmd>          # artisan como `www`, NO como root (ver abajo)
$PHP artisan october:migrate            # único modo de aplicar migraciones
rm -f storage/cms/manifest.php          # si un plugin nuevo no muestra su menú
cd themes/<tema> && npm run build       # Tailwind; luego subir ?v= del asset (Cloudflare cachea 1 año)
```

## Prohibido (ya costó datos o días)
- **NUNCA** `plugin:refresh` ni `plugin:rollback`/`rollbackPlugin()`: ignoran la versión y ejecutan el `down()` de casi todo (13 tablas de Sites perdidas el 2026-09-21). Para cambiar esquema: nueva versión en `version.yaml` + `october:migrate`.
- No correr artisan/tinker que escriba en `storage/` como root: deja archivos `root:root` y PHP-FPM (`www`) responde 500. Si pasa: `chown -R www:www storage/`.
- No poner secretos en `SettingsModel` (sin cifrado) ni en el repo.
- No tocar la rama con `reset`/`rebase`: otras sesiones de Claude escriben en el mismo repo. Verificar con `git log` antes de asumir que un commit propio sigue ahí.

## Reglas de código October (fallan en silencio si se ignoran)
- Subcarpetas de plugin **siempre en minúsculas** (`classes/`, `models/`, `controllers/`, `jobs/`).
- `version.yaml`: cada versión = lista YAML `[línea de changelog, archivo.php, ...]`; un string plano no corre migraciones.
- Permisos: `$user->hasAccess()`, nunca `hasPermission()` (ignora superadmin).
- Pantalla para tenants ⇒ además `registerPermissions` + menú, una migración `grant_*_to_tenant_admin.php` en `aero/sites/updates` (con `class_exists`). Sin ella el tenant ve "denegado".
- Datos de tenant: columna `tenant_id`; resolver con `Aero\Sites\Traits\ResolvesCurrentTenant`; los controllers filtran por tenant y **fallan cerrado**.
- Controller con lista/formulario ⇒ existen `index.php`/`create.php`/`update.php`, si no la página sale en blanco.
- Jobs: trait `Dispatchable`. Carbon 3: `diffInDays($f, true)` si quieres valor absoluto.
- Más detalle: `.claude/skills/aero-plugin/references/gotchas.md`.

## Convenciones
- Idioma de UI, docs y commits: español. Commits Conventional Commits (el git-agent los hace; si commiteas a mano, mismo estilo).
- Nuevos eventos: `aero.{plugin}.{algoOcurrido}`. Integraciones entre plugins: nunca `use` duro fuera de un guard `class_exists()`.
- Antes de crear un plugin, comprobar si la función cabe en uno existente (`ls plugins/aero`).
- Terminar una tarea = **verificado**: migración corrida, ruta/pantalla probada con un usuario del rol correcto, no solo "el código existe".

## Skills útiles
`/aero-plugin` · `/docs-plugin` · `/form-guide` · `/service-offer` · `/pines` · `/october-*` (piezas sueltas: crud, model, api, job, event, mail…).
