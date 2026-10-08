Create OctoberCMS/Laravel events, listeners, subscribers, and hooks — the full event-driven backend pattern.

## Usage
`/october-event <Vendor>/<Plugin> <action> <EventName> [options]`

**Actions:** `fire`, `listen`, `subscribe`, `hook`

**Examples:**
- `/october-event Micro/Blog fire PostPublished` — Create an Event class to fire
- `/october-event Micro/Blog listen PostPublished SendNotificationEmail` — Create a Listener
- `/october-event Micro/Blog subscribe UserActivity` — Create an Event Subscriber
- `/october-event Micro/Blog hook cms.page.beforeDisplay` — Listen to OctoberCMS core hook

---

## Patterns by action

### `fire` — Create a dispatchable Event class

Create `events/{EventName}.php`:
```php
<?php namespace {Vendor}\{Plugin}\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use {Vendor}\{Plugin}\Models\{RelatedModel};

class {EventName}
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly {RelatedModel} $model,
        public readonly array $context = [],
    ) {}
}
```

To fire it from anywhere:
```php
{EventName}::dispatch($model);
// or:
event(new {EventName}($model));
```

Register in `Plugin.php boot()` if needed.

---

### `listen` — Create a queued Listener for an Event

Create `listeners/{ListenerName}.php`:
```php
<?php namespace {Vendor}\{Plugin}\Listeners;

use {Vendor}\{Plugin}\Events\{EventName};
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class {ListenerName} implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'default';
    public int $tries = 3;
    public int $backoff = 60;

    public function handle({EventName} $event): void
    {
        // $event->model — the model that triggered the event
        // $event->context — extra data
        
        // Example: send email
        // Mail::to($event->model->user->email)->send(new SomeMail($event->model));
    }

    public function failed({EventName} $event, \Throwable $exception): void
    {
        // Log or notify on permanent failure
        \Log::error(class_basename($this) . ' failed: ' . $exception->getMessage());
    }
}
```

Register in `Plugin.php`:
```php
public function boot(): void
{
    \Event::listen(
        \{Vendor}\{Plugin}\Events\{EventName}::class,
        \{Vendor}\{Plugin}\Listeners\{ListenerName}::class,
    );
}
```

---

### `subscribe` — Event Subscriber (groups multiple listeners)

Create `subscribers/{SubscriberName}Subscriber.php`:
```php
<?php namespace {Vendor}\{Plugin}\Subscribers;

use Illuminate\Events\Dispatcher;
use {Vendor}\{Plugin}\Events\PostPublished;
use {Vendor}\{Plugin}\Events\PostDeleted;

class {SubscriberName}Subscriber
{
    public function onPostPublished(PostPublished $event): void
    {
        // Handle post published
    }

    public function onPostDeleted(PostDeleted $event): void
    {
        // Handle post deleted
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            PostPublished::class => 'onPostPublished',
            PostDeleted::class   => 'onPostDeleted',
        ];
    }
}
```

Register in `Plugin.php boot()`:
```php
\Event::subscribe(\{Vendor}\{Plugin}\Subscribers\{SubscriberName}Subscriber::class);
```

---

### `hook` — Listen to OctoberCMS core system events

Add to `Plugin.php boot()`. Common hooks:

**CMS Hooks:**
```php
// Before a page is displayed
\Event::listen('cms.page.beforeDisplay', function ($controller, $url, $page) {
    // $controller — CMS Controller instance
    // Redirect, add data, check auth, etc.
});

// After a page is displayed  
\Event::listen('cms.page.postprocess', function ($controller, $url, $page, &$dataHolder) {
    $dataHolder->content = str_replace('foo', 'bar', $dataHolder->content);
});

// Component before run
\Event::listen('cms.component.beforeRunAjaxHandler', function ($component, $handler) {
    // intercept AJAX
});
```

**Model Hooks (extend any model):**
```php
// Extend any model
\{Vendor}\{Plugin}\Models\Post::extend(function ($model) {
    $model->bindEvent('model.afterSave', function () use ($model) {
        // Fires after save
    });
    
    $model->bindEvent('model.beforeCreate', function () use ($model) {
        $model->slug = str_slug($model->title);
    });
});

// OctoberCMS model events: beforeCreate, afterCreate, beforeSave, afterSave,
// beforeUpdate, afterUpdate, beforeDelete, afterDelete, beforeFetch, afterFetch
```

**Backend List/Form Hooks:**
```php
// Add extra columns to any backend list
\Event::listen('backend.list.extendColumns', function ($widget) {
    if (!$widget->getController() instanceof \{Vendor}\{Plugin}\Controllers\Posts) {
        return;
    }
    $widget->addColumns([
        'extra_info' => ['label' => 'Info Extra'],
    ]);
});

// Add extra fields to any backend form
\Event::listen('backend.form.extendFields', function ($widget) {
    if (!$widget->model instanceof \{Vendor}\{Plugin}\Models\Post) {
        return;
    }
    $widget->addFields([
        'custom_note' => ['label' => 'Nota', 'type' => 'text'],
    ]);
});
```

**Mail Events:**
```php
\Event::listen('mailer.beforeSend', function ($mailer, $view, $data) {
    // intercept email sending
});
```

---

Report all files created, where events are registered, and how to test/trigger them.
