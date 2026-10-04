---
name: aero-plugin
description: Crea o extiende un plugin Aero.* de la plataforma SaaS (multitenant, permisos, grant a tenant_admin, API, créditos, eventos, docs) con las convenciones reales del proyecto y los gotchas de October ya resueltos. Úsala cuando pidan "crear un plugin", "nuevo módulo", "agregar pantalla para tenants", "agregar API/créditos a un plugin" o cualquier trabajo de plugins en plugins/aero/. Reemplaza al comando /market-plugin.
---

# aero-plugin — plugin completo y verificado, en una pasada

Una orden = plugin que carga, migra, se ve en el menú **para el rol correcto**, filtra por tenant y queda probado. Lee `references/gotchas.md` antes de escribir código: cada fila costó horas en este proyecto.

Vendor siempre `Aero`; ruta `plugins/aero/{plugin_lower}`, namespace `Aero\{Plugin}`. No preguntes por el vendor.

## 0. Decidir (2 min, sin preguntar si es obvio)
1. `ls plugins/aero/` y `grep -rl "palabra clave" plugins/aero/*/Plugin.php`: si la función cabe en un plugin existente, **extiéndelo** (usa `/october-crud`, `/october-model`, etc.) en vez de crear otro.
2. ¿Tiene datos de tenant? (casi siempre sí) → tenant_id + scoping + grant.
3. ¿Se cobra? → acción en Aero.Credits. ¿Expone API? → Aero.Api. ¿Notifica? → eventos para Aero.Notify.
4. Monorepo por defecto. Submódulo (`github.com/aeromatico/{plugin}`) solo si es producto independiente, y **confirmar con la persona** antes de `git submodule add`.
5. Modelo de referencia (copiar patrones, no inventar): **`aero/sms`** (tenant + API + créditos + permisos + grant), `aero/credits` (integración por eventos), `aero/notify` (única dependencia dura legítima: `$require = ['Aero.Sites']`).

## 1. Esqueleto
Crea solo lo necesario, todo en **minúsculas**:
```
plugins/aero/{p}/Plugin.php   classes/  controllers/  models/  jobs/  console/  http/controllers/api/
                              lang/es/lang.php  updates/version.yaml  updates/create_*.php  routes.php (si hay API)
```
- `Plugin.php`: copia la forma de `plugins/aero/sms/Plugin.php` (permisos `aero.{p}.use` para tenant y `aero.{p}.superadmin` para plataforma, navegación con `sideMenu`, `order` sin colisión: mira los de los demás con `grep -rn "'order'" plugins/aero/*/Plugin.php`).
- Íconos: solo los que existen (lista en `references/gotchas.md`).
- `version.yaml`: `1.0.0: ["Línea de changelog.", create_x_table.php]`. Cada archivo listado debe existir.
- Tablas: prefijo `aero_{p}_`; `tenant_id` como `unsignedBigInteger` nullable + índice `['tenant_id','created_at']` (sin FK, como sms).
- Usa `templates/` de esta skill: `CurrentTenant.php.tpl`, `ScopesToTenant.php.tpl`, `grant_to_tenant_admin.php.tpl`, `seed_credit_action.php.tpl` (quitar `.tpl`). Sustituye `{Plugin}`, `{p}`.

## 2. Tenant, permisos y grant (lo que más se olvida)
- Controllers de listas/formularios: `use \Aero\{Plugin}\Classes\ScopesToTenant;` (falla cerrado).
- Superadmin ve todo vía `hasAccess('aero.{p}.superadmin')`.
- **Grant obligatorio** si el tenant usa la pantalla: copia `templates/grant_to_tenant_admin.php.tpl` a `plugins/aero/sites/updates/grant_{p}_to_tenant_admin.php`, añade la versión nueva en `plugins/aero/sites/updates/version.yaml` (siguiente minor) y corre `october:migrate`. Sin esto el tenant ve "denegado".
- Cada controller del backend: `index.php`, `create.php`, `update.php` y `BackendMenu::setContext('Aero.{Plugin}', '{p}', '{item}')`.

## 3. Integraciones opcionales (siempre detrás de `class_exists`)
- **Créditos**: `templates/seed_credit_action.php.tpl`; cobrar con el servicio de Credits como hace `aero/sms/classes/Billing.php`.
- **API REST**: eventos `aero.api.registerScopes` y `aero.api.registerEndpoints` (ver Plugin.php de sms) + `routes.php` + `http/controllers/api/`.
- **Eventos propios**: `aero.{p}.{algoOcurrido}` en pasado.
- **Notify**: declara los eventos para el catálogo de Aero.Notify si aplica.

## 4. Aplicar y VERIFICAR (no se da por terminado sin esto)
```bash
sudo -u www /www/server/php/84/bin/php artisan october:migrate     # NUNCA plugin:refresh / rollback
rm -f storage/cms/manifest.php
bin/verify-plugin {p}          # compuerta: lint, version.yaml, vistas, filtros, alias $/, permisos, grants, íconos, tenant + tests
bin/aero-test {p} [filtro]     # solo los tests (SQLite en memoria, aislados de producción)
```
`verify-plugin` debe salir sin ERRORES (los avisos se revisan, no bloquean). Si el plugin no tiene tests, crea `phpunit.xml` y `tests/SmokeTest.php` desde `templates/*.tpl` (ejemplo funcionando: `plugins/aero/sms/tests/`) y añade al menos un test de **aislamiento por tenant**.

Además, verificación funcional con tinker (como `www`), que los tests no sustituyen:
- Con un usuario real del rol `tenant_admin`: `$u->hasAccess('aero.{p}.use')` es `true` y `NavigationManager::instance()->listSideMenuItems(...)` muestra el ítem.
- Si hay API: un `curl` con una key de prueba al endpoint principal.
- `curl -sI` a la URL del backend → 200/302 (no 500); `storage/logs/system.log` sin errores nuevos.
Reporta qué verificaste y qué NO pudiste verificar.

**Tests: reglas de oro.** Nunca correr `phpunit` pelado: `phpunit.xml` debe forzar (`force="true"`) `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `CACHE_STORE=array`, `SESSION_DRIVER=array`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array`. Sin `CACHE_STORE=array` los tests leen la configuración de correo del Redis de producción y fallan (`Mailer [zeptomail] is not defined`). Los seeds/migraciones que tocan otro plugin deben comprobar `Schema::hasTable(...)`, no solo `class_exists`.

## 5. Cierre
- Si tiene pantallas: invoca `/docs-plugin {Plugin}` para dejar la documentación como borrador.
- No commitees a mano: el `git-agent` lo hace por área. Si hace falta, Conventional Commits en español.
- Actualiza la memoria del proyecto con una entrada `project_{p}_plugin.md` (versión, qué hace, qué falta) y una línea en `MEMORY.md`.

## Piezas sueltas (comandos genéricos)
`/october-crud` `/october-model` `/october-api` `/october-job` `/october-event` `/october-mail` `/october-command` `/october-scope` `/october-relation` `/october-component` `/october-tailor` `/october-page` `/october-partial`. Úsalos para añadir una pieza a un plugin ya existente; esta skill ya contempla las convenciones de plataforma que ellos no conocen.
