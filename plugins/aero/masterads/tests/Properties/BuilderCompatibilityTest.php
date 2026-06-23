<?php

declare(strict_types=1);

namespace Aero\MasterAds\Tests\Properties;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Property P9 — Compatibilidad RainLab Builder.
 *
 * Validates: Requirements 17.1, 17.2, 17.3, 17.4.
 *
 * Property statement (from design.md):
 *
 *   ∀ model in models/:
 *     exists(models/{model}/fields.yaml) ∧ exists(models/{model}/columns.yaml)
 *   ∀ controller in controllers/:
 *     exists(controllers/{controller}/config_form.yaml) ∧
 *     exists(controllers/{controller}/config_list.yaml)
 *   ∀ migration in updates/:
 *     name matches create_*_table.php ∧ entry is registered in version.yaml
 *
 * This is a *structural* property: it walks the plugin filesystem and asserts
 * the RainLab Builder layout is intact, independent of any database state.
 * No bootstrapping of the OctoberCMS test harness is required — the test
 * extends the plain PHPUnit TestCase.
 */
final class BuilderCompatibilityTest extends TestCase
{
    /**
     * Models that are intentionally excluded from the Builder structure.
     * `Settings.php` (system-wide singletons) is the standard exception, but
     * this plugin doesn't ship one — the list is kept to keep the property
     * explicit and future-proof.
     *
     * @var array<int, string>
     */
    private const MODEL_EXCEPTIONS = [
        'Settings',
    ];

    private function pluginRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * P9.a — Every concrete model has a matching Builder folder with the
     * `fields.yaml` and `columns.yaml` files that RainLab Builder generates.
     */
    public function testAllModelsHaveBuilderYamls(): void
    {
        $modelsDir = $this->pluginRoot() . '/models';
        $this->assertDirectoryExists($modelsDir, 'models/ directory is missing');

        $modelFiles = glob($modelsDir . '/*.php');
        $this->assertNotEmpty($modelFiles, 'No model files found under models/');

        foreach ($modelFiles as $file) {
            $className = basename($file, '.php');

            if (in_array($className, self::MODEL_EXCEPTIONS, true)) {
                continue;
            }

            $folder = $modelsDir . '/' . strtolower($className);
            $this->assertDirectoryExists(
                $folder,
                sprintf('Builder folder missing for model %s (expected %s)', $className, $folder)
            );

            $fieldsPath  = $folder . '/fields.yaml';
            $columnsPath = $folder . '/columns.yaml';

            $this->assertFileExists($fieldsPath, sprintf('fields.yaml missing for %s', $className));
            $this->assertFileExists($columnsPath, sprintf('columns.yaml missing for %s', $className));

            $fields = Yaml::parseFile($fieldsPath);
            $this->assertIsArray(
                $fields,
                sprintf('fields.yaml for %s did not parse as a YAML mapping', $className)
            );
            $this->assertTrue(
                isset($fields['fields']) || isset($fields['tabs']),
                sprintf("%s fields.yaml has neither 'fields' nor 'tabs' top-level key", $className)
            );

            $columns = Yaml::parseFile($columnsPath);
            $this->assertIsArray(
                $columns,
                sprintf('columns.yaml for %s did not parse as a YAML mapping', $className)
            );
            $this->assertArrayHasKey(
                'columns',
                $columns,
                sprintf("%s columns.yaml has no 'columns' top-level key", $className)
            );
        }
    }

    /**
     * P9.b — Every backend controller has the RainLab Builder companion
     * YAMLs (`config_form.yaml` and `config_list.yaml`) and both reference
     * the model class explicitly.
     */
    public function testAllControllersHaveBuilderYamls(): void
    {
        $controllersDir = $this->pluginRoot() . '/controllers';
        $this->assertDirectoryExists($controllersDir, 'controllers/ directory is missing');

        $controllerFiles = glob($controllersDir . '/*.php');
        $this->assertNotEmpty($controllerFiles, 'No controller files found under controllers/');

        foreach ($controllerFiles as $file) {
            $className = basename($file, '.php');

            $folder = $controllersDir . '/' . strtolower($className);
            $this->assertDirectoryExists(
                $folder,
                sprintf('Builder folder missing for controller %s (expected %s)', $className, $folder)
            );

            $formPath = $folder . '/config_form.yaml';
            $listPath = $folder . '/config_list.yaml';

            $this->assertFileExists($formPath, sprintf('config_form.yaml missing for %s', $className));
            $this->assertFileExists($listPath, sprintf('config_list.yaml missing for %s', $className));

            $form = Yaml::parseFile($formPath);
            $this->assertIsArray(
                $form,
                sprintf('config_form.yaml for %s did not parse as a YAML mapping', $className)
            );
            $this->assertArrayHasKey(
                'form',
                $form,
                sprintf("%s config_form.yaml has no 'form' top-level key", $className)
            );
            $this->assertArrayHasKey(
                'modelClass',
                $form,
                sprintf("%s config_form.yaml has no 'modelClass' top-level key", $className)
            );

            $list = Yaml::parseFile($listPath);
            $this->assertIsArray(
                $list,
                sprintf('config_list.yaml for %s did not parse as a YAML mapping', $className)
            );
            $this->assertArrayHasKey(
                'list',
                $list,
                sprintf("%s config_list.yaml has no 'list' top-level key", $className)
            );
            $this->assertArrayHasKey(
                'modelClass',
                $list,
                sprintf("%s config_list.yaml has no 'modelClass' top-level key", $className)
            );
        }
    }

    /**
     * P9.c — Every migration in `updates/` is named `create_<table>_table.php`
     * (snake_case) and is registered as an entry under some version in
     * `version.yaml`.
     */
    public function testAllMigrationsRegisteredInVersionYaml(): void
    {
        $updatesDir = $this->pluginRoot() . '/updates';
        $this->assertDirectoryExists($updatesDir, 'updates/ directory is missing');

        $versionFile = $updatesDir . '/version.yaml';
        $this->assertFileExists($versionFile, 'updates/version.yaml is missing');

        $versionYaml = Yaml::parseFile($versionFile);
        $this->assertIsArray($versionYaml, 'version.yaml did not parse as a YAML mapping');

        // Collect every .php filename referenced inside any version's entries.
        $registered = [];
        foreach ($versionYaml as $version => $entries) {
            $this->assertIsArray(
                $entries,
                sprintf('version.yaml entry %s is not a list', (string) $version)
            );
            foreach ($entries as $entry) {
                if (is_string($entry) && str_ends_with($entry, '.php')) {
                    $registered[] = $entry;
                }
            }
        }

        // Naming pattern: snake_case, must start with `create_` and end with `_table.php`.
        $namingPattern = '/^create_[a-z0-9]+(?:_[a-z0-9]+)*_table\.php$/';

        $migrationFiles = glob($updatesDir . '/create_*_table.php');
        $this->assertNotEmpty(
            $migrationFiles,
            'No create_*_table.php migrations found under updates/'
        );

        foreach ($migrationFiles as $migrationFile) {
            $base = basename($migrationFile);

            $this->assertMatchesRegularExpression(
                $namingPattern,
                $base,
                sprintf(
                    'Migration %s does not follow the snake_case create_<table>_table.php pattern',
                    $base
                )
            );

            $this->assertContains(
                $base,
                $registered,
                sprintf('Migration %s is not registered in version.yaml', $base)
            );
        }
    }
}
