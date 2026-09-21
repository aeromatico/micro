Create a REST API resource endpoint in OctoberCMS 4 / Laravel 12.

## Usage
`/october-api <Vendor>/<Plugin> <ResourceName> [methods] [--auth] [--public]`

**Examples:**
- `/october-api Micro/Blog Post index,show,store,update,destroy --auth`
- `/october-api Micro/Ecommerce Product index,show --public`
- `/october-api Micro/Orders Order index,store --auth`

## What to create

Given `$ARGUMENTS`, extract:
- **Vendor/Plugin** — plugin namespace
- **ResourceName** — singular PascalCase (e.g. `Post`)
- **methods** — comma-separated: index, show, store, update, destroy (default: all 5)
- **--auth** — requires Bearer token auth (default if not --public)
- **--public** — no auth required

Plugin base: `/www/wwwroot/micro.clouds.com.bo/plugins/{vendor_lower}/{plugin_lower}/`

---

### 1. Routes — `routes.php` (create or append)

```php
<?php

use Illuminate\Support\Facades\Route;
use {Vendor}\{Plugin}\Http\Controllers\Api\{ResourceName}Controller;

Route::prefix('api/v1')->middleware(['api'])->group(function () {

    // {ResourceName} API endpoints
    Route::middleware([/* '--auth' ? 'auth:sanctum' : '' */])->group(function () {
        Route::apiResource('{resource_plural_lower}', {ResourceName}Controller::class);
        // Generated only for requested methods — remove others
    });

});
```

### 2. API Controller — `http/controllers/api/{ResourceName}Controller.php`

```php
<?php namespace {Vendor}\{Plugin}\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use {Vendor}\{Plugin}\Models\{ResourceName};
use {Vendor}\{Plugin}\Http\Resources\{ResourceName}Resource;
use {Vendor}\{Plugin}\Http\Resources\{ResourceName}Collection;

class {ResourceName}Controller extends Controller
{
    public function __construct()
    {
        // if --auth: $this->middleware('auth:sanctum');
    }

    public function index(Request $request): JsonResponse
    {
        $query = {ResourceName}::query();

        // Search
        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        // Filters
        if ($request->has('status')) {
            $query->where('is_active', $request->boolean('status'));
        }

        $items = $query
            ->orderBy($request->input('sort_by', 'created_at'), $request->input('sort_dir', 'desc'))
            ->paginate($request->input('per_page', 15));

        return response()->json(new {ResourceName}Collection($items));
    }

    public function show(int $id): JsonResponse
    {
        $item = {ResourceName}::findOrFail($id);
        return response()->json(new {ResourceName}Resource($item));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // 'name' => 'required|string|max:255',
            // 'slug' => 'required|string|unique:{table_name},slug',
        ]);

        $item = {ResourceName}::create($validated);

        return response()->json(new {ResourceName}Resource($item), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $item = {ResourceName}::findOrFail($id);

        $validated = $request->validate([
            // 'name' => 'sometimes|string|max:255',
        ]);

        $item->update($validated);

        return response()->json(new {ResourceName}Resource($item));
    }

    public function destroy(int $id): JsonResponse
    {
        $item = {ResourceName}::findOrFail($id);
        $item->delete();

        return response()->json(['message' => '{ResourceName} deleted.'], 200);
    }
}
```

### 3. API Resource — `http/resources/{ResourceName}Resource.php`

```php
<?php namespace {Vendor}\{Plugin}\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class {ResourceName}Resource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            // 'name'    => $this->name,
            // 'slug'    => $this->slug,
            // 'status'  => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
```

### 4. Collection — `http/resources/{ResourceName}Collection.php`

```php
<?php namespace {Vendor}\{Plugin}\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class {ResourceName}Collection extends ResourceCollection
{
    public $collects = {ResourceName}Resource::class;

    public function toArray($request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'current_page' => $this->currentPage(),
                'last_page'    => $this->lastPage(),
                'per_page'     => $this->perPage(),
                'total'        => $this->total(),
            ],
        ];
    }
}
```

### 5. If --auth: API Token generation in Plugin.php

```php
// In registerComponents() or boot():
// Add Laravel Sanctum token support
// php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

### 6. Register routes in Plugin.php

Add to `Plugin.php`:
```php
public function boot(): void
{
    $this->app->router->group([], function ($router) {
        require __DIR__ . '/routes.php';
    });
}
```

---

After creating all files:
1. Run: `/www/server/php/84/bin/php artisan route:list --path=api/v1`
2. Show the full API endpoint table (method, URI, controller action)
3. Show example curl commands for testing each endpoint
