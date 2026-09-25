Scaffold a new `Aero.*` plugin that extends the market.com.bo SaaS platform, wired consistently with the rest of the ecosystem from the first commit.

## Usage
`/market-plugin <PluginName> [description] [--requires Sites,Other] [--submodule]`

**Examples:**
- `/market-plugin Invoicing "Facturación electrónica por tenant"`
- `/market-plugin Reviews "Reseñas de clientes en micrositios" --requires Sites`
- `/market-plugin Forms "Constructor de formularios standalone" --submodule`

Vendor is always `Aero` — every plugin in this repo lives at `plugins/aero/{plugin_lower}` under namespace `Aero\{Plugin}`. Don't ask for a vendor; only override it if the user explicitly names a different one.

---

## What this platform is

This isn't a generic OctoberCMS install — it's a growing marketplace of `Aero.*` plugins, each a module of one SaaS product (`panel.market.com.bo`). `Aero.Sites` is the core: it owns the `Tenant` model, multi-tenancy, plans, and billing. Every other plugin either:
- **stands alone** (no dependency on any other `Aero.*` plugin — e.g. a plugin that only talks to third-party APIs), or
- **integrates loosely** with siblings via `class_exists()` guards + Laravel events — never a hard `use` of another plugin's class outside that guard, and
- **rarely** declares a hard `$require` — only when the plugin is *meaningless* without the other (e.g. `Aero.Notify` requires `Aero.Sites` because every notification rule needs a `Tenant`).

Read the real pattern before writing new integration code:
- `plugins/aero/credits/Plugin.php` — `bootConnectorIntegration()`, `bootPurchaseIntegration()`: `class_exists()` guard, then `Event::listen()`.
- `plugins/aero/notify/Plugin.php` — `$require = ['Aero.Sites']` (the one legitimate hard dependency), optional siblings (Hello, Api, Crm, Qrbo, Shop) detected via `class_exists()`.
- `plugins/aero/sites/Plugin.php` — `bootHelloIntegration()` and similar, for the platform-core side of the same pattern.

**Before scaffolding, check whether the feature belongs in an existing plugin instead of a new one:**
```
ls plugins/aero/
grep -rl "relevant keyword" plugins/aero/*/Plugin.php
```
If it's a new capability on an existing domain (e.g. another CRM report), extend that plugin with `/october-crud` or `/october-scope` instead of creating a new one. Only scaffold a new plugin for a genuinely new bounded domain.

---

## Step 1 — Decide monorepo vs. submodule

Default: **monorepo** (`plugins/aero/{plugin}` committed directly to this repo, like `credits`, `notify`, `sites`, `crm`, `shop`).

Use `--submodule` (separate GitHub repo under `github.com/aeromatico/{plugin}`, added via `git submodule add`) only when the plugin is meant to be a standalone, independently versioned product — the existing precedents are `hello`, `pay` (repo `qrbo`), `aifields`, `api` (repo `api-market`). Ask the user to confirm before running `git submodule add` — it's a repo-structure change, not just a file scaffold.

---

## Step 2 — Scaffold files

Base path: `/www/wwwroot/micro.clouds.com.bo/plugins/aero/{plugin_lower}/`

### `Plugin.php`

```php
<?php namespace Aero\{Plugin};

use Backend;
use System\Classes\PluginBase;

/**
 * {One or two lines: what this plugin owns in the platform, and its
 * integration boundary — who it depends on, who depends on it, which
 * siblings it detects optionally via class_exists().}
 */
class Plugin extends PluginBase
{
    // Only declare $require for a genuinely hard dependency (see notify's
    // Aero.Sites case). Omit entirely for standalone or loosely-coupled plugins.
    // public $require = ['Aero.Sites'];

    public function pluginDetails(): array
    {
        return [
            'name'        => 'aero.{plugin_lower}::lang.plugin.name',
            'description' => 'aero.{plugin_lower}::lang.plugin.description',
            'author'      => 'Aero',
            'icon'        => 'icon-{pick a real Font Awesome icon}',
            'homepage'    => 'https://panel.market.com.bo',
        ];
    }

    public function register(): void
    {
        // $this->registerConsoleCommand('{plugin_lower}:something', \Aero\{Plugin}\Console\Something::class);
    }

    public function boot(): void
    {
        // $this->bootSomeIntegration();
    }

    /**
     * Example of the platform's loose-coupling pattern — copy this shape for
     * any integration with another Aero.* plugin. Never assume the sibling
     * plugin is installed.
     */
    // protected function bootSomeIntegration(): void
    // {
    //     if (!class_exists(\Aero\Other\Models\Something::class)) {
    //         return;
    //     }
    //
    //     Event::listen('aero.other.somethingHappened', function ($thing) {
    //         // react here
    //     });
    // }

    public function registerPermissions(): array
    {
        return [
            'aero.{plugin_lower}.access' => [
                'tab'   => 'aero.{plugin_lower}::lang.plugin.name',
                'label' => 'aero.{plugin_lower}::lang.permissions.access',
            ],
        ];
    }

    public function registerNavigation(): array
    {
        return [
            '{plugin_lower}' => [
                'label'       => 'aero.{plugin_lower}::lang.menu.{plugin_lower}',
                'url'         => Backend::url('aero/{plugin_lower}/{maincontroller_lower}'),
                'icon'        => 'icon-{same as pluginDetails}',
                'permissions' => ['aero.{plugin_lower}.access'],
                'order'       => 500, // check existing entries below to avoid collisions
            ],
        ];
    }

    // Only if the plugin has backend settings — omit otherwise. SettingsModel
    // has no Encryptable/mutator support: never store secrets on it directly.
    // public function registerSettings(): array
    // {
    //     return [
    //         'settings' => [
    //             'label'       => 'aero.{plugin_lower}::lang.plugin.name',
    //             'description' => 'aero.{plugin_lower}::lang.settings.description',
    //             'category'    => 'Sistema',
    //             'icon'        => 'icon-{same}',
    //             'class'       => \Aero\{Plugin}\Models\Settings::class,
    //             'order'       => 500,
    //             'permissions' => ['aero.{plugin_lower}.access'],
    //             'keywords'    => '{search keywords}',
    //         ],
    //     ];
    // }
}
```

Check current `order` values in sibling plugins' `registerNavigation()`/`registerSettings()` before picking a number — collisions just reorder silently, they don't error, so they're easy to miss.

### `lang/en/lang.php`

```php
<?php return [
    'plugin' => [
        'name'        => '{Plugin}',
        'description' => '{description}',
    ],
    'menu' => [
        '{plugin_lower}' => '{Plugin}',
    ],
    'permissions' => [
        'access' => 'Access {Plugin}',
    ],
];
```
Add a `'settings' => ['description' => '...']` key only if `registerSettings()` is used.

### `updates/version.yaml`

```yaml
1.0.0:
    - "First version of {Plugin} — {one-line summary of what it does in the platform}."
    - create_{table}_table.php
```
**Format matters**: the value under each version is a YAML list where the first item is a free-text changelog line and every subsequent item is a migration filename relative to `updates/` — a plain string value does not run migrations (fails silently, no error). Every migration file referenced here must actually exist in `updates/`.

### Standard subfolder layout (create only what's needed)
```
plugins/aero/{plugin_lower}/
├── Plugin.php
├── classes/          — services, business logic (not Eloquent models)
├── console/           — Artisan commands
├── controllers/        — backend CRUD (PascalCase plural)
├── models/            — Eloquent models
├── partials/          — shared .htm partials (navbar widgets, etc.)
├── reportwidgets/       — dashboard widgets, if any
├── lang/en/lang.php
└── updates/
    ├── version.yaml
    └── create_*_table.php
```
**All subfolder names must be lowercase** (`classes/`, `models/`, `console/`, `reportwidgets/` — never `Classes/`/`Models/`). October's autoloader resolves the namespace-to-path mapping case-sensitively on this filesystem; a capitalized folder means the class silently fails to autoload.

---

## Step 3 — Tenant scoping (if the plugin holds tenant data)

Most plugins in this platform are multi-tenant. If `{Plugin}`'s data belongs to a tenant:
- Add `tenant_id` FK to `Aero.Sites`' `Tenant` model on the owning table(s), never a copy of tenant logic.
- Resolve the current tenant the same way sibling plugins do — grep `resolveCurrentTenantId` in `plugins/aero/credits/classes/Credits.php` or `plugins/aero/sites` for the canonical helper, don't reinvent it.
- If the plugin exposes backend screens the `tenant_admin` role should reach, add a `grant_*` migration in `Aero.Sites` (or the new plugin, mirroring the Hello→Conectar precedent) — screens are **denied by default** to `tenant_admin` until explicitly granted, even with correct `registerPermissions()`.
- Backend permission checks must use `$user->hasAccess(...)`, never `hasPermission(...)` — `hasPermission()` ignores `is_superuser` and will incorrectly lock out the platform superadmin.

---

## Step 4 — October/Laravel pitfalls specific to this codebase

Check every one of these before considering the plugin done — all have caused real silent failures here:

| Pitfall | Symptom | Fix |
|---|---|---|
| Capitalized subfolder (`Models/`, `Classes/`) | Class not found, no error | Lowercase all subfolders |
| `version.yaml` value as plain string | Migrations never run, no error | Value must be a YAML list: changelog line + filenames |
| Queued Job missing `Dispatchable` trait | `::dispatch()` throws `BadMethodCallException` | `use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;` |
| Validation rule `'array'` with `protected $casts = [...'array']` | Validation fails even with valid array | Use `$jsonable` instead of `$casts` for that field, or drop the `'array'` rule |
| Secrets on a `SettingsModel` | Stored in plaintext, no mutator support | Don't put API keys/tokens on `SettingsModel`; use `Aero.Api`'s key store or an encrypted column on a real model |
| `config_filter.yaml` scope `type: relation` or `type: group` | 500 error | Only `checkbox`, `switch`, `dropdown`, `widget` are valid filter scope types |
| `ReportWidget` partial in `widget.php` | Widget renders blank | Must be `{Widget}/partials/_widget.php` |
| Controller without `index.php`/`create.php`/`update.php` | Menu item shows, page is blank, no error, unrelated to permissions | Create the matching view file for every `ListController`/`FormController` behavior config |
| New plugin's menu missing after creation | Nothing shown, no error | `rm storage/cms/manifest.php` (or `php artisan cache:clear`) then reload |
| Ran `php artisan plugin:refresh` or `plugin:rollback {code}` to "reset" a plugin | **Destroys data across unrelated plugins** — `rollback` ignores the version argument and runs nearly every registered `down()` | **Never run either.** To apply schema changes, add a new version entry to `version.yaml` and run `october:migrate` |

---

## Step 5 — Apply migrations

```bash
/www/server/php/84/bin/php artisan october:migrate
```
If the new plugin's backend menu doesn't appear:
```bash
rm -f storage/cms/manifest.php
```
Report the full directory tree created, the migration output, and confirm the menu item is visible.

---

## Next steps

- Models/controllers/CRUD screens → `/october-crud`
- Client-managed content instead of plugin logic → `/october-tailor`
- Public API → `/october-api`
- Background/async work → `/october-job`
- Cross-plugin reaction to a platform event → `/october-event` (name new events `aero.{plugin_lower}.{eventInPastTense}`, mirroring `aero.pay.paymentReceived`, `aero.connector.beforeRun`/`afterRun`)
- Once the plugin has real backend screens, run `/docs` (or let `docs-agent` pick it up) so `/docs/estructura.md` and `docs/skills/backend.md` stay in sync
