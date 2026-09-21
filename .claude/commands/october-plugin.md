Create a new OctoberCMS plugin from scratch.

## Usage
`/october-plugin <Vendor> <PluginName> [description]`

**Example:** `/october-plugin Micro Blog "Blog con posts y categorías"`

## What to create

Given `$ARGUMENTS` (e.g. "Micro Blog Blog con posts y categorías"), extract:
- **Vendor** = first word (PascalCase)
- **Plugin** = second word (PascalCase)
- **Description** = rest of the string (or empty)

Create the full plugin scaffold at `/www/wwwroot/micro.clouds.com.bo/plugins/{vendor_lower}/{plugin_lower}/`:

### Required files

**`Plugin.php`**
```php
<?php namespace {Vendor}\{Plugin};

use System\Classes\PluginBase;

class Plugin extends PluginBase
{
    public function pluginDetails(): array
    {
        return [
            'name'        => '{Plugin}',
            'description' => '{description}',
            'author'      => '{Vendor}',
            'icon'        => 'icon-leaf',
        ];
    }

    public function registerComponents(): array
    {
        return [];
    }

    public function registerSettings(): array
    {
        return [];
    }
}
```

**`updates/version.yaml`**
```yaml
1.0.0:
    - First version of {Plugin} plugin.
```

**`lang/en/lang.php`**
```php
<?php return [
    'plugin' => [
        'name'        => '{Plugin}',
        'description' => '{description}',
    ],
];
```

After creating files, run:
```
/www/server/php/84/bin/php artisan october:migrate
```

Report the full directory tree created and any errors.
