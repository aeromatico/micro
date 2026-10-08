Add query scopes, filters, and advanced Eloquent patterns to an OctoberCMS model.

## Usage
`/october-scope <Vendor>/<Plugin> <ModelName> <scope-type> [options]`

**Scope types:** `filter`, `search`, `cache`, `scope`, `observer`

**Examples:**
- `/october-scope Micro/Blog Post filter status,date_range,category` — Add list filters + model scopes
- `/october-scope Micro/Blog Post search title,content,tags` — Full-text search scope
- `/october-scope Micro/Blog Post cache` — Add Redis caching to expensive queries
- `/october-scope Micro/Blog Post observer` — Create a Model Observer

---

## Patterns by type

### `scope` — Local query scopes on the model

Add to `models/{ModelName}.php`:

```php
// Boolean scope: Post::published()->get()
public function scopePublished(Builder $query): Builder
{
    return $query->where('is_active', true)
                 ->where('published_at', '<=', now());
}

// Parameterized: Post::ofCategory($id)->get()
public function scopeOfCategory(Builder $query, int $categoryId): Builder
{
    return $query->where('category_id', $categoryId);
}

// Date range: Post::createdBetween($from, $to)->get()
public function scopeCreatedBetween(Builder $query, string $from, string $to): Builder
{
    return $query->whereBetween('created_at', [
        \Carbon\Carbon::parse($from)->startOfDay(),
        \Carbon\Carbon::parse($to)->endOfDay(),
    ]);
}

// Latest N: Post::recent(5)->get()
public function scopeRecent(Builder $query, int $limit = 10): Builder
{
    return $query->orderByDesc('created_at')->limit($limit);
}

// With eager loads to avoid N+1: Post::withRelations()->get()
public function scopeWithRelations(Builder $query): Builder
{
    return $query->with(['category', 'tags', 'author', 'featuredImage']);
}
```

---

### `filter` — Backend list filters (scopes.yaml + model scopes)

Add to `models/{modelname_lower}/scopes.yaml`:
```yaml
scopes:

    status:
        label: Estado
        type: switch
        conditions: "is_active = :filtered"

    category:
        label: Categoría
        type: relationship
        nameFrom: name
        options: \{Vendor}\{Plugin}\Models\Category

    date_range:
        label: Rango de fechas
        type: daterange
        conditions: "created_at >= ':after' AND created_at <= ':before'"

    featured:
        label: Solo destacados
        type: switch
        conditions: "is_featured = 1"
```

Also add model scopes for each filter field listed above.

---

### `search` — Full-text search scope

Add to `models/{ModelName}.php`:
```php
public function scopeSearch(Builder $query, string $term): Builder
{
    $term = '%' . trim($term) . '%';

    return $query->where(function (Builder $q) use ($term) {
        $q->where('title', 'like', $term)
          ->orWhere('content', 'like', $term)
          ->orWhereHas('tags', fn($t) => $t->where('name', 'like', $term));
    });
}
```

For production full-text (MySQL FULLTEXT):
```php
// In migration — add FULLTEXT index:
// DB::statement('ALTER TABLE posts ADD FULLTEXT ft_search (title, content)');

public function scopeFullTextSearch(Builder $query, string $term): Builder
{
    return $query->whereRaw(
        'MATCH(title, content) AGAINST(? IN BOOLEAN MODE)',
        ['+' . implode('* +', explode(' ', $term)) . '*']
    );
}
```

---

### `cache` — Redis query caching

Add to `models/{ModelName}.php`:
```php
use Illuminate\Support\Facades\Cache;

// Cache an expensive query result
public static function getCached(string $key, int $ttlMinutes = 60, \Closure $query): mixed
{
    return Cache::remember("model.{$key}", $ttlMinutes * 60, $query);
}

// Example cached accessor
public static function getFeatured(int $limit = 6): \Illuminate\Database\Eloquent\Collection
{
    return Cache::remember('posts.featured.' . $limit, 300, function () use ($limit) {
        return static::published()
            ->where('is_featured', true)
            ->withRelations()
            ->recent($limit)
            ->get();
    });
}

// Invalidate cache after save/delete
public static function boot(): void
{
    parent::boot();

    static::saved(function () {
        Cache::forget('posts.featured.6');
        Cache::tags('posts')->flush(); // if Redis supports tags
    });

    static::deleted(function () {
        Cache::forget('posts.featured.6');
    });
}
```

Usage: `Post::getFeatured(3)` — cached for 5 minutes, auto-invalidates on save.

---

### `observer` — Model Observer (clean lifecycle hooks)

Create `observers/{ModelName}Observer.php`:
```php
<?php namespace {Vendor}\{Plugin}\Observers;

use {Vendor}\{Plugin}\Models\{ModelName};
use Illuminate\Support\Str;

class {ModelName}Observer
{
    public function creating({ModelName} $model): void
    {
        // Auto-generate slug before create
        if (empty($model->slug) && !empty($model->title)) {
            $model->slug = Str::slug($model->title);
        }
    }

    public function created({ModelName} $model): void
    {
        // Dispatch job, send notification, etc.
        // \{Vendor}\{Plugin}\Jobs\NotifySubscribers::dispatch($model);
    }

    public function updating({ModelName} $model): void
    {
        // Track changes
        if ($model->isDirty('is_active') && $model->is_active) {
            $model->published_at = $model->published_at ?? now();
        }
    }

    public function updated({ModelName} $model): void
    {
        // Invalidate caches
    }

    public function deleting({ModelName} $model): void
    {
        // Cascade deletes not handled by DB
        // $model->comments()->delete();
    }

    public function deleted({ModelName} $model): void
    {
        // Cleanup files, caches, etc.
    }
}
```

Register in `Plugin.php boot()`:
```php
public function boot(): void
{
    \{Vendor}\{Plugin}\Models\{ModelName}::observe(
        \{Vendor}\{Plugin}\Observers\{ModelName}Observer::class
    );
}
```

---

Report all files created/modified and usage examples for each scope/pattern added.
