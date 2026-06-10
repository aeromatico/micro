<?php namespace Aero\Sites\Http\Controllers\Api;

use Aero\Sites\Http\Resources\PageResource;
use Aero\Sites\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PagesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $query = Page::forTenant($tenant->id)->published()->orderBy('sort_order');

        if ($layout = $request->query('layout')) {
            $query->where('layout', $layout);
        }

        $pages = $query->get();

        return response()->json([
            'data' => PageResource::collection($pages),
        ]);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $page = Page::forTenant($tenant->id)
            ->published()
            ->where('slug', $slug)
            ->first();

        if (!$page) {
            return response()->json(['error' => 'not_found', 'message' => 'Page not found.'], 404);
        }

        return response()->json(new PageResource($page));
    }
}
