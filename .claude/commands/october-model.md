Create a new OctoberCMS Model with database migration and backend controller.

## Usage
`/october-model <Vendor>/<Plugin> <ModelName> [fields...]`

**Example:** `/october-model Micro/Blog Post title:string slug:string content:text published_at:datetime`

## What to create

Given `$ARGUMENTS`, extract:
- **Vendor/Plugin** = plugin namespace
- **ModelName** = singular PascalCase (e.g. `Post`)
- **fields** = `name:type` pairs (types: string, text, integer, boolean, datetime, date, decimal, json)

Plugin base: `/www/wwwroot/micro.clouds.com.bo/plugins/{vendor_lower}/{plugin_lower}/`

### 1. Model — `models/{ModelName}.php`

```php
<?php namespace {Vendor}\{Plugin}\Models;

use October\Rain\Database\Model;

class {ModelName} extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    public $table = '{vendor_lower}_{plugin_lower}_{modelname_plural_lower}';

    protected $guarded = [];

    protected $dates = ['deleted_at'];

    public $rules = [
        // 'title' => 'required|max:255',
    ];

    // Relations
    // public $belongsTo = [];
    // public $hasMany = [];
    // public $belongsToMany = [];
    // public $attachOne = ['image' => \System\Models\File::class];
    // public $attachMany = ['images' => \System\Models\File::class];
}
```

### 2. Migration — `updates/create_{table}_table.php`

```php
<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{table_name}', function (Blueprint $table) {
            $table->id();
            // Generated columns from fields:
            // $table->string('title');
            // $table->string('slug')->unique();
            // $table->text('content')->nullable();
            // $table->boolean('is_active')->default(true);
            // $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{table_name}');
    }
};
```

### 3. Register migration in `updates/version.yaml`

Add the new migration version entry.

### 4. Backend Controller — `controllers/{ModelNamePlural}.php`

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

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('{Vendor}.{Plugin}', 'main-menu-item');
    }
}
```

### 5. List config — `controllers/{modelname_plural_lower}/config_list.yaml`

```yaml
title: {ModelNamePlural}
modelClass: {Vendor}\{Plugin}\Models\{ModelName}
recordOnClick: return this.recordUrl('/{vendor_lower}/{plugin_lower}/{modelname_plural_lower}/update/:id');
recordsPerPage: 20
showCheckboxes: true
toolbar:
    buttons: list_toolbar
    search:
        prompt: Search
columns:
    # title:
    #     label: Title
    #     searchable: true
    created_at:
        label: Created
        type: datetime
```

### 6. Form config — `controllers/{modelname_plural_lower}/config_form.yaml`

```yaml
name: {ModelName}
form: $/{ vendor_lower}/{plugin_lower}/models/{modelname_lower}/fields.yaml
modelClass: {Vendor}\{Plugin}\Models\{ModelName}
create:
    title: New {ModelName}
    redirect: /{vendor_lower}/{plugin_lower}/{modelname_plural_lower}/update/:id
update:
    title: Edit {ModelName}
    redirect: /{vendor_lower}/{plugin_lower}/{modelname_plural_lower}
```

### 7. Fields YAML — `models/{modelname_lower}/fields.yaml`

```yaml
fields:
    # title:
    #     label: Title
    #     type: text
    #     span: full
    #     required: true
```

After all files are created, run:
```
/www/server/php/84/bin/php artisan october:migrate
```

Report all files created, the table name, and the backend URL.
