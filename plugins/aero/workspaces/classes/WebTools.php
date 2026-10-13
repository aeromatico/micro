<?php namespace Aero\Workspaces\Classes;

use Aero\Workspaces\Models\Revision;
use Illuminate\Support\Str;

/**
 * Herramientas con las que un agente (Iván, desarrollo web) GESTIONA y GENERA el
 * sitio (Aero.Sites) y la tienda (Aero.Shop) de UN cliente.
 *
 * NO se registran en `AiToolRegistry`: ahí las vería cualquier chatbot público
 * del tenant. Solo las ve `AgentRunner` (ver `registry()`) y únicamente las usa
 * un agente cuyos skills las declaren.
 *
 * Reglas duras (no dependen de lo que diga el modelo):
 *  - El tenant lo pone el motor (segundo argumento); jamás un argumento. Todo
 *    se busca con `tenant_id` y falla cerrado.
 *  - Guardar exige `agreed_plan`: el agente solo construye tras acordar con la persona.
 *  - Lo nuevo nace como BORRADOR (página sin publicar, producto `draft`).
 *  - Publicar, despublicar, archivar o cambiar algo que ya está en vivo exige
 *    `confirm: true`; sin él solo devuelve lo que pasaría.
 *  - Cada cambio guarda una copia «antes» (Revision) y se deshace con `web_undo`.
 *  - Nada se borra: lo más destructivo es archivar / despublicar.
 *  - Texto con scripts, manejadores `on…=` o URLs `javascript:`/`data:` se rechaza.
 */
class WebTools
{
    public const CATEGORY = 'web_builder';
    public const MAX_BLOCKS = 40;
    public const MAX_PAGE_BYTES = 200000;
    public const MAX_BATCH = 25;
    public const LAYOUTS = ['default', 'home', 'contact'];

    protected const PAGE_FIELDS = ['title', 'slug', 'puck_data', 'meta_title', 'meta_description', 'layout', 'show_in_menu', 'menu_positions', 'is_published'];
    protected const PRODUCT_FIELDS = ['name', 'slug', 'description', 'base_price', 'compare_at_price', 'sku', 'collection_id', 'status', 'is_featured', 'seo_title', 'seo_description', 'stock_quantity', 'published_at'];
    protected const COLLECTION_FIELDS = ['name', 'slug', 'description', 'parent_id', 'is_active'];
    protected const SEO_FIELDS = ['title_format', 'default_description'];
    protected const CONTACT_FIELDS = ['contact_email', 'phone', 'whatsapp', 'city', 'region', 'address'];

    /** Se usa en las pruebas para no depender del renderizador real. */
    public static ?\Closure $renderer = null;

    public static function tools(): array
    {
        $tool = fn (string $description, array $properties, array $required, string $method) => [
            'description' => $description,
            'category'    => static::CATEGORY,
            'parameters'  => ['type' => 'object', 'properties' => $properties ?: new \stdClass(), 'required' => $required],
            'handler'     => [static::class, $method],
        ];

        $plan = ['agreed_plan' => ['type' => 'string', 'description' => 'Lo que la persona aprobó, en pasos simples (mín. 20 caracteres). Obligatorio para guardar.']];
        $confirm = ['confirm' => ['type' => 'boolean', 'description' => 'false o ausente: solo muestra lo que pasaría. true: lo hace. Envía true únicamente tras el «sí» explícito de la persona.']];

        $productProps = [
            'name'             => ['type' => 'string'],
            'description'      => ['type' => 'string', 'description' => 'Texto del producto. HTML básico permitido (p, br, ul, li, strong, em).'],
            'base_price'       => ['type' => 'number', 'description' => 'Precio en la moneda base de la tienda (ver web_context).'],
            'compare_at_price' => ['type' => 'number', 'description' => 'Precio anterior (tachado), opcional.'],
            'sku'              => ['type' => 'string'],
            'collection_id'    => ['type' => 'integer', 'description' => 'Id de una colección del cliente (web_collections).'],
            'is_featured'      => ['type' => 'boolean'],
            'seo_title'        => ['type' => 'string', 'description' => 'Hasta 70 caracteres.'],
            'seo_description'  => ['type' => 'string', 'description' => 'Hasta 160 caracteres.'],
        ];

        return [
            'web_context' => $tool(
                'PRIMERO, siempre. Qué tiene ya el cliente: negocio y rubro, contacto, SEO, páginas (y cuántas publicadas), si la tienda está activa, moneda, colecciones y productos por estado, más qué datos faltan. Úsalo para NO preguntar lo que ya se sabe.',
                [], [], 'context'
            ),
            'web_blocks_catalog' => $tool(
                'Catálogo VIVO de bloques de página. Sin argumentos: lista corta (tipo y para qué sirve). Con `types`: campos y opciones válidas de esos bloques. Nunca uses un bloque, campo u opción que no aparezca aquí.',
                ['types' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Ej. ["Hero","FAQ"].']],
                [], 'blocksCatalog'
            ),
            'web_pages' => $tool(
                'Páginas del sitio del cliente (id, título, slug, publicada, en menú, bloques). Con `id` devuelve una; con `block` (índice) un solo bloque completo.',
                ['id' => ['type' => 'integer'], 'block' => ['type' => 'integer', 'description' => 'Índice (desde 0) del bloque completo que quieres leer.']],
                [], 'pages'
            ),
            'web_page_save' => $tool(
                'Crea o edita una página. Nueva ⇒ queda como BORRADOR sin publicar. Con `id` EDITA esa página (reemplaza sus bloques por los que envíes: manda la lista COMPLETA); si está publicada exige confirm:true. Valida los bloques con el catálogo y los renderiza antes de guardar; si hay errores no guarda nada.',
                [
                    'id'               => ['type' => 'integer', 'description' => 'Omitir para crear.'],
                    'title'            => ['type' => 'string'],
                    'slug'             => ['type' => 'string', 'description' => 'Ej. "nosotros". Se normaliza y se hace único.'],
                    'blocks'           => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['type' => ['type' => 'string'], 'props' => ['type' => 'object']], 'required' => ['type', 'props']], 'description' => 'Bloques en orden: [{"type":"Hero","props":{...}}].'],
                    'meta_title'       => ['type' => 'string', 'description' => 'Hasta 70 caracteres.'],
                    'meta_description' => ['type' => 'string', 'description' => 'Hasta 160 caracteres.'],
                    'layout'           => ['type' => 'string', 'enum' => static::LAYOUTS],
                    'show_in_menu'     => ['type' => 'boolean'],
                    'menu_positions'   => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['top', 'navbar', 'sidebar', 'footer']]],
                ] + $plan + $confirm,
                ['agreed_plan'], 'pageSave'
            ),
            'web_page_publish' => $tool(
                'Publica o despublica una página (la hace visible o la oculta en el sitio real). Sin confirm:true solo avisa.',
                ['id' => ['type' => 'integer'], 'published' => ['type' => 'boolean']] + $confirm,
                ['id', 'published'], 'pagePublish'
            ),
            'web_seo_set' => $tool(
                'Ajusta el SEO general del sitio: formato del título («%s | {name}») y descripción por defecto. Sin confirm:true solo muestra el cambio.',
                ['title_format' => ['type' => 'string'], 'default_description' => ['type' => 'string']] + $plan + $confirm,
                ['agreed_plan'], 'seoSet'
            ),
            'web_contact_set' => $tool(
                'Ajusta los datos públicos de contacto (correo, teléfono, WhatsApp, ciudad, departamento, dirección). Se muestran en el sitio: exige confirm:true; sin él solo muestra el cambio.',
                ['contact_email' => ['type' => 'string'], 'phone' => ['type' => 'string'], 'whatsapp' => ['type' => 'string'], 'city' => ['type' => 'string'], 'region' => ['type' => 'string', 'description' => 'Departamento de Bolivia: chuquisaca, la_paz, cochabamba, oruro, potosi, tarija, santa_cruz, beni, pando.'], 'address' => ['type' => 'string']] + $plan + $confirm,
                ['agreed_plan'], 'contactSet'
            ),
            'web_collections' => $tool(
                'Colecciones (categorías) de la tienda del cliente, con cuántos productos tiene cada una.',
                [], [], 'collections'
            ),
            'web_collection_save' => $tool(
                'Crea o edita una colección. `id` para editar.',
                ['id' => ['type' => 'integer'], 'name' => ['type' => 'string'], 'slug' => ['type' => 'string'], 'description' => ['type' => 'string'], 'parent_id' => ['type' => 'integer'], 'is_active' => ['type' => 'boolean']] + $plan,
                ['agreed_plan'], 'collectionSave'
            ),
            'web_products' => $tool(
                'Productos de la tienda en cualquier estado (borrador, activo, archivado). Filtros: q, status, collection_id. Con `id` devuelve uno completo.',
                ['id' => ['type' => 'integer'], 'q' => ['type' => 'string'], 'status' => ['type' => 'string', 'enum' => ['draft', 'active', 'archived']], 'collection_id' => ['type' => 'integer'], 'limit' => ['type' => 'integer', 'description' => '1 a 50, por defecto 20.']],
                [], 'products'
            ),
            'web_product_save' => $tool(
                'Crea o edita UN producto. Nuevo ⇒ queda en BORRADOR. Con `id` edita solo los campos que envíes; si el producto está activo exige confirm:true. No toca variantes ni imágenes (las sube la persona) ni ajusta stock de un producto existente.',
                ['id' => ['type' => 'integer', 'description' => 'Omitir para crear.'], 'stock_quantity' => ['type' => 'integer', 'description' => 'Solo al crear.']] + $productProps + $plan + $confirm,
                ['agreed_plan'], 'productSave'
            ),
            'web_products_create' => $tool(
                'Crea VARIOS productos nuevos de una vez (hasta ' . static::MAX_BATCH . '), todos en BORRADOR. Ideal para cargar un catálogo acordado.',
                ['items' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => $productProps + ['stock_quantity' => ['type' => 'integer']], 'required' => ['name', 'base_price']]]] + $plan,
                ['items', 'agreed_plan'], 'productsCreate'
            ),
            'web_product_status' => $tool(
                'Cambia el estado de un producto: draft, active (visible en la tienda) o archived. Sin confirm:true solo avisa.',
                ['id' => ['type' => 'integer'], 'status' => ['type' => 'string', 'enum' => ['draft', 'active', 'archived']]] + $confirm,
                ['id', 'status'], 'productStatus'
            ),
            'web_undo' => $tool(
                'Deshace un cambio hecho por los agentes: lo deja como estaba (lo creado vuelve a no existir si sigue en borrador). Sin `revision_id` toma el último cambio sin deshacer. Sin confirm:true solo muestra qué haría. `web_changes` lista los cambios.',
                ['revision_id' => ['type' => 'integer']] + $confirm,
                [], 'undo'
            ),
            'web_changes' => $tool(
                'Cambios recientes hechos por los agentes en el sitio y la tienda (id, qué, cuándo, si ya se deshizo).',
                ['limit' => ['type' => 'integer']], [], 'changes'
            ),
        ];
    }

    // ---------------------------------------------------------------- lectura

    public static function context(array $args, int $tenantId): array
    {
        if ($error = static::guard($tenantId)) {
            return $error;
        }

        $tenant = \Aero\Sites\Models\Tenant::find($tenantId);
        $contact = $tenant->contactConfig;
        $seo = \Aero\Sites\Models\SeoConfig::where('tenant_id', $tenantId)->first();
        $pages = \Aero\Sites\Models\Page::forTenant($tenantId);

        $out = [
            'business' => ['name' => $tenant->name, 'niche' => $tenant->niche_type ?? null, 'url' => 'https://' . $tenant->primary_domain],
            'contact'  => ['email' => $contact?->contact_email, 'phone' => $contact?->phone, 'whatsapp' => $contact?->whatsapp, 'city' => $contact?->city, 'region' => $contact?->region, 'address' => $contact?->address],
            'seo'      => ['title_format' => $seo?->title_format, 'default_description' => $seo?->default_description],
            'pages'    => ['total' => (clone $pages)->count(), 'published' => (clone $pages)->published()->count(), 'items' => (clone $pages)->orderBy('sort_order')->limit(30)->get(['id', 'title', 'slug', 'is_published'])->map(fn ($p) => ['id' => $p->id, 'title' => $p->title, 'slug' => $p->slug, 'published' => (bool) $p->is_published])->all()],
            'shop'     => ['available' => false],
            'limits'   => ['max_blocks_per_page' => static::MAX_BLOCKS, 'max_products_per_batch' => static::MAX_BATCH],
        ];

        if (class_exists(\Aero\Shop\Models\Product::class)) {
            $settings = \Aero\Shop\Models\ShopSettings::where('tenant_id', $tenantId)->first();
            $currency = null;

            try {
                $currency = $settings?->base_currency_id ? \Aero\Shop\Models\Currency::find($settings->base_currency_id) : null;
            }
            catch (\Throwable) {
                // la moneda es solo contexto
            }

            $counts = \Aero\Shop\Models\Product::forTenant($tenantId)->where('is_internal', false)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->all();

            $out['shop'] = [
                'available'   => true,
                'enabled'     => (bool) ($settings?->is_enabled),
                'store_mode'  => $settings?->store_mode,
                'currency'    => $currency ? ['code' => $currency->code, 'symbol' => $currency->symbol] : null,
                'products'    => ['draft' => (int) ($counts['draft'] ?? 0), 'active' => (int) ($counts['active'] ?? 0), 'archived' => (int) ($counts['archived'] ?? 0)],
                'collections' => \Aero\Shop\Models\Collection::forTenant($tenantId)->orderBy('name')->limit(40)->get(['id', 'name', 'slug', 'is_active'])->all(),
            ];
        }

        $out['missing'] = array_values(array_filter([
            $contact?->whatsapp || $contact?->phone ? null : 'contacto (teléfono o WhatsApp)',
            $seo?->default_description ? null : 'descripción SEO del sitio',
            $out['pages']['published'] > 0 ? null : 'ninguna página publicada',
            $out['shop']['available'] && ($out['shop']['currency'] ?? null) === null ? 'moneda base de la tienda' : null,
        ]));

        return $out;
    }

    public static function blocksCatalog(array $args, int $tenantId): array
    {
        $catalog = static::catalog();

        if ($catalog === null) {
            return ['error' => 'El catálogo de bloques no está disponible (falta compilar el editor).'];
        }

        $types = array_values(array_filter((array) ($args['types'] ?? []), 'is_string'));

        if (!$types) {
            return ['blocks' => array_map(fn ($def, $name) => ['type' => $name, 'for' => (string) ($def['desc'] ?? '')], $catalog, array_keys($catalog)), 'note' => 'Pide el detalle de los que vayas a usar con `types`.'];
        }

        $out = [];

        foreach ($types as $type) {
            if (!isset($catalog[$type])) {
                return ['error' => "No existe el bloque «{$type}».", 'valid' => array_keys($catalog)];
            }

            $fields = [];

            foreach ((array) ($catalog[$type]['fields'] ?? []) as $key => $def) {
                $field = ['type' => $def['type'] ?? 'text', 'label' => $def['label'] ?? $key];

                if (!empty($def['options'])) {
                    $field['options'] = array_map(fn ($o) => is_array($o) ? ($o['value'] ?? null) : $o, (array) $def['options']);
                }

                if (!empty($def['arrayFields'])) {
                    $field['item'] = array_keys((array) $def['arrayFields']);
                }

                $fields[$key] = $field;
            }

            $out[$type] = ['for' => (string) ($catalog[$type]['desc'] ?? ''), 'fields' => $fields];
        }

        return ['blocks' => $out];
    }

    public static function pages(array $args, int $tenantId): array
    {
        if ($error = static::guard($tenantId)) {
            return $error;
        }

        if (!empty($args['id'])) {
            $page = \Aero\Sites\Models\Page::forTenant($tenantId)->find((int) $args['id']);

            if (!$page) {
                return ['error' => 'No existe esa página en este cliente.'];
            }

            $blocks = array_values((array) ($page->puck_data['content'] ?? []));

            if (isset($args['block'])) {
                $i = (int) $args['block'];

                return isset($blocks[$i]) ? ['id' => $page->id, 'index' => $i, 'block' => $blocks[$i]] : ['error' => 'Ese bloque no existe.', 'blocks' => count($blocks)];
            }

            $detail = static::pageBrief($page) + ['meta_title' => $page->meta_title, 'meta_description' => $page->meta_description, 'layout' => $page->layout];
            $full = $blocks;

            if (strlen(json_encode($full, JSON_UNESCAPED_UNICODE)) > 9000) {
                return $detail + ['blocks' => array_map(fn ($b, $i) => ['index' => $i, 'type' => $b['type'] ?? '?', 'fields' => array_keys((array) ($b['props'] ?? []))], $blocks, array_keys($blocks)), 'note' => 'Página grande: pide un bloque con `block` para ver su contenido.'];
            }

            return $detail + ['blocks' => $full];
        }

        return ['pages' => \Aero\Sites\Models\Page::forTenant($tenantId)->orderBy('sort_order')->limit(100)->get()->map(fn ($p) => static::pageBrief($p))->all()];
    }

    public static function collections(array $args, int $tenantId): array
    {
        if ($error = static::guard($tenantId, true)) {
            return $error;
        }

        return ['collections' => \Aero\Shop\Models\Collection::forTenant($tenantId)->orderBy('name')->get()->map(fn ($c) => [
            'id' => $c->id, 'name' => $c->name, 'slug' => $c->slug, 'description' => $c->description, 'parent_id' => $c->parent_id,
            'is_active' => (bool) $c->is_active, 'products' => \Aero\Shop\Models\Product::forTenant($tenantId)->where('collection_id', $c->id)->count(),
        ])->all()];
    }

    public static function products(array $args, int $tenantId): array
    {
        if ($error = static::guard($tenantId, true)) {
            return $error;
        }

        $query = \Aero\Shop\Models\Product::forTenant($tenantId)->where('is_internal', false);

        if (!empty($args['id'])) {
            $product = (clone $query)->find((int) $args['id']);

            return $product ? ['product' => static::productPayload($product, true)] : ['error' => 'No existe ese producto en este cliente.'];
        }

        if (!empty($args['status'])) {
            $query->where('status', (string) $args['status']);
        }

        if (!empty($args['collection_id'])) {
            $query->where('collection_id', (int) $args['collection_id']);
        }

        if ($q = trim((string) ($args['q'] ?? ''))) {
            $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('sku', 'like', "%{$q}%"));
        }

        $limit = max(1, min(50, (int) ($args['limit'] ?? 20)));

        return ['total' => (clone $query)->count(), 'products' => $query->orderBy('name')->limit($limit)->get()->map(fn ($p) => static::productPayload($p))->all()];
    }

    public static function changes(array $args, int $tenantId): array
    {
        $limit = max(1, min(50, (int) ($args['limit'] ?? 15)));

        return ['changes' => Revision::where('tenant_id', $tenantId)->orderByDesc('id')->limit($limit)->get()->map(fn ($r) => [
            'revision_id' => $r->id, 'what' => $r->subject_type, 'id' => $r->subject_id, 'action' => $r->action,
            'summary' => $r->summary, 'at' => (string) $r->created_at, 'undone' => $r->undone_at !== null,
        ])->all()];
    }

    // -------------------------------------------------------------- escritura

    public static function pageSave(array $args, int $tenantId): array
    {
        if ($error = static::guard($tenantId) ?? static::needPlan($args)) {
            return $error;
        }

        $page = null;

        if (!empty($args['id'])) {
            $page = \Aero\Sites\Models\Page::forTenant($tenantId)->find((int) $args['id']);

            if (!$page) {
                return ['saved' => false, 'errors' => ['No existe esa página en este cliente.']];
            }
        }

        $data = [];
        $errors = [];

        if (array_key_exists('title', $args)) {
            $title = trim(strip_tags((string) $args['title']));
            $title === '' || mb_strlen($title) > 200 ? $errors[] = 'El título es obligatorio (hasta 200 caracteres).' : $data['title'] = $title;
        }
        elseif (!$page) {
            $errors[] = 'Falta el título.';
        }

        foreach (['meta_title' => 70, 'meta_description' => 160] as $key => $max) {
            if (array_key_exists($key, $args)) {
                $data[$key] = mb_substr(trim(strip_tags((string) $args[$key])), 0, $max);
            }
        }

        if (isset($args['layout'])) {
            in_array($args['layout'], static::LAYOUTS, true) ? $data['layout'] = $args['layout'] : $errors[] = 'Layout no válido: ' . implode(', ', static::LAYOUTS) . '.';
        }

        if (isset($args['show_in_menu'])) {
            $data['show_in_menu'] = (bool) $args['show_in_menu'];
        }

        if (isset($args['menu_positions'])) {
            $valid = array_keys(\Aero\Sites\Models\Page::MENU_POSITIONS);
            $positions = array_values(array_unique(array_filter((array) $args['menu_positions'], fn ($p) => in_array($p, $valid, true))));
            $data['menu_positions'] = $positions;
        }

        if (array_key_exists('blocks', $args)) {
            [$puck, $blockErrors] = static::buildPuck((array) $args['blocks']);
            $blockErrors ? $errors = array_merge($errors, $blockErrors) : $data['puck_data'] = $puck;
        }
        elseif (!$page) {
            $errors[] = 'Falta `blocks`: la lista de bloques de la página.';
        }

        if (array_key_exists('slug', $args) || !$page) {
            $slug = static::pageSlug($tenantId, (string) ($args['slug'] ?? ($data['title'] ?? '')), $page);

            if ($slug === null) {
                $errors[] = 'Ese identificador (slug) no es válido o está reservado.';
            }
            elseif (!$page || $slug !== $page->slug) {
                $data['slug'] = $slug;
            }
        }

        if ($errors) {
            return ['saved' => false, 'errors' => $errors];
        }

        if ($page && $page->is_published && empty($args['confirm'])) {
            return ['saved' => false, 'confirmed' => false, 'warning' => 'La página «' . $page->title . '» está PUBLICADA: el cambio se vería en el sitio real al instante.', 'changes' => array_keys($data), 'next' => 'Pídele confirmación a la persona y repite con confirm:true. Se puede deshacer con web_undo.'];
        }

        try {
            if ($page) {
                $before = static::snapshot($page, static::PAGE_FIELDS);
                $page->forceFill($data)->save();
                static::record($tenantId, 'page', $page->id, 'update', $before, 'Editó la página «' . $page->title . '»', $args);
            }
            else {
                $page = new \Aero\Sites\Models\Page();
                $page->forceFill($data + [
                    'tenant_id' => $tenantId, 'content_mode' => 'puck', 'is_published' => false, 'is_placeholder' => false,
                    'layout' => 'default', 'sort_order' => (int) \Aero\Sites\Models\Page::forTenant($tenantId)->max('sort_order') + 1,
                ])->save();
                static::record($tenantId, 'page', $page->id, 'create', null, 'Creó la página «' . $page->title . '» (borrador)', $args);
            }
        }
        catch (\Throwable $e) {
            return ['saved' => false, 'errors' => ['No se pudo guardar: ' . $e->getMessage()]];
        }

        return ['saved' => true, 'id' => $page->id, 'slug' => $page->slug, 'published' => (bool) $page->is_published, 'blocks' => count((array) ($page->puck_data['content'] ?? [])),
            'edit_url' => static::tenantUrl($tenantId, \Backend::url('aero/sites/pages/update/' . $page->id)),
            'note' => $page->is_published ? 'Cambio aplicado en una página publicada.' : 'Queda como BORRADOR: no se ve en el sitio hasta publicarla.'];
    }

    public static function pagePublish(array $args, int $tenantId): array
    {
        if ($error = static::guard($tenantId)) {
            return $error;
        }

        $page = \Aero\Sites\Models\Page::forTenant($tenantId)->find((int) ($args['id'] ?? 0));

        if (!$page) {
            return ['error' => 'No existe esa página en este cliente.'];
        }

        $publish = (bool) ($args['published'] ?? false);

        if ($publish && empty($page->puck_data['content'])) {
            return ['error' => 'La página no tiene bloques: no se puede publicar vacía.'];
        }

        if (empty($args['confirm'])) {
            return ['confirmed' => false, 'page' => $page->title, 'will' => $publish ? 'publicar (visible en el sitio real)' : 'despublicar (dejar de mostrarla)', 'next' => 'Pídele confirmación a la persona y repite con confirm:true.'];
        }

        $before = static::snapshot($page, ['is_published']);
        $page->forceFill(['is_published' => $publish, 'is_placeholder' => false])->save();
        static::record($tenantId, 'page', $page->id, 'update', $before, ($publish ? 'Publicó' : 'Despublicó') . ' la página «' . $page->title . '»', $args);

        return ['confirmed' => true, 'id' => $page->id, 'published' => $publish];
    }

    public static function seoSet(array $args, int $tenantId): array
    {
        return static::configSet($args, $tenantId, 'seo', \Aero\Sites\Models\SeoConfig::class, static::SEO_FIELDS);
    }

    public static function contactSet(array $args, int $tenantId): array
    {
        if (isset($args['region']) && $args['region'] !== '' && !array_key_exists($args['region'], \Aero\Sites\Models\ContactConfig::BO_DEPARTMENTS)) {
            return ['error' => 'Departamento no válido.', 'valid' => array_keys(\Aero\Sites\Models\ContactConfig::BO_DEPARTMENTS)];
        }

        if (!empty($args['contact_email']) && !filter_var($args['contact_email'], FILTER_VALIDATE_EMAIL)) {
            return ['error' => 'El correo no es válido.'];
        }

        return static::configSet($args, $tenantId, 'contact', \Aero\Sites\Models\ContactConfig::class, static::CONTACT_FIELDS);
    }

    public static function collectionSave(array $args, int $tenantId): array
    {
        if ($error = static::guard($tenantId, true) ?? static::needPlan($args)) {
            return $error;
        }

        $collection = null;

        if (!empty($args['id'])) {
            $collection = \Aero\Shop\Models\Collection::forTenant($tenantId)->find((int) $args['id']);

            if (!$collection) {
                return ['saved' => false, 'errors' => ['No existe esa colección en este cliente.']];
            }
        }
        elseif (trim((string) ($args['name'] ?? '')) === '') {
            return ['saved' => false, 'errors' => ['Falta el nombre de la colección.']];
        }

        $data = [];

        foreach (['name' => 150, 'description' => 1000] as $key => $max) {
            if (array_key_exists($key, $args)) {
                $data[$key] = mb_substr(trim(strip_tags((string) $args[$key])), 0, $max);
            }
        }

        if (!empty($args['slug'])) {
            $data['slug'] = Str::slug((string) $args['slug']);
        }

        if (isset($args['is_active'])) {
            $data['is_active'] = (bool) $args['is_active'];
        }

        if (!empty($args['parent_id'])) {
            $parent = \Aero\Shop\Models\Collection::forTenant($tenantId)->find((int) $args['parent_id']);

            if (!$parent || ($collection && $parent->id === $collection->id)) {
                return ['saved' => false, 'errors' => ['La colección padre no es válida.']];
            }

            $data['parent_id'] = $parent->id;
        }

        try {
            if ($collection) {
                $before = static::snapshot($collection, static::COLLECTION_FIELDS);
                $collection->forceFill($data)->save();
                static::record($tenantId, 'collection', $collection->id, 'update', $before, 'Editó la colección «' . $collection->name . '»', $args);
            }
            else {
                $collection = new \Aero\Shop\Models\Collection();
                $collection->forceFill($data + ['tenant_id' => $tenantId, 'is_active' => true, 'sort_order' => 0])->save();
                static::record($tenantId, 'collection', $collection->id, 'create', null, 'Creó la colección «' . $collection->name . '»', $args);
            }
        }
        catch (\Throwable $e) {
            return ['saved' => false, 'errors' => ['No se pudo guardar: ' . $e->getMessage()]];
        }

        return ['saved' => true, 'id' => $collection->id, 'slug' => $collection->slug];
    }

    public static function productSave(array $args, int $tenantId): array
    {
        if ($error = static::guard($tenantId, true) ?? static::needPlan($args)) {
            return $error;
        }

        $product = null;

        if (!empty($args['id'])) {
            $product = \Aero\Shop\Models\Product::forTenant($tenantId)->where('is_internal', false)->find((int) $args['id']);

            if (!$product) {
                return ['saved' => false, 'errors' => ['No existe ese producto en este cliente.']];
            }

            if ($product->status === 'active' && empty($args['confirm'])) {
                return ['saved' => false, 'confirmed' => false, 'warning' => 'El producto «' . $product->name . '» está ACTIVO: el cambio se vería en la tienda al instante.', 'changes' => array_values(array_intersect(array_keys($args), static::PRODUCT_FIELDS)), 'next' => 'Pídele confirmación a la persona y repite con confirm:true. Se puede deshacer con web_undo.'];
            }
        }

        [$data, $errors] = static::productData($args, $tenantId, !$product);

        if ($errors) {
            return ['saved' => false, 'errors' => $errors];
        }

        try {
            if ($product) {
                $before = static::snapshot($product, static::PRODUCT_FIELDS);
                $product->forceFill($data)->save();
                static::record($tenantId, 'product', $product->id, 'update', $before, 'Editó el producto «' . $product->name . '»', $args);
            }
            else {
                $product = static::createProduct($data, $tenantId, $args);
            }
        }
        catch (\Throwable $e) {
            return ['saved' => false, 'errors' => ['No se pudo guardar: ' . $e->getMessage()]];
        }

        return ['saved' => true, 'product' => static::productPayload($product), 'edit_url' => static::tenantUrl($tenantId, \Backend::url('aero/shop/products/update/' . $product->id)),
            'note' => $product->status === 'draft' ? 'Queda en BORRADOR: no se ve en la tienda hasta activarlo.' : null];
    }

    public static function productsCreate(array $args, int $tenantId): array
    {
        if ($error = static::guard($tenantId, true) ?? static::needPlan($args)) {
            return $error;
        }

        $items = array_values((array) ($args['items'] ?? []));

        if (!$items || count($items) > static::MAX_BATCH) {
            return ['saved' => false, 'errors' => ['Envía entre 1 y ' . static::MAX_BATCH . ' productos por llamada.']];
        }

        // Todo o nada: primero se valida cada uno, después se crea.
        $prepared = [];
        $errors = [];

        foreach ($items as $i => $item) {
            [$data, $itemErrors] = static::productData((array) $item, $tenantId, true);

            foreach ($itemErrors as $e) {
                $errors[] = 'Producto ' . ($i + 1) . ': ' . $e;
            }

            $prepared[] = $data;
        }

        if ($errors) {
            return ['saved' => false, 'errors' => $errors];
        }

        $created = [];

        try {
            \DB::transaction(function () use ($prepared, $tenantId, $args, &$created) {
                foreach ($prepared as $data) {
                    $created[] = static::createProduct($data, $tenantId, $args);
                }
            });
        }
        catch (\Throwable $e) {
            return ['saved' => false, 'errors' => ['No se pudo guardar: ' . $e->getMessage()]];
        }

        return ['saved' => true, 'created' => count($created), 'products' => array_map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'slug' => $p->slug, 'status' => $p->status], $created), 'note' => 'Todos quedan en BORRADOR.'];
    }

    public static function productStatus(array $args, int $tenantId): array
    {
        if ($error = static::guard($tenantId, true)) {
            return $error;
        }

        $status = (string) ($args['status'] ?? '');

        if (!in_array($status, ['draft', 'active', 'archived'], true)) {
            return ['error' => 'Estado no válido: draft, active o archived.'];
        }

        $product = \Aero\Shop\Models\Product::forTenant($tenantId)->where('is_internal', false)->find((int) ($args['id'] ?? 0));

        if (!$product) {
            return ['error' => 'No existe ese producto en este cliente.'];
        }

        if ($status === 'active' && ((float) $product->base_price <= 0 || trim((string) $product->description) === '')) {
            return ['error' => 'Para activarlo necesita precio mayor a 0 y descripción. Complétalos con web_product_save.'];
        }

        if (empty($args['confirm'])) {
            return ['confirmed' => false, 'product' => $product->name, 'from' => $product->status, 'to' => $status, 'next' => 'Pídele confirmación a la persona y repite con confirm:true.'];
        }

        $before = static::snapshot($product, ['status', 'published_at']);
        $product->forceFill(['status' => $status, 'published_at' => $status === 'active' ? ($product->published_at ?: now()) : $product->published_at])->save();
        static::record($tenantId, 'product', $product->id, 'update', $before, "Cambió «{$product->name}» a {$status}", $args);

        return ['confirmed' => true, 'id' => $product->id, 'status' => $status];
    }

    public static function undo(array $args, int $tenantId): array
    {
        $query = Revision::where('tenant_id', $tenantId)->whereNull('undone_at');
        $revision = !empty($args['revision_id']) ? $query->find((int) $args['revision_id']) : $query->orderByDesc('id')->first();

        if (!$revision) {
            return ['error' => 'No hay un cambio por deshacer.'];
        }

        $model = static::subject($revision, $tenantId);

        if ($revision->action === 'create' && $model && (($model->is_published ?? false) || ($model->status ?? null) === 'active')) {
            return ['error' => 'Eso ya está publicado: no se elimina desde aquí. Despublícalo o archívalo con la confirmación de la persona.'];
        }

        if (empty($args['confirm'])) {
            return ['confirmed' => false, 'will' => $revision->action === 'create' ? 'eliminar lo creado: ' . $revision->summary : 'restaurar lo anterior a: ' . $revision->summary, 'next' => 'Pídele confirmación a la persona y repite con confirm:true.'];
        }

        try {
            if ($model) {
                if ($revision->action === 'create' || $revision->before === null) {
                    $model->delete();
                }
                else {
                    $model->forceFill((array) $revision->before)->save();
                }
            }
        }
        catch (\Throwable $e) {
            return ['error' => 'No se pudo deshacer: ' . $e->getMessage()];
        }

        $revision->update(['undone_at' => now()]);

        return ['confirmed' => true, 'undone' => $revision->summary];
    }

    // ------------------------------------------------------------- internals

    /** Plan acordado obligatorio para guardar. */
    protected static function needPlan(array $args): ?array
    {
        return mb_strlen(trim((string) ($args['agreed_plan'] ?? ''))) < 20
            ? ['saved' => false, 'errors' => ['Falta agreed_plan: antes de guardar, acuerda el plan con la persona y envíalo aquí en pasos simples.']]
            : null;
    }

    protected static function guard(int $tenantId, bool $shop = false): ?array
    {
        if ($tenantId <= 0 || !class_exists(\Aero\Sites\Models\Tenant::class) || !\Aero\Sites\Models\Tenant::where('id', $tenantId)->exists()) {
            return ['error' => 'No se pudo determinar el cliente.'];
        }

        if ($shop && !class_exists(\Aero\Shop\Models\Product::class)) {
            return ['error' => 'La tienda (Aero.Shop) no está disponible en esta instalación.'];
        }

        return null;
    }

    /** Ajuste de SEO o contacto: una sola fila por tenant, con confirmación y copia para deshacer. */
    protected static function configSet(array $args, int $tenantId, string $type, string $class, array $fields): array
    {
        if ($error = static::guard($tenantId) ?? static::needPlan($args)) {
            return $error;
        }

        $data = [];

        foreach ($fields as $field) {
            if (array_key_exists($field, $args)) {
                $data[$field] = static::cleanText((string) $args[$field], 500);

                if ($data[$field] === null) {
                    return ['error' => "El campo {$field} contiene contenido no permitido."];
                }
            }
        }

        if (!$data) {
            return ['error' => 'No enviaste ningún campo para cambiar.'];
        }

        if (empty($args['confirm'])) {
            return ['confirmed' => false, 'will_set' => $data, 'next' => 'Pídele confirmación a la persona y repite con confirm:true.'];
        }

        $row = $class::where('tenant_id', $tenantId)->first();
        $before = $row ? static::snapshot($row, $fields) : null;

        try {
            $row ??= new $class();
            $row->forceFill($data + ['tenant_id' => $tenantId] + ($type === 'seo' ? ['title_format' => '%s | {name}', 'sitemap_enabled' => true] : []))->save();
        }
        catch (\Throwable $e) {
            return ['error' => 'No se pudo guardar: ' . $e->getMessage()];
        }

        static::record($tenantId, $type, $row->id, $before === null ? 'create' : 'update', $before, 'Ajustó ' . ($type === 'seo' ? 'el SEO general' : 'los datos de contacto'), $args);

        return ['confirmed' => true, $type => array_intersect_key($row->only($fields), $data)];
    }

    /** @return array{0: array, 1: array<string>} [atributos listos, errores] */
    protected static function productData(array $args, int $tenantId, bool $creating): array
    {
        $data = [];
        $errors = [];

        if (array_key_exists('name', $args) || $creating) {
            $name = trim(strip_tags((string) ($args['name'] ?? '')));
            $name === '' || mb_strlen($name) > 200 ? $errors[] = 'El nombre es obligatorio (hasta 200 caracteres).' : $data['name'] = $name;
        }

        if (array_key_exists('description', $args)) {
            $clean = static::cleanHtml((string) $args['description']);
            $clean === null ? $errors[] = 'La descripción contiene contenido no permitido (scripts o enlaces peligrosos).' : $data['description'] = $clean;
        }

        foreach (['base_price', 'compare_at_price'] as $key) {
            if (array_key_exists($key, $args) && $args[$key] !== null && $args[$key] !== '') {
                is_numeric($args[$key]) && $args[$key] >= 0 && $args[$key] <= 10000000 ? $data[$key] = round((float) $args[$key], 2) : $errors[] = "{$key} debe ser un número entre 0 y 10.000.000.";
            }
        }

        if ($creating && !isset($data['base_price'])) {
            $errors[] = 'Falta base_price.';
        }

        if (isset($data['compare_at_price'], $data['base_price']) && $data['compare_at_price'] <= $data['base_price']) {
            $errors[] = 'compare_at_price debe ser mayor que base_price (es el precio anterior).';
        }

        if (array_key_exists('sku', $args)) {
            $data['sku'] = mb_substr(trim(strip_tags((string) $args['sku'])), 0, 80) ?: null;
        }

        if (!empty($args['collection_id'])) {
            \Aero\Shop\Models\Collection::forTenant($tenantId)->where('id', (int) $args['collection_id'])->exists() ? $data['collection_id'] = (int) $args['collection_id'] : $errors[] = 'Esa colección no existe en este cliente.';
        }

        if (isset($args['is_featured'])) {
            $data['is_featured'] = (bool) $args['is_featured'];
        }

        foreach (['seo_title' => 70, 'seo_description' => 160] as $key => $max) {
            if (array_key_exists($key, $args)) {
                $data[$key] = mb_substr(trim(strip_tags((string) $args[$key])), 0, $max) ?: null;
            }
        }

        if ($creating && isset($args['stock_quantity'])) {
            is_numeric($args['stock_quantity']) && $args['stock_quantity'] >= 0 ? $data['stock_quantity'] = (int) $args['stock_quantity'] : $errors[] = 'stock_quantity debe ser un entero mayor o igual a 0.';
        }

        return [$data, $errors];
    }

    protected static function createProduct(array $data, int $tenantId, array $args): \Aero\Shop\Models\Product
    {
        $product = new \Aero\Shop\Models\Product();
        $product->forceFill($data + [
            'tenant_id' => $tenantId, 'type' => 'physical', 'status' => 'draft', 'has_variants' => false, 'requires_shipping' => true,
            'track_inventory' => false, 'allow_backorder' => false, 'is_internal' => false,
        ])->save();

        static::record($tenantId, 'product', $product->id, 'create', null, 'Creó el producto «' . $product->name . '» (borrador)', $args);

        return $product;
    }

    /** @return array{0: ?array, 1: array<string>} [puck_data, errores] */
    protected static function buildPuck(array $blocks): array
    {
        $catalog = static::catalog();

        if ($catalog === null) {
            return [null, ['El catálogo de bloques no está disponible.']];
        }

        $blocks = array_values($blocks);

        if (!$blocks || count($blocks) > static::MAX_BLOCKS) {
            return [null, ['La página necesita entre 1 y ' . static::MAX_BLOCKS . ' bloques.']];
        }

        $errors = [];
        $content = [];

        foreach ($blocks as $i => $block) {
            $n = $i + 1;
            $type = is_array($block) ? ($block['type'] ?? null) : null;
            $props = is_array($block) ? ($block['props'] ?? null) : null;

            if (!is_string($type) || !isset($catalog[$type])) {
                $errors[] = "Bloque {$n}: tipo «" . (is_string($type) ? $type : '?') . '» no válido. Usa web_blocks_catalog.';
                continue;
            }

            if (!is_array($props)) {
                $errors[] = "Bloque {$n} ({$type}): props debe ser un objeto.";
                continue;
            }

            foreach ((array) ($catalog[$type]['fields'] ?? []) as $key => $def) {
                if (!array_key_exists($key, $props) || empty($def['options']) || is_array($props[$key])) {
                    continue;
                }

                $valid = array_map(fn ($o) => (string) (is_array($o) ? ($o['value'] ?? '') : $o), $def['options']);

                if (!in_array((string) $props[$key], $valid, true)) {
                    $errors[] = "Bloque {$n} ({$type}): «{$key}» = «{$props[$key]}» no es válido. Opciones: " . implode(', ', $valid) . '.';
                }
            }

            if ($bad = static::unsafe($props)) {
                $errors[] = "Bloque {$n} ({$type}): {$bad}";
            }

            $props['id'] = $type . '-' . Str::lower(Str::random(10));
            $content[] = ['type' => $type, 'props' => $props];
        }

        if ($errors) {
            return [null, $errors];
        }

        $puck = ['root' => ['props' => []], 'content' => $content];

        if (strlen(json_encode($puck, JSON_UNESCAPED_UNICODE)) > static::MAX_PAGE_BYTES) {
            return [null, ['La página es demasiado grande (máx. ' . (static::MAX_PAGE_BYTES / 1000) . ' KB). Divídela en dos.']];
        }

        $html = static::$renderer ? (static::$renderer)($puck) : (new \Aero\Sites\Classes\Ai\HeadlessRenderer())->render($puck);

        if (!is_string($html) || trim($html) === '') {
            return [null, ['Los bloques no se pudieron renderizar: revisa campos obligatorios con web_blocks_catalog.']];
        }

        return [$puck, []];
    }

    /** Recorre los valores buscando scripts, manejadores `on…=` y URLs peligrosas. */
    protected static function unsafe(mixed $value): ?string
    {
        if (is_array($value)) {
            foreach ($value as $v) {
                if ($bad = static::unsafe($v)) {
                    return $bad;
                }
            }

            return null;
        }

        if (!is_string($value)) {
            return null;
        }

        if (preg_match('/<\s*(script|iframe|object|embed)\b|\bon[a-z]+\s*=|(?:java|vb)script\s*:|data\s*:\s*text\/html/i', $value)) {
            return 'contiene scripts, iframes o manejadores de eventos, que no se permiten.';
        }

        return null;
    }

    protected static function cleanText(string $value, int $max): ?string
    {
        $value = trim(strip_tags($value));

        return static::unsafe($value) ? null : mb_substr($value, 0, $max);
    }

    /** HTML básico de descripción de producto; null si trae algo peligroso. */
    protected static function cleanHtml(string $html): ?string
    {
        if (static::unsafe($html)) {
            return null;
        }

        return trim(strip_tags($html, '<p><br><ul><ol><li><strong><b><em><i><h3><h4>'));
    }

    protected static function pageSlug(int $tenantId, string $raw, ?\Aero\Sites\Models\Page $page): ?string
    {
        $slug = Str::slug($raw);

        if ($slug === '') {
            return $page && $page->slug === '' ? '' : null; // la portada conserva su slug vacío; nadie más puede tomarlo
        }

        if (in_array($slug, ['backend', 'api', 'admin', 'storage', 'themes', 'plugins', 'modules', 'tienda', 'carrito', 'checkout'], true)) {
            return null;
        }

        $base = $slug;
        $n = 2;

        while (\Aero\Sites\Models\Page::withTrashed()->where('tenant_id', $tenantId)->where('slug', $slug)->when($page, fn ($q) => $q->where('id', '!=', $page->id))->exists()) {
            $slug = $base . '-' . $n++;
        }

        return $slug;
    }

    protected static function catalog(): ?array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $path = plugins_path('aero/sites/assets/puck-editor/dist/catalog.json');
        $raw = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return $cache = is_array($raw) ? $raw : null;
    }

    protected static function pageBrief(\Aero\Sites\Models\Page $page): array
    {
        return ['id' => $page->id, 'title' => $page->title, 'slug' => $page->slug, 'published' => (bool) $page->is_published,
            'in_menu' => (bool) $page->show_in_menu, 'blocks' => count((array) ($page->puck_data['content'] ?? []))];
    }

    protected static function productPayload(\Aero\Shop\Models\Product $p, bool $full = false): array
    {
        $out = ['id' => $p->id, 'name' => $p->name, 'slug' => $p->slug, 'status' => $p->status, 'base_price' => (float) $p->base_price,
            'compare_at_price' => $p->compare_at_price !== null ? (float) $p->compare_at_price : null, 'sku' => $p->sku, 'collection_id' => $p->collection_id,
            'is_featured' => (bool) $p->is_featured, 'stock_quantity' => (int) $p->stock_quantity, 'has_variants' => (bool) $p->has_variants];

        return $full ? $out + ['description' => $p->description, 'seo_title' => $p->seo_title, 'seo_description' => $p->seo_description, 'images' => $p->images()->count()] : $out;
    }

    protected static function snapshot(\Illuminate\Database\Eloquent\Model $model, array $fields): array
    {
        $out = [];

        foreach ($fields as $field) {
            $out[$field] = $model->getAttribute($field);
        }

        return $out;
    }

    protected static function record(int $tenantId, string $type, int $id, string $action, ?array $before, string $summary, array $args): void
    {
        Revision::create([
            'tenant_id' => $tenantId, 'subject_type' => $type, 'subject_id' => $id, 'action' => $action, 'before' => $before,
            'summary' => mb_substr($summary, 0, 250), 'agreed_plan' => mb_substr((string) ($args['agreed_plan'] ?? ''), 0, 2000),
        ]);
    }

    protected static function subject(Revision $revision, int $tenantId): mixed
    {
        $class = match ($revision->subject_type) {
            'page'       => \Aero\Sites\Models\Page::class,
            'seo'        => \Aero\Sites\Models\SeoConfig::class,
            'contact'    => \Aero\Sites\Models\ContactConfig::class,
            'product'    => \Aero\Shop\Models\Product::class,
            'collection' => \Aero\Shop\Models\Collection::class,
            default      => null,
        };

        return $class && class_exists($class) ? $class::where('tenant_id', $tenantId)->find($revision->subject_id) : null;
    }

    protected static function tenantUrl(int $tenantId, string $url): string
    {
        try {
            return \Aero\Sites\Models\Tenant::localizeBackendUrls($tenantId, $url);
        }
        catch (\Throwable) {
            return $url;
        }
    }
}
