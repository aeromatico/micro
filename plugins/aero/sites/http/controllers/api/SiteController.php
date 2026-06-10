<?php namespace Aero\Sites\Http\Controllers\Api;

use Aero\Sites\Http\Resources\SiteResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SiteController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $tenant->load(['seoConfig', 'contactConfig', 'logo', 'favicon']);

        return response()->json(new SiteResource($tenant));
    }

    public function seo(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $seo = $tenant->seoConfig;

        if (!$seo) {
            return response()->json(['error' => 'not_found', 'message' => 'SEO config not found.'], 404);
        }

        return response()->json([
            'title_format'        => $seo->title_format,
            'default_description' => $seo->default_description,
            'og_image_url'        => $seo->og_image?->getPath(),
            'google_analytics_id' => $seo->google_analytics_id,
            'sitemap_enabled'     => (bool) $seo->sitemap_enabled,
        ]);
    }
}
