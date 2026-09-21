<?php

use Aero\Docs\Models\Article;
use Aero\Docs\Models\Category;
use October\Rain\Database\Updates\Seeder;

return new class extends Seeder
{
    /**
     * Documentación de las funciones del panel del tenant de Aero.Crm.
     * El contenido vive como Markdown en plugins/aero/docs/content/crm y se
     * importa una sola vez como artículos del plugin Docs.
     */
    public function run(): void
    {
        $root = Category::firstOrNew(['tenant_id' => null, 'slug' => 'crm']);
        $root->fill([
            'name'        => 'CRM',
            'icon'        => '🗂️',
            'description' => 'Guía del CRM: clientes, pipeline, cobranzas, tickets y equipo.',
            'is_active'   => true,
            'is_global'   => true,
        ]);
        $root->save();

        $forms = Category::firstOrNew(['tenant_id' => null, 'slug' => 'crm-funciones']);
        $forms->fill([
            'parent_id'   => $root->id,
            'name'        => 'Funciones del panel',
            'icon'        => '📋',
            'description' => 'Qué hace cada pantalla del CRM y cómo se usa.',
            'is_active'   => true,
            'is_global'   => true,
        ]);
        $forms->save();

        $contentDir = plugins_path('aero/docs/content/crm');

        $articles = [
            ['file' => 'crm-vista-general',              'title' => 'CRM — Vista general',                 'sort' => 0,  'featured' => true],
            ['file' => 'crm-configuracion',              'title' => 'Configuración de CRM',                'sort' => 10],
            ['file' => 'crm-empresas',                   'title' => 'Empresas',                            'sort' => 20],
            ['file' => 'crm-contactos',                  'title' => 'Contactos',                           'sort' => 30],
            ['file' => 'crm-listas',                     'title' => 'Listas',                              'sort' => 40],
            ['file' => 'crm-cobranzas',                  'title' => 'Cobranzas',                           'sort' => 50],
            ['file' => 'crm-automatizacion-cobranzas',   'title' => 'Automatización de cobranzas',         'sort' => 60],
            ['file' => 'crm-leads',                      'title' => 'Leads',                               'sort' => 70],
            ['file' => 'crm-pipeline',                   'title' => 'Pipeline',                            'sort' => 80],
            ['file' => 'crm-actividades',                'title' => 'Actividades',                         'sort' => 90],
            ['file' => 'crm-respuestas',                 'title' => 'Respuestas rápidas',                  'sort' => 100],
            ['file' => 'crm-equipo',                     'title' => 'Equipo',                              'sort' => 110],
            ['file' => 'crm-tickets',                    'title' => 'Tickets',                             'sort' => 120],
            ['file' => 'crm-departamentos',              'title' => 'Departamentos',                       'sort' => 130],
        ];

        foreach ($articles as $item) {
            $path = $contentDir . '/' . $item['file'] . '.md';
            if (!is_file($path)) {
                continue;
            }

            $article = Article::firstOrNew(['tenant_id' => null, 'slug' => $item['file']]);
            $article->fill([
                'category_id'  => $forms->id,
                'title'        => $item['title'],
                'content'      => file_get_contents($path),
                'plugin_version' => $this->pluginVersion('aero/crm'),
                'sort_order'   => $item['sort'],
                'is_published' => true,
                'is_global'    => true,
                'is_featured'  => (bool) ($item['featured'] ?? false),
            ]);
            $article->save();
        }
    }

    /** Última versión declarada en updates/version.yaml del plugin. */
    protected function pluginVersion(string $handle): ?string
    {
        $path = plugins_path($handle . '/updates/version.yaml');
        if (!is_file($path)) {
            return null;
        }

        preg_match_all('/^\s*([0-9]+\.[0-9]+\.[0-9]+):/m', file_get_contents($path), $m);
        if (empty($m[1])) {
            return null;
        }

        usort($m[1], 'version_compare');

        return end($m[1]) ?: null;
    }
};
