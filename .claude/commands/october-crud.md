Create a complete OctoberCMS backend CRUD — Model, Controller, Form fields, List columns, Filters, and Menu registration — all wired together.

## Usage
`/october-crud <Vendor>/<Plugin> <ModelName> [field:type ...]`

**Examples:**
- `/october-crud Micro/Blog Post title:string slug:string content:richtext published_at:datepicker is_featured:checkbox`
- `/october-crud Micro/Shop Product name:string price:decimal stock:number category:relation image:fileupload`
- `/october-crud Micro/Events Event title:string starts_at:datepicker location:text capacity:number`

## Field type mapping

| Shorthand | Backend form widget | Column type |
|-----------|--------------------|----|
| `string` | `text` | `text` |
| `text` / `longtext` | `textarea` | `text` |
| `richtext` | `richeditor` | `text` |
| `markdown` | `markdown` | `text` |
| `number` / `integer` | `number` | `number` |
| `decimal` | `number` (step 0.01) | `number` |
| `boolean` / `checkbox` | `checkbox` | `switch` |
| `datepicker` / `datetime` | `datepicker` (mode:datetime) | `datetime` |
| `date` | `datepicker` (mode:date) | `date` |
| `color` | `colorpicker` | `text` |
| `image` / `fileupload` | `fileupload` (image:true) | `image` |
| `files` | `fileupload` (maxFiles:10) | `text` |
| `tags` | `taglist` | `text` |
| `relation` | `relation` | `text` (via relation) |
| `repeater` | `repeater` | *(skipped in list)* |
| `code` | `codeeditor` | *(skipped in list)* |
| `select` | `dropdown` | `text` |

---

## What to create

Plugin base: `/www/wwwroot/micro.clouds.com.bo/plugins/{vendor_lower}/{plugin_lower}/`

### 1. Model — `models/{ModelName}.php`

Full model with:
- `$table` named `{vendor}_{plugin}_{model_plural}`
- `$fillable` from all fields
- `$rules` with sensible defaults (required for non-nullable, numeric for numbers)
- `Validation` + `SoftDelete` traits
- Sluggable if slug field present
- `$attachOne` / `$attachMany` if fileupload fields
- `$hasMany` / `$belongsTo` stubs if relation fields
- Accessor methods for computed fields

### 2. Migration — `updates/create_{table}_table.php`

Correct column types for all fields. Always include timestamps + softDeletes.

### 3. Register migration in `updates/version.yaml`

### 4. Backend controller — `controllers/{ModelNamePlural}.php`

```php
<?php namespace {Vendor}\{Plugin}\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

class {ModelNamePlural} extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['{vendor}.{plugin}.access_{model_plural_lower}'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('{Vendor}.{Plugin}', '{model_plural_lower}');
    }
}
```

### 5. Form config — `controllers/{model_plural_lower}/config_form.yaml`

```yaml
name: {ModelName}
form: $/{vendor_lower}/{plugin_lower}/models/{modelname_lower}/fields.yaml
modelClass: {Vendor}\{Plugin}\Models\{ModelName}
defaultRedirect: {vendor_lower}/{plugin_lower}/{model_plural_lower}

create:
    title: Nuevo {ModelName}
    redirect: {vendor_lower}/{plugin_lower}/{model_plural_lower}/update/:id
    redirectClose: {vendor_lower}/{plugin_lower}/{model_plural_lower}

update:
    title: Editar {ModelName}
    redirect: {vendor_lower}/{plugin_lower}/{model_plural_lower}
    redirectClose: {vendor_lower}/{plugin_lower}/{model_plural_lower}
    
preview:
    title: Ver {ModelName}
```

### 6. List config — `controllers/{model_plural_lower}/config_list.yaml`

```yaml
title: {ModelNamePlural}
list: $/{vendor_lower}/{plugin_lower}/models/{modelname_lower}/columns.yaml
filter: $/{vendor_lower}/{plugin_lower}/models/{modelname_lower}/scopes.yaml
modelClass: {Vendor}\{Plugin}\Models\{ModelName}
recordUrl: {vendor_lower}/{plugin_lower}/{model_plural_lower}/update/:id
noRecordsMessage: No hay registros.
recordsPerPage: 20
showSetup: true
showCheckboxes: true

toolbar:
    buttons: list_toolbar
    search:
        prompt: Buscar...

defaultSort:
    column: created_at
    direction: desc
```

### 7. List toolbar partial — `controllers/{model_plural_lower}/_list_toolbar.htm`

```html
<a href="<?= Backend::url('{vendor_lower}/{plugin_lower}/{model_plural_lower}/create') ?>" class="btn btn-primary oc-icon-plus">
    Nuevo {ModelName}
</a>
<button
    class="btn btn-default oc-icon-trash-o"
    data-request="onDelete"
    data-request-confirm="¿Eliminar los seleccionados?"
    data-list-checked-trigger
    data-list-checked-request
    disabled>
    Eliminar
</button>
```

### 8. Fields YAML — `models/{modelname_lower}/fields.yaml`

Build from provided field list. Group in tabs if > 6 fields:

```yaml
fields:
    # Always first tab for main fields, second for meta/settings

tabs:
    fields:
        title:                        # example string field
            label: Título
            type: text
            span: full
            required: true

        slug:                         # example slug (auto-filled)
            label: Slug
            type: text
            span: left
            preset:
                field: title
                type: slug

        content:                      # example richeditor
            label: Contenido
            type: richeditor
            size: huge
            span: full
            tab: Contenido

        image:                        # example fileupload
            label: Imagen
            type: fileupload
            mode: image
            imageWidth: 1200
            imageHeight: 630
            tab: Media

        is_active:                    # example checkbox
            label: Activo
            type: checkbox
            default: true
            tab: Configuración

        published_at:                 # example datepicker
            label: Publicar el
            type: datepicker
            mode: datetime
            span: left
            tab: Configuración
```

### 9. Columns YAML — `models/{modelname_lower}/columns.yaml`

```yaml
columns:
    # Include meaningful columns (skip richtext/code/repeater)
    id:
        label: '#'
        type: number
        width: 60px
    
    # ...generated columns from field list

    is_active:
        label: Activo
        type: switch
        width: 80px

    created_at:
        label: Creado
        type: datetime
        width: 160px
```

### 10. Scopes YAML — `models/{modelname_lower}/scopes.yaml`

```yaml
scopes:
    is_active:
        label: Estado
        type: switch
        conditions: "is_active = :filtered"
        default: 1

    created_at:
        label: Fecha de creación
        type: daterange
        conditions: "created_at >= ':after' AND created_at <= ':before'"
        modelScope: createdBetween
```

### 11. Register in Plugin.php

Add to `registerNavigation()`:
```php
public function registerNavigation(): array
{
    return [
        '{plugin_lower}' => [
            'label'       => '{Plugin}',
            'url'         => Backend::url('{vendor_lower}/{plugin_lower}/{model_plural_lower}'),
            'icon'        => 'icon-list',
            'permissions' => ['{vendor_lower}.{plugin_lower}.*'],
            'order'       => 500,
            'sideMenu' => [
                '{model_plural_lower}' => [
                    'label'       => '{ModelNamePlural}',
                    'icon'        => 'icon-list',
                    'url'         => Backend::url('{vendor_lower}/{plugin_lower}/{model_plural_lower}'),
                    'permissions' => ['{vendor_lower}.{plugin_lower}.access_{model_plural_lower}'],
                ],
            ],
        ],
    ];
}

public function registerPermissions(): array
{
    return [
        '{vendor_lower}.{plugin_lower}.access_{model_plural_lower}' => [
            'tab'   => '{Plugin}',
            'label' => 'Gestionar {ModelNamePlural}',
        ],
    ];
}
```

---

After creating all files:
1. Run: `/www/server/php/84/bin/php artisan october:migrate`
2. Run: `/www/server/php/84/bin/php artisan route:list | grep {vendor_lower}`
3. Show the backend URL: `https://micro.clouds.com.bo/admin/{vendor_lower}/{plugin_lower}/{model_plural_lower}`
4. List every file created with its path
