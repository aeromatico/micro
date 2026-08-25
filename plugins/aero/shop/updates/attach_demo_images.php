<?php

/**
 * Artisan-compatible script to download Unsplash images and attach them
 * to products and collections via the system_files table.
 *
 * Run with: php artisan tinker --execute="require base_path('plugins/aero/shop/updates/attach_demo_images.php');"
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

$tenantId = 1;
$uploadDir = 'uploads/demo';

// Get all collections and products
$collections = DB::table('aero_shop_collections')->where('tenant_id', $tenantId)->get();
$products = DB::table('aero_shop_products')->where('tenant_id', $tenantId)->get();

$downloadImage = function (string $url, string $subdir, string $filename) use ($uploadDir) {
    $path = $uploadDir . '/' . $subdir . '/' . $filename;
    $localPath = storage_path('app/' . $path);
    $dir = dirname($localPath);

    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    // Skip if already downloaded
    if (file_exists($localPath) && filesize($localPath) > 0) {
        return $path;
    }

    try {
        $response = Http::timeout(15)->get($url);
        if ($response->successful()) {
            file_put_contents($localPath, $response->body());
            return $path;
        }
    } catch (\Exception $e) {
        echo "  ⚠ Error downloading {$filename}: " . $e->getMessage() . PHP_EOL;
    }

    return null;
};

// Attach collection images
echo "📎 Attaching collection images..." . PHP_EOL;
foreach ($collections as $col) {
    $cacheEntry = DB::table('aero_sites_image_cache')
        ->where('keywords', 'collection:' . $col->slug)
        ->first();

    if (!$cacheEntry) {
        echo "  ⚠ No cache entry for collection: {$col->slug}" . PHP_EOL;
        continue;
    }

    // Check if already attached
    $existing = DB::table('system_files')
        ->where('attachment_type', 'Aero\Shop\Models\Collection')
        ->where('attachment_id', $col->id)
        ->where('field', 'image')
        ->first();

    if ($existing) {
        echo "  ✓ {$col->name} already attached" . PHP_EOL;
        continue;
    }

    $filename = $col->slug . '.jpg';
    $path = $downloadImage($cacheEntry->url, 'collections', $filename);

    if ($path) {
        DB::table('system_files')->insert([
            'disk_name'       => 'local',
            'file_name'       => $filename,
            'file_size'       => 0,
            'content_type'    => 'image/jpeg',
            'title'           => null,
            'description'     => null,
            'field'           => 'image',
            'attachment_id'   => $col->id,
            'attachment_type' => 'Aero\Shop\Models\Collection',
            'is_public'       => true,
            'sort_order'      => 0,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
        echo "  ✓ {$col->name}" . PHP_EOL;
    }
}

// Attach product images
echo PHP_EOL . "📎 Attaching product images..." . PHP_EOL;
foreach ($products as $prod) {
    $cacheEntry = DB::table('aero_sites_image_cache')
        ->where('keywords', 'product:' . $prod->slug)
        ->first();

    if (!$cacheEntry) {
        echo "  ⚠ No cache entry for product: {$prod->slug}" . PHP_EOL;
        continue;
    }

    // Check if already attached
    $existing = DB::table('system_files')
        ->where('attachment_type', 'Aero\Shop\Models\Product')
        ->where('attachment_id', $prod->id)
        ->where('field', 'images')
        ->first();

    if ($existing) {
        echo "  ✓ {$prod->name} already attached" . PHP_EOL;
        continue;
    }

    $filename = $prod->slug . '.jpg';
    $path = $downloadImage($cacheEntry->url, 'products', $filename);

    if ($path) {
        DB::table('system_files')->insert([
            'disk_name'       => 'local',
            'file_name'       => $filename,
            'file_size'       => 0,
            'content_type'    => 'image/jpeg',
            'title'           => null,
            'description'     => null,
            'field'           => 'images',
            'attachment_id'   => $prod->id,
            'attachment_type' => 'Aero\Shop\Models\Product',
            'is_public'       => true,
            'sort_order'      => 0,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
        echo "  ✓ {$prod->name}" . PHP_EOL;
    }
}

echo PHP_EOL . "✅ Done!" . PHP_EOL;
