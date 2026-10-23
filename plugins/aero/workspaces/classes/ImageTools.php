<?php namespace Aero\Workspaces\Classes;

use Aero\Workspaces\Models\Settings;
use Aero\Workspaces\Models\Staff;

/**
 * Herramientas de imagen de Paola (privadas, nunca en el registro público):
 *  - image_search:   foto real de Unsplash por palabras clave (gratis; usa el banco de Aero.Sites).
 *  - image_generate: imagen creada con IA (GPT Image 1 por el conector de IA). Se cobra por cada imagen
 *                    en el tipo de crédito de Paola, SIEMPRE con aviso del precio y confirmación previa,
 *                    y se reembolsa si el proveedor falla. No depende del interruptor de cobro de
 *                    Workspaces: generar cuesta dinero real, así que sin tarifa no se genera.
 *
 * La tarifa es la fila de tarifa `imagen` del agente (Billing::IMAGE); el tenant lo pone el motor.
 */
class ImageTools
{
    public const CATEGORY = 'imagenes';
    public const AGENT = 'paola';
    public const DAILY_LIMIT = 30;

    /** Tamaños que ofrecemos → tamaño del proveedor. */
    public const SIZES = ['cuadrada' => '1024x1024', 'horizontal' => '1536x1024', 'vertical' => '1024x1536'];

    /** Pruebas: fn (string $prompt, string $size): string con los bytes PNG. */
    public static ?\Closure $generator = null;

    /** Pruebas: fn (string $keywords): array{url: string, attribution: ?string}|null. */
    public static ?\Closure $searcher = null;

    /** Pruebas: fn (int $tenantId, string $bytes): string con la URL. */
    public static ?\Closure $storer = null;

    public static function tools(): array
    {
        $tool = fn (string $description, array $properties, array $required, string $method) => [
            'description' => $description,
            'category'    => static::CATEGORY,
            'parameters'  => ['type' => 'object', 'properties' => $properties ?: new \stdClass(), 'required' => $required],
            'handler'     => [static::class, $method],
        ];

        return [
            'image_context' => $tool(
                'PRIMERO, siempre que pidan una imagen. Cuánto cuesta generar UNA imagen con IA (y en qué moneda), el saldo de la persona, cuántas puede generar con él y si la búsqueda de fotos reales está disponible.',
                [], [], 'context'
            ),
            'image_search' => $tool(
                'Busca UNA foto real (banco Unsplash) por palabras clave, mejor en inglés y concretas. GRATIS. Devuelve el enlace y el crédito del autor, que debes mencionar.',
                ['keywords' => ['type' => 'string', 'description' => 'Qué debe mostrar la foto, p. ej. «modern dental clinic reception».']],
                ['keywords'], 'search'
            ),
            'image_generate' => $tool(
                'Genera UNA imagen nueva con IA a partir de una descripción. COBRA créditos por cada imagen. Sin confirm:true NO genera ni cobra: devuelve el precio y el saldo. Con confirm:true cobra y genera; si falla, devuelve el cobro. Nunca la uses sin que la persona haya aceptado el precio.',
                [
                    'prompt'       => ['type' => 'string', 'description' => 'Descripción visual detallada: sujeto, estilo, luz, composición. Sin texto escrito dentro de la imagen salvo que lo pidan.'],
                    'orientation'  => ['type' => 'string', 'enum' => array_keys(static::SIZES), 'description' => 'cuadrada (por defecto), horizontal o vertical.'],
                    'agreed_plan'  => ['type' => 'string', 'description' => 'Lo que la persona aprobó, incluido el precio (mín. 20 caracteres). Obligatorio.'],
                    'confirm'      => ['type' => 'boolean', 'description' => 'false o ausente: solo informa el precio. true únicamente tras el «sí» explícito de la persona al precio.'],
                ],
                ['prompt', 'agreed_plan'], 'generate'
            ),
        ];
    }

    // -- Herramientas ---------------------------------------------------------------------

    public static function context(array $args, int $tenantId, array $ctx = []): array
    {
        $staff = static::staff();
        $price = $staff ? static::price($staff) : 0;
        $type = $staff ? Billing::creditType($staff) : null;
        $label = $type ? (Workspace::creditTypes()[$type]['label'] ?? $type) : null;
        $balance = null;
        $reason = static::unavailable($staff, $price);

        if (!$reason && static::creditsAvailable()) {
            try {
                $balance = Billing::ledger()->balance($tenantId, $type);
            }
            catch (\Throwable $e) {
                $reason = 'No se pudo consultar el saldo.';
            }
        }

        return [
            'generation' => [
                'available'      => $reason === null,
                'reason'         => $reason,
                'price_per_image' => $price,
                'credit'         => $label,
                'balance'        => $balance,
                'images_you_can_afford' => ($balance !== null && $price > 0) ? intdiv($balance, $price) : null,
                'orientations'   => array_keys(static::SIZES),
                'daily_limit'    => static::DAILY_LIMIT,
            ],
            'search' => ['available' => static::searchReady(), 'price_per_image' => 0],
            'note'   => 'Dile a la persona el precio por imagen ANTES de generar y espera su «sí». Buscar fotos reales es gratis.',
        ];
    }

    public static function search(array $args, int $tenantId, array $ctx = []): array
    {
        $keywords = trim((string) ($args['keywords'] ?? ''));

        if ($keywords === '') {
            return ['error' => 'Dime qué debe mostrar la foto.'];
        }

        if (!static::searchReady()) {
            return ['error' => 'La búsqueda de fotos reales no está configurada en la plataforma. Puedo generar una imagen con IA (con costo) si la persona quiere.'];
        }

        try {
            $found = static::$searcher
                ? (static::$searcher)($keywords)
                : (new \Aero\Sites\Classes\Ai\ImageSourceService())->resolve($keywords);
        }
        catch (\Throwable $e) {
            return ['error' => 'La búsqueda de fotos falló. Inténtalo de nuevo.'];
        }

        if (!$found || empty($found['url']) || str_contains((string) $found['url'], 'placehold.co')) {
            return ['error' => 'No encontré una foto para «' . $keywords . '». Prueba con otras palabras o genera una con IA (con costo).'];
        }

        return ['photo' => ['url' => $found['url'], 'credit' => $found['attribution'] ?? null], 'free' => true, 'note' => 'Foto real de un banco de imágenes; menciona el crédito del autor.'];
    }

    public static function generate(array $args, int $tenantId, array $ctx = []): array
    {
        $prompt = trim((string) ($args['prompt'] ?? ''));
        $size = static::SIZES[$args['orientation'] ?? 'cuadrada'] ?? null;
        $staff = static::staff();
        $price = $staff ? static::price($staff) : 0;

        if (mb_strlen($prompt) < 8) {
            return ['error' => 'Describe mejor la imagen (sujeto, estilo, composición).'];
        }

        if (!$size) {
            return ['error' => 'La orientación debe ser: ' . implode(', ', array_keys(static::SIZES)) . '.'];
        }

        if ($reason = static::unavailable($staff, $price)) {
            return ['error' => $reason];
        }

        $type = Billing::creditType($staff);
        $label = Workspace::creditTypes()[$type]['label'] ?? $type;

        try {
            $balance = Billing::ledger()->balance($tenantId, $type);
        }
        catch (\Throwable $e) {
            return ['error' => 'No se pudo consultar el saldo.'];
        }

        if (empty($args['confirm'])) {
            return [
                'confirm_needed' => true,
                'price'          => $price,
                'credit'         => $label,
                'balance'        => $balance,
                'enough'         => $balance >= $price,
                'message'        => "Generar esta imagen cuesta {$price} {$label}; tienes {$balance}. No se cobra hasta que la persona acepte.",
            ];
        }

        if (mb_strlen(trim((string) ($args['agreed_plan'] ?? ''))) < 20) {
            return ['error' => 'Falta el plan acordado con el precio (agreed_plan).'];
        }

        if ($balance < $price) {
            return ['error' => "No tienes saldo suficiente: la imagen cuesta {$price} {$label} y tienes {$balance}."];
        }

        $cap = 'workspaces.images.' . $tenantId . '.' . now()->format('Ymd');

        if ((int) \Cache::get($cap, 0) >= static::DAILY_LIMIT) {
            return ['error' => 'Se alcanzó el límite diario de imágenes generadas (' . static::DAILY_LIMIT . '). Inténtalo mañana.'];
        }

        try {
            $txId = Billing::charge($tenantId, $price, 'workspaces.image', 'Imagen generada con ' . $staff->name, 'workspaces.image.' . $tenantId . '.' . \Str::uuid(), [
                'staff_id' => $staff->id, 'credit_type' => $type,
            ]);
        }
        catch (\Throwable $e) {
            \Log::warning('aero.workspaces: no se pudo cobrar la imagen', ['tenant_id' => $tenantId, 'error' => $e->getMessage()]);

            return ['error' => 'No se pudo cobrar la imagen; no se generó nada.'];
        }

        try {
            $bytes = static::render($prompt, $size);
            $url = static::store($tenantId, $bytes);
        }
        catch (\Throwable $e) {
            \Log::warning('aero.workspaces: falló la generación de imagen', ['tenant_id' => $tenantId, 'error' => $e->getMessage()]);

            try {
                Billing::ledger()->refund($txId, 'La imagen no se pudo generar');
            }
            catch (\Throwable $r) {
                \Log::error('aero.workspaces: no se pudo reembolsar la imagen', ['tx' => $txId, 'error' => $r->getMessage()]);
            }

            return ['error' => 'No se pudo generar la imagen. No se te cobró nada (se devolvieron las ' . $price . ' ' . $label . ').', 'refunded' => true];
        }

        \Cache::put($cap, (int) \Cache::get($cap, 0) + 1, now()->endOfDay());
        \Event::fire('aero.workspaces.charged', [$tenantId, 'image', $price, (int) $staff->id, $txId]);

        return ['generated' => true, 'url' => $url, 'charged' => $price, 'credit' => $label, 'balance' => $balance - $price,
            'note' => 'Entrega el enlace tal cual. Es una imagen creada con IA, no una foto real.'];
    }

    // -- Piezas ---------------------------------------------------------------------------

    protected static function staff(): ?Staff
    {
        return Staff::where('slug', static::AGENT)->first();
    }

    /** Créditos de UNA imagen (0 = sin tarifa configurada). */
    public static function price(Staff $staff): int
    {
        return Billing::rate($staff, Billing::IMAGE);
    }

    protected static function creditsAvailable(): bool
    {
        return Billing::$ledger !== null || class_exists(\Aero\Credits\Classes\Credits::class);
    }

    /** Motivo por el que no se puede generar, o null si sí. */
    protected static function unavailable(?Staff $staff, int $price): ?string
    {
        if (!$staff) {
            return 'El agente de imágenes no existe.';
        }

        if (!static::creditsAvailable()) {
            return 'El cobro en créditos no está disponible, así que no se generan imágenes.';
        }

        if ($price <= 0) {
            return 'La generación de imágenes aún no tiene tarifa configurada.';
        }

        if (!static::$generator && !static::connector()) {
            return 'La generación de imágenes no está configurada en la plataforma (falta el conector de IA).';
        }

        return null;
    }

    protected static function searchReady(): bool
    {
        if (static::$searcher) {
            return true;
        }

        return class_exists(\Aero\Sites\Classes\Ai\ImageSourceService::class)
            && class_exists(\Aero\Sites\Models\Settings::class)
            && \Aero\Sites\Models\Settings::isImageSourcingConfigured();
    }

    protected static function profile(): ?array
    {
        return Settings::modelOfKind('image');
    }

    /** Conector del perfil «image» del catálogo; si no tiene, el de los agentes. */
    protected static function connector(): ?\Aero\Connector\Models\Connector
    {
        if (!class_exists(\Aero\Connector\Models\Connector::class)) {
            return null;
        }

        $profile = static::profile();
        $id = (int) ($profile['connector_id'] ?? 0) ?: (int) Settings::agentConnectorId();

        $connector = $id ? \Aero\Connector\Models\Connector::find($id) : null;

        // Solo conectores OpenAI-compatibles (o el gateway de Cloudflare): el del agente puede ser otro tipo (Hub).
        return ($connector && $connector->is_enabled && $connector->type === 'ai_openai_compatible') ? $connector : null;
    }

    /** @return string bytes de la imagen */
    protected static function render(string $prompt, string $size): string
    {
        if (static::$generator) {
            return (string) (static::$generator)($prompt, $size);
        }

        $connector = static::connector() ?? throw new \RuntimeException('Sin conector de imagen.');
        $profile = static::profile();
        $model = (string) ($profile['model'] ?? 'openai/gpt-image-1');
        $base = rtrim((string) \Aero\Connector\Classes\AiGateway::baseUrl($connector), '/');

        // Cloudflare AI Gateway: «/compat» no ofrece imágenes; se usa la ruta propia del proveedor.
        if ($connector->provider_hint === \Aero\Connector\Classes\AiGateway::CLOUDFLARE) {
            $base = preg_replace('#/compat$#', '', $base) . '/openai';
            $model = preg_replace('#^openai/#', '', $model);
        }

        $request = \Http::withHeaders(\Aero\Connector\Classes\AiGateway::headers($connector));

        if ($key = $connector->credentials['api_key'] ?? null) {
            $request = $request->withToken($key);
        }

        $response = $request->connectTimeout(10)->timeout(120)->post($base . '/images/generations', [
            'model' => $model, 'prompt' => $prompt, 'size' => $size, 'quality' => 'low', 'n' => 1,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('El proveedor respondió HTTP ' . $response->status());
        }

        $b64 = $response->json('data.0.b64_json');
        $bytes = $b64 ? base64_decode($b64, true) : false;

        if (!$bytes || strlen($bytes) < 500) {
            throw new \RuntimeException('El proveedor no devolvió una imagen válida.');
        }

        return $bytes;
    }

    /** Guarda la imagen como archivo público del tenant y devuelve su URL. */
    protected static function store(int $tenantId, string $bytes): string
    {
        if (static::$storer) {
            return (string) (static::$storer)($tenantId, $bytes);
        }

        $file = new \System\Models\File();
        $file->fromData($bytes, 'ia-' . now()->format('Ymd-His') . '-' . \Str::lower(\Str::random(6)) . '.png');
        $file->is_public = true;
        $file->save();

        if (class_exists(\Aero\Sites\Models\Tenant::class) && ($tenant = \Aero\Sites\Models\Tenant::find($tenantId)) && method_exists($tenant, 'puck_uploads')) {
            $tenant->puck_uploads()->add($file);
        }

        return $file->getPath();
    }
}
