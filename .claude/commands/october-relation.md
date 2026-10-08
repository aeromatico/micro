Add relationships between OctoberCMS models — updates both models, migrations, and wires the backend RelationController.

## Usage
`/october-relation <Vendor>/<Plugin> <ParentModel> <type> <ChildModel> [--pivot-fields field:type ...]`

**Relation types:** `hasMany`, `hasOne`, `belongsTo`, `belongsToMany`, `morphMany`, `attachOne`, `attachMany`

**Examples:**
- `/october-relation Micro/Blog Post hasMany Comment`
- `/october-relation Micro/Shop Product belongsToMany Category --pivot-fields sort_order:integer`
- `/october-relation Micro/Blog Post attachOne FeaturedImage`
- `/october-relation Micro/Blog Post attachMany Gallery`
- `/october-relation Micro/Events Event morphMany Tag`

---

## What to do

Plugin base: `/www/wwwroot/micro.clouds.com.bo/plugins/{vendor_lower}/{plugin_lower}/`

### Step 1 — Update ParentModel with the relation definition

Open `models/{ParentModel}.php` and add the correct relation property:

**hasMany:**
```php
public $hasMany = [
    'comments' => [\{Vendor}\{Plugin}\Models\Comment::class],
];
```

**belongsTo:**
```php
public $belongsTo = [
    'category' => [\{Vendor}\{Plugin}\Models\Category::class],
];
```

**belongsToMany:**
```php
public $belongsToMany = [
    'categories' => [
        \{Vendor}\{Plugin}\Models\Category::class,
        'table' => '{vendor}_{plugin}_post_category',
        'pivotData' => ['sort_order'],  // if pivot fields provided
    ],
];
```

**attachOne / attachMany (uses System\Models\File):**
```php
public $attachOne = [
    'featured_image' => [\System\Models\File::class],
];
public $attachMany = [
    'gallery' => [\System\Models\File::class],
];
```

### Step 2 — Update ChildModel (for hasMany/belongsTo pair)

For `hasMany` → also add `belongsTo` on the child:
```php
public $belongsTo = [
    'post' => [\{Vendor}\{Plugin}\Models\Post::class],
];
```

### Step 3 — Create/update migration if needed

For `belongsToMany`: create pivot table migration:
```php
Schema::create('{vendor}_{plugin}_{parent_lower}_{child_lower}', function (Blueprint $table) {
    $table->unsignedBigInteger('{parent_lower}_id');
    $table->unsignedBigInteger('{child_lower}_id');
    // pivot fields here if --pivot-fields provided
    $table->primary(['{parent_lower}_id', '{child_lower}_id']);
    $table->foreign('{parent_lower}_id')->references('id')->on('{parent_table}')->onDelete('cascade');
    $table->foreign('{child_lower}_id')->references('id')->on('{child_table}')->onDelete('cascade');
});
```

For `hasMany`: add foreign key to child table (if it doesn't exist).

For `attachOne`/`attachMany`: no migration needed — uses `system_files` table.

### Step 4 — Wire RelationController in the backend

Add `RelationController` behavior to the parent controller:
```php
public $implement = [
    \Backend\Behaviors\FormController::class,
    \Backend\Behaviors\ListController::class,
    \Backend\Behaviors\RelationController::class,  // add this
];

public $relationConfig = 'config_relation.yaml';  // add this
```

Create `controllers/{parent_plural_lower}/config_relation.yaml`:
```yaml
# HasMany example
{child_plural_lower}:
    label: {ChildModels}
    manage:
        title: Gestionar {ChildModel}
        form: $/{vendor_lower}/{plugin_lower}/models/{child_lower}/fields.yaml
        recordsPerPage: 15
    view:
        list: $/{vendor_lower}/{plugin_lower}/models/{child_lower}/columns.yaml
        toolbarButtons: create|delete
    emptyMessage: No hay {child_plural_lower} registrados.

# BelongsToMany example
{child_plural_lower}:
    label: {ChildModels}
    manage:
        title: Relacionar {ChildModel}
        list: $/{vendor_lower}/{plugin_lower}/models/{child_lower}/columns.yaml
        recordsPerPage: 15
    view:
        list: $/{vendor_lower}/{plugin_lower}/models/{child_lower}/columns.yaml
        toolbarButtons: add|remove

# AttachMany example
gallery:
    label: Galería
    manage:
        form: $/backend/models/file/fields.yaml
    view:
        list: $/backend/models/file/columns.yaml
        toolbarButtons: create|delete
```

### Step 5 — Add relation field to parent's fields.yaml

```yaml
{child_plural_lower}:
    label: {ChildModels}
    type: relation
    tab: Relaciones
```

Or for attachOne/attachMany, use fileupload widget instead.

### Step 6 — Run migrations

```
/www/server/php/84/bin/php artisan october:migrate
```

Report all files changed/created and the relation type configured.
