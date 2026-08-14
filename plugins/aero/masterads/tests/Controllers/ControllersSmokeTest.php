<?php declare(strict_types=1);

namespace Aero\MasterAds\Tests\Controllers;

use PHPUnit\Framework\TestCase;

/**
 * ControllersSmokeTest — Structural verification of every backend
 * controller in the plugin.
 *
 * Validates: Requirements 12.6, 14.2 of the master-ads spec.
 *
 * For each controller we assert (without booting OctoberCMS):
 *   1. The class file exists.
 *   2. The file declares namespace Aero\MasterAds\Controllers.
 *   3. The class extends Backend\Classes\Controller (matched as
 *      `extends Controller` after the `use Backend\Classes\Controller`).
 *   4. The expected behaviors appear in $implement.
 *   5. The required permission slug appears in $requiredPermissions.
 *   6. BackendMenu::setContext('Aero.MasterAds', ...) is called.
 *   7. config_form.yaml and config_list.yaml exist in the lowercase
 *      companion subfolder.
 */
class ControllersSmokeTest extends TestCase
{
    private const BASE_BEHAVIORS = [
        '\Backend\Behaviors\FormController',
        '\Backend\Behaviors\ListController',
    ];

    public static function controllerProvider(): iterable
    {
        $base = self::BASE_BEHAVIORS;

        yield 'Workspaces' => [
            'Workspaces',
            array_merge($base, ['\Backend\Behaviors\RelationController']),
            'aero.masterads.manage_workspaces',
        ];
        yield 'MetaAccounts' => ['MetaAccounts', $base, 'aero.masterads.manage_meta_accounts'];
        yield 'Campaigns' => ['Campaigns', $base, 'aero.masterads.access_campaigns'];
        yield 'AdSets' => ['AdSets', $base, 'aero.masterads.access_campaigns'];
        yield 'Ads' => ['Ads', $base, 'aero.masterads.access_campaigns'];
        yield 'Recommendations' => ['Recommendations', $base, 'aero.masterads.review_recommendations'];
        yield 'AiAnalyses' => ['AiAnalyses', $base, 'aero.masterads.run_analysis'];
        yield 'AiProviders' => ['AiProviders', $base, 'aero.masterads.manage_ai_providers'];
        yield 'Plans' => ['Plans', $base, 'aero.masterads.manage_billing'];
        yield 'Subscriptions' => ['Subscriptions', $base, 'aero.masterads.manage_billing'];
    }

    /** @dataProvider controllerProvider */
    public function testControllerStructure(string $shortName, array $expectedBehaviors, string $expectedPermission): void
    {
        $file = $this->pluginRoot() . '/controllers/' . $shortName . '.php';
        $this->assertFileExists($file, "Controller file missing: $file");

        $content = file_get_contents($file);
        $this->assertNotFalse($content);

        // 1. Namespace
        $this->assertMatchesRegularExpression(
            '/namespace\s+Aero\\\\MasterAds\\\\Controllers\s*;/',
            $content,
            "Wrong namespace in $shortName"
        );

        // 2. extends Controller
        $this->assertMatchesRegularExpression(
            "/class\s+{$shortName}\s+extends\s+Controller/",
            $content,
            "Class $shortName must extend Backend\\Classes\\Controller"
        );

        // 3. Uses Backend\Classes\Controller
        $this->assertStringContainsString(
            'use Backend\Classes\Controller',
            $content,
            "$shortName must import Backend\\Classes\\Controller"
        );

        // 4. Each expected behavior appears in $implement
        foreach ($expectedBehaviors as $behavior) {
            $escaped = preg_quote(ltrim($behavior, '\\'), '/');
            $this->assertMatchesRegularExpression(
                "/{$escaped}::class/",
                $content,
                "$shortName must implement behavior $behavior"
            );
        }

        // 5. Expected permission
        $this->assertStringContainsString(
            $expectedPermission,
            $content,
            "$shortName must declare requiredPermissions = ['$expectedPermission']"
        );

        // 6. BackendMenu::setContext
        $this->assertMatchesRegularExpression(
            "/BackendMenu::setContext\\(\\s*'Aero\\.MasterAds'/",
            $content,
            "$shortName must call BackendMenu::setContext('Aero.MasterAds', ...)"
        );

        // 7. Companion subfolder with config_form.yaml and config_list.yaml
        $folder = $this->pluginRoot() . '/controllers/' . strtolower($shortName);
        $this->assertDirectoryExists($folder, "Controller subfolder missing: $folder");
        $this->assertFileExists($folder . '/config_form.yaml', "config_form.yaml missing for $shortName");
        $this->assertFileExists($folder . '/config_list.yaml', "config_list.yaml missing for $shortName");
    }

    public function testAllControllersInPluginAreCovered(): void
    {
        $controllersDir = $this->pluginRoot() . '/controllers';
        $files = glob($controllersDir . '/*.php');
        $diskNames = array_map(fn($f) => basename($f, '.php'), $files);

        $providerNames = [];
        foreach (self::controllerProvider() as $row) {
            $providerNames[] = $row[0];
        }

        sort($diskNames);
        sort($providerNames);
        $this->assertSame($providerNames, $diskNames,
            'Every controller .php file on disk must be covered by controllerProvider() — and vice versa.');
    }

    private function pluginRoot(): string
    {
        return dirname(__DIR__, 2);
    }
}
