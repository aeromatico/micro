Create a queued Job for OctoberCMS 4 / Laravel 12, configured to use Redis queue.

## Usage
`/october-job <Vendor>/<Plugin> <JobName> [--scheduled cron] [--chain]`

**Examples:**
- `/october-job Micro/Blog SendWeeklyDigest --scheduled "0 8 * * 1"` — Every Monday at 8am
- `/october-job Micro/Ecommerce ProcessOrder` — Manual/event-triggered job
- `/october-job Micro/Media OptimizeImages --chain` — Part of a job chain

---

## What to create

Plugin base: `/www/wwwroot/micro.clouds.com.bo/plugins/{vendor_lower}/{plugin_lower}/`

### 1. Job class — `jobs/{JobName}.php`

```php
<?php namespace {Vendor}\{Plugin}\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class {JobName} implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;          // seconds
    public int $backoff = 30;          // retry delay seconds
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        // Inject minimal data — avoid passing full objects when possible
        // public readonly int $postId,
        // public readonly string $email,
    ) {}

    public function handle(): void
    {
        // --- Job logic here ---
        // Use $this->postId, $this->email, etc.
        
        // Example: send email
        // $post = Post::findOrFail($this->postId);
        // Mail::to($this->email)->send(new DigestMail($post));
        
        // Example: process data in chunks
        // Post::published()->chunk(100, function ($posts) {
        //     foreach ($posts as $post) {
        //         // process...
        //     }
        // });
    }

    public function failed(\Throwable $exception): void
    {
        // Called after all retries exhausted
        \Log::error("{JobName} failed permanently: " . $exception->getMessage(), [
            // 'post_id' => $this->postId,
        ]);
        
        // Optional: notify admin
        // Notification::route('mail', config('mail.from.address'))->notify(...);
    }

    // Optional: uniqueness lock to prevent duplicate jobs
    // public function uniqueId(): string
    // {
    //     return $this->postId;
    // }
}
```

### 2. Dispatch the job (usage examples)

```php
// Fire and forget (default queue)
{JobName}::dispatch();

// Delayed
{JobName}::dispatch()->delay(now()->addMinutes(5));

// Specific queue
{JobName}::dispatch()->onQueue('emails');

// Chain multiple jobs
use Illuminate\Support\Facades\Bus;
Bus::chain([
    new {JobName}(),
    new AnotherJob(),
])->dispatch();

// Batch (parallel, with callbacks)
$batch = Bus::batch([
    new {JobName}(1),
    new {JobName}(2),
    new {JobName}(3),
])->then(function (\Illuminate\Bus\Batch $batch) {
    // All jobs completed
})->catch(function (\Illuminate\Bus\Batch $batch, \Throwable $e) {
    // First batch job failure
})->dispatch();
```

### 3. If --scheduled: Register as a scheduled command

Create `console/{JobName}Command.php`:
```php
<?php namespace {Vendor}\{Plugin}\Console;

use Illuminate\Console\Command;
use {Vendor}\{Plugin}\Jobs\{JobName};

class {JobName}Command extends Command
{
    protected $signature = '{vendor_lower}:{job_lower} {--force : Skip confirmation}';
    protected $description = 'Dispatch the {JobName} job';

    public function handle(): int
    {
        {JobName}::dispatch();
        $this->info('{JobName} dispatched to queue.');
        return self::SUCCESS;
    }
}
```

Register in `Plugin.php`:
```php
public function register(): void
{
    $this->app->singleton('{vendor_lower}.{job_lower}', function () {
        return new \{Vendor}\{Plugin}\Console\{JobName}Command;
    });
}

public function registerSchedule(\Illuminate\Console\Scheduling\Schedule $schedule): void
{
    $schedule->command('{vendor_lower}:{job_lower}')
             ->cron('{cron_expression}')   // e.g. '0 8 * * 1'
             ->withoutOverlapping()
             ->runInBackground()
             ->onFailure(function () {
                 \Log::error('{JobName} scheduled run failed');
             });
}
```

### 4. Verify Redis queue is configured

Confirm `.env` has:
```
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=c7aa40066dcb9340
REDIS_PORT=6379
```

### 5. Run queue worker (for testing)

```bash
/www/server/php/84/bin/php artisan queue:work redis --queue=default --tries=3 --timeout=60
```

For production with supervisor, show the supervisor config at `/etc/supervisor/conf.d/{plugin_lower}-worker.conf`:
```ini
[program:{plugin_lower}-worker]
command=/www/server/php/84/bin/php /www/wwwroot/micro.clouds.com.bo/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
directory=/www/wwwroot/micro.clouds.com.bo
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www
numprocs=2
redirect_stderr=true
stdout_logfile=/www/wwwlogs/{plugin_lower}-worker.log
stopwaitsecs=3600
```

---

After creating files:
1. Show the dispatch command and examples
2. Show how to monitor: `php artisan queue:monitor redis:default`
3. Show how to retry failed jobs: `php artisan queue:retry all`
