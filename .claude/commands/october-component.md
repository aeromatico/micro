Create a new OctoberCMS component inside an existing plugin.

## Usage
`/october-component <Vendor>/<Plugin> <ComponentName> [description]`

**Example:** `/october-component Micro/Blog PostList "Lista paginada de posts"`

## What to create

Given `$ARGUMENTS`, extract:
- **Vendor/Plugin** = first argument (determines namespace and directory)
- **ComponentName** = second argument (PascalCase)
- **Description** = rest

Plugin base path: `/www/wwwroot/micro.clouds.com.bo/plugins/{vendor_lower}/{plugin_lower}/`

### 1. Component class — `components/{ComponentName}.php`

```php
<?php namespace {Vendor}\{Plugin}\Components;

use Cms\Classes\ComponentBase;

class {ComponentName} extends ComponentBase
{
    public function componentDetails(): array
    {
        return [
            'name'        => '{ComponentName}',
            'description' => '{description}',
        ];
    }

    public function defineProperties(): array
    {
        return [
            // 'perPage' => [
            //     'title'       => 'Items per page',
            //     'type'        => 'string',
            //     'default'     => '10',
            //     'validationPattern' => '^[0-9]+$',
            // ],
        ];
    }

    public function onRun(): void
    {
        $this->page['{componentName_camel}'] = $this->load{ComponentName}();
    }

    protected function load{ComponentName}(): mixed
    {
        return null;
    }
}
```

### 2. Component template — `components/{componentname_lower}/default.htm`

```twig
<!-- {ComponentName} Component -->
<div x-data="{ open: false }" class="w-full">
    {# Component content here using Pines + Tailwind + Alpine.js #}
</div>
```

### 3. Register in Plugin.php

Open the plugin's `Plugin.php` and add the component to `registerComponents()`:
```php
\{Vendor}\{Plugin}\Components\{ComponentName}::class => '{vendorPlugin}{ComponentName}',
```

Report what was created and the component tag name to use in .htm files.
