Create an Artisan console command for an OctoberCMS plugin.

## Usage
`/october-command <Vendor>/<Plugin> <CommandName> <signature> [--scheduled cron]`

**Examples:**
- `/october-command Micro/Blog CleanDrafts blog:clean-drafts {--days=30 : Drafts older than N days}`
- `/october-command Micro/Ecommerce SyncInventory shop:sync-inventory {source : external|csv|api}`
- `/october-command Micro/Reports GenerateMonthly reports:monthly --scheduled "0 1 1 * *"`

---

## What to create

Plugin base: `/www/wwwroot/micro.clouds.com.bo/plugins/{vendor_lower}/{plugin_lower}/`

### 1. Command class — `console/{CommandName}.php`

```php
<?php namespace {Vendor}\{Plugin}\Console;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;

class {CommandName} extends Command
{
    use ConfirmableTrait;

    protected $signature = '{signature}';
    /*
     * Signature examples:
     *   blog:clean-drafts           — simple command
     *   blog:clean-drafts {--days=30 : Older than N days}  — optional option
     *   shop:sync {source}          — required argument
     *   reports:export {type?}      — optional argument
     *   cache:warm {model*}         — variadic argument (multiple values)
     */

    protected $description = '{CommandName description}';

    public function handle(): int
    {
        // Guard for production (use --force to bypass in production)
        // if (!$this->confirmToProceed('This will affect production data!')) {
        //     return self::FAILURE;
        // }

        // Access options and arguments
        // $days   = (int) $this->option('days');
        // $source = $this->argument('source');

        // Progress bar for long operations
        $this->info('Starting {CommandName}...');
        $items = collect(); // replace with actual query

        $bar = $this->output->createProgressBar($items->count());
        $bar->start();

        $processed = 0;
        $errors = 0;

        foreach ($items as $item) {
            try {
                // Process each item
                $processed++;
            } catch (\Throwable $e) {
                $errors++;
                $this->warn("  Error on #{$item->id}: {$e->getMessage()}");
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        // Summary table
        $this->table(
            ['Metric', 'Value'],
            [
                ['Processed', $processed],
                ['Errors',    $errors],
                ['Duration',  now()->diffForHumans(null, true)],
            ]
        );

        if ($errors > 0) {
            $this->warn("Completed with {$errors} error(s).");
            return self::FAILURE;
        }

        $this->info('Done.');
        return self::SUCCESS;
    }
}
```

### 2. Register in Plugin.php

```php
public function register(): void
{
    $this->registerConsoleCommand('{vendor_lower}.{command_lower}', function () {
        return new \{Vendor}\{Plugin}\Console\{CommandName};
    });
}
```

Or for multiple commands:
```php
public function register(): void
{
    $this->commands([
        \{Vendor}\{Plugin}\Console\{CommandName}::class,
    ]);
}
```

### 3. If --scheduled: Add to schedule

```php
public function registerSchedule(\Illuminate\Console\Scheduling\Schedule $schedule): void
{
    $schedule->command('{artisan_signature}')
             ->cron('{cron_expression}')    // e.g. '0 1 1 * *' (1am on 1st of month)
             ->withoutOverlapping(10)       // lock for max 10 minutes
             ->runInBackground()
             ->sendOutputTo(storage_path('logs/{command_lower}.log'))
             ->onFailure(function () {
                 \Log::error('{CommandName} scheduled run failed');
             })
             ->onSuccess(function () {
                 \Log::info('{CommandName} ran successfully');
             });
}
```

Common cron expressions:
- `* * * * *` — Every minute
- `0 * * * *` — Every hour
- `0 8 * * *` — Daily at 8am
- `0 8 * * 1` — Every Monday at 8am
- `0 1 1 * *` — First of month at 1am
- `0 2 * * 0` — Every Sunday at 2am

### 4. Verify cron is running (AApanel)

```bash
# Check scheduled tasks
/www/server/php/84/bin/php artisan schedule:list

# Run scheduler manually (call this via cron every minute)
/www/server/php/84/bin/php artisan schedule:run

# Add to system cron if not already present:
# * * * * * /www/server/php/84/bin/php /www/wwwroot/micro.clouds.com.bo/artisan schedule:run >> /dev/null 2>&1
```

To add the cron entry:
```bash
crontab -e
# Add: * * * * * /www/server/php/84/bin/php /www/wwwroot/micro.clouds.com.bo/artisan schedule:run >> /dev/null 2>&1
```

---

After creating:
1. Show: `/www/server/php/84/bin/php artisan {signature} --help`
2. Run it once to verify: `/www/server/php/84/bin/php artisan {signature}`
3. If scheduled, show: `/www/server/php/84/bin/php artisan schedule:list`
