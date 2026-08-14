<?php namespace Aero\Sites\Classes\Ai;

use Aero\AiFields\Models\Settings as AiSettings;
use Aero\AiFields\Services\AiService;
use Aero\Sites\Models\AiGeneration;
use Aero\Sites\Models\Archetype;
use Aero\Sites\Models\DesignTheme;
use Aero\Sites\Models\Tenant;
use Log;
use Exception;

/**
 * Genera páginas de micrositio vía IA usando el catálogo de componentes Puck.
 *
 * Flujo:
 * 1. Construye el prompt (descripción del negocio + nicho + datos del tenant + catálogo).
 * 2. Llama AiService::complete() (vía Aero.AiFields, cualquier proveedor configurado).
 * 3. Valida el JSON devuelto contra la estructura esperada.
 * 4. Si falla, reintenta hasta 2 veces con feedback de error.
 * 5. Registra el uso en aero_sites_ai_generations.
 */
class SiteGenerator
{
    protected AiService $ai;
    protected HeadlessRenderer $renderer;
    protected ImageSourceService $images;

    public function __construct()
    {
        $this->ai = new AiService();
        $this->renderer = new HeadlessRenderer();
        $this->images = new ImageSourceService();
    }

    // -----------------------------------------------------------------------
    // Catálogo de componentes — leído de dist/catalog.json (exportado desde
    // components.jsx por scripts/export-catalog.mjs, ver `npm run build` en
    // assets/puck-editor/). Única fuente de verdad para label/desc/fields;
    // acá solo se traduce al formato de "hint" plano que usa el prompt.
    // -----------------------------------------------------------------------

    protected ?array $catalogCache = null;

    protected function componentCatalog(): array
    {
        if ($this->catalogCache !== null) {
            return $this->catalogCache;
        }

        $path = plugins_path('aero/sites/assets/puck-editor/dist/catalog.json');
        if (!file_exists($path)) {
            throw new Exception(
                "Catálogo de componentes no encontrado en {$path}. " .
                "Ejecutá 'npm run build' en plugins/aero/sites/assets/puck-editor."
            );
        }

        $raw = json_decode((string) file_get_contents($path), true);
        if (!is_array($raw)) {
            throw new Exception("Catálogo de componentes inválido en {$path}.");
        }

        $catalog = [];
        foreach ($raw as $name => $def) {
            $catalog[$name] = [
                'desc'   => $def['desc'] ?? '',
                'fields' => $this->translateFieldsForPrompt($def['fields'] ?? []),
            ];
        }

        return $this->catalogCache = $this->applyAiOverrides($catalog);
    }

    /**
     * Convierte los `fields` del editor Puck (type/options/arrayFields) al
     * formato de "hint" plano que espera el prompt (ej. "solid|outline",
     * "array[{quote, author, role}]"). Campos con "(opcional)" en su label
     * se marcan como nullable.
     */
    protected function translateFieldsForPrompt(array $fields): array
    {
        $out = [];

        foreach ($fields as $key => $def) {
            $type  = $def['type'] ?? 'text';
            $label = mb_strtolower($def['label'] ?? '');
            $nullable = str_contains($label, 'opcional');

            $hint = match ($type) {
                'radio', 'select' => implode('|', array_column($def['options'] ?? [], 'value')),
                'array'            => 'array[{' . implode(', ', array_keys($def['arrayFields'] ?? [])) . '}]',
                'textarea'         => str_contains($label, 'html') ? 'string (HTML)' : 'string',
                default            => 'string',
            };

            $out[$key] = $nullable ? "{$hint}|null" : $hint;
        }

        return $out;
    }

    /**
     * Ajustes al catálogo específicos de la generación con IA que no tienen
     * sentido en el editor Puck en sí (ej. pedir keywords de imagen en vez
     * de una URL, que luego resuelve ImageSourceService).
     */
    protected function applyAiOverrides(array $catalog): array
    {
        if (isset($catalog['ImageBlock'])) {
            unset($catalog['ImageBlock']['fields']['imageUrl']);
            $catalog['ImageBlock']['fields'] = [
                'imageKeywords' => 'string (2-4 palabras en inglés describiendo la foto, ej. "italian restaurant interior")',
            ] + $catalog['ImageBlock']['fields'];
            $catalog['ImageBlock']['desc'] .= ' La foto real se busca automáticamente por palabras clave (no inventes una URL).';
        }

        if (isset($catalog['Gallery']['fields']['images'])) {
            $catalog['Gallery']['fields']['images'] = 'array[{imageKeywords (2-4 palabras en inglés), alt}]';
            $catalog['Gallery']['desc'] .= ' Las fotos reales se buscan automáticamente por palabras clave (no inventes URLs).';
        }

        if (isset($catalog['LogoCloud'])) {
            $catalog['LogoCloud']['desc'] .= ' No hay forma de obtener logos reales automáticamente: evita este bloque salvo que el usuario haya mencionado marcas/partners específicos.';
        }

        return $catalog;
    }

    // -----------------------------------------------------------------------
    // Prompt
    // -----------------------------------------------------------------------

    protected function buildSystemPrompt(Tenant $tenant, array $archetype): string
    {
        $nicheLabel = $tenant->niche_type;
        $tenant->load(['seoConfig', 'contactConfig']);

        $businessName  = $tenant->name;
        $seo           = $tenant->seoConfig;
        $contact       = $tenant->contactConfig;

        $contactInfo = '';
        if ($contact) {
            $parts = [];
            if ($contact->contact_email) $parts[] = "email: {$contact->contact_email}";
            if ($contact->phone) $parts[] = "teléfono: {$contact->phone}";
            if ($contact->whatsapp) $parts[] = "WhatsApp: {$contact->whatsapp}";
            if ($contact->address) $parts[] = "dirección: {$contact->address}";
            if ($parts) $contactInfo = "\nDatos de contacto del negocio: " . implode(', ', $parts) . '.';
        }

        $defaultDesc = $seo?->default_description ?? '';

        // Catálogo de componentes como texto estructurado para el prompt
        $catalog = '';
        foreach ($this->componentCatalog() as $name => $spec) {
            $catalog .= "- **$name**: {$spec['desc']}\n  Props: " . json_encode($spec['fields']) . "\n";
        }

        $blocks = $archetype['blocks'] ?? [];
        $archetypeInstruction = $blocks
            ? 'Como punto de partida recomendado para este tipo de negocio, usa esta secuencia de bloques: '
                . implode(' → ', $blocks) . '. Podés ajustar cantidad/orden si la descripción del usuario lo amerita, '
                . 'pero mantené un hero al inicio y un CTA al final.'
            : 'Elegí una secuencia de 4 a 8 bloques con un hero al inicio y un CTA al final.';

        return <<<PROMPT
Eres un diseñador de sitios web. Debes generar una página de inicio (landing page) profesional
para un micrositio de negocio usando EXCLUSIVAMENTE los componentes disponibles abajo.

El JSON de respuesta DEBE tener esta estructura exacta:
{
  "content": [
    { "type": "NombreComponente", "props": { ... } }
  ],
  "root": { "props": {} }
}

Reglas:
- SOLO usa los componentes del catálogo. No inventes nuevos.
- El array "content" debe contener entre 4 y 8 bloques (un hero al inicio y un CTA al final).
- {$archetypeInstruction}
- Cada bloque debe ser del tipo de uno de los componentes del catálogo.
- Los props deben ser planos (strings, números), no anidados.
- La respuesta debe ser SOLO el JSON, sin texto adicional, sin markdown, sin ```.
- Usa valores realistas y coherentes con el negocio descrito.
- Usa nombres de marca reales en lugar de placeholders.
- NO uses comillas dobles dentro de strings (escapar si es necesario).

Catálogo de componentes disponibles:
{$catalog}

Negocio: {$businessName}
Rubro / nicho: {$nicheLabel}
{$contactInfo}
Descripción del negocio (SEO): {$defaultDesc}
PROMPT;
    }

    // -----------------------------------------------------------------------
    // Arquetipo y tema
    // -----------------------------------------------------------------------

    /**
     * @param string|null $archetypeHandle  Elegido explícitamente por el usuario en el panel
     *                                       de generación. Si es null (o no existe/inactivo),
     *                                       se elige uno al azar entre los afines al nicho.
     */
    protected function resolveArchetype(Tenant $tenant, ?string $archetypeHandle = null): array
    {
        $default = ['handle' => 'default', 'blocks' => ['Hero', 'FeatureGrid', 'Testimonials', 'CTASection'], 'recommended_tones' => []];

        if ($archetypeHandle) {
            $chosen = Archetype::active()->where('handle', $archetypeHandle)->first();
            if ($chosen) {
                return $this->archetypeToArray($chosen);
            }
        }

        $candidates = Archetype::active()->forNiche($tenant->niche_type)->get();

        if ($candidates->isEmpty()) {
            return $default;
        }

        return $this->archetypeToArray($candidates->random());
    }

    protected function archetypeToArray(Archetype $archetype): array
    {
        return [
            'handle'            => $archetype->handle,
            'blocks'            => $archetype->blocks_list,
            'recommended_tones' => $archetype->recommended_tones ?? [],
        ];
    }

    /**
     * Si el tenant ya tiene un DesignTheme asignado, lo respeta. Si no,
     * elige uno acorde al nicho/tono del arquetipo y lo asigna de forma
     * persistente (para que el sitio se mantenga visualmente coherente en
     * futuras ediciones/generaciones).
     */
    protected function resolveTheme(Tenant $tenant, array $archetype): ?DesignTheme
    {
        if ($tenant->design_theme_id && $tenant->designTheme) {
            return $tenant->designTheme;
        }

        $tones = $archetype['recommended_tones'] ?? [];

        $theme = DesignTheme::active()->forNiche($tenant->niche_type)
            ->when($tones, fn ($q) => $q->whereIn('tone', $tones))
            ->inRandomOrder()->first()
            ?? DesignTheme::active()->forNiche($tenant->niche_type)->inRandomOrder()->first()
            ?? DesignTheme::active()->inRandomOrder()->first();

        if ($theme) {
            $tenant->design_theme_id = $theme->id;
            $tenant->save();
            $tenant->setRelation('designTheme', $theme);
        }

        return $theme;
    }

    // -----------------------------------------------------------------------
    // Validación
    // -----------------------------------------------------------------------

    protected function validatePuckJson(array $data): array
    {
        $errors = [];

        if (!isset($data['content']) || !is_array($data['content'])) {
            $errors[] = 'Falta "content" (debe ser un array).';
            return $errors;
        }

        $validTypes = array_keys($this->componentCatalog());

        foreach ($data['content'] as $i => $block) {
            if (!isset($block['type'])) {
                $errors[] = "Bloque {$i}: falta 'type'.";
                continue;
            }
            if (!in_array($block['type'], $validTypes, true)) {
                $errors[] = "Bloque {$i}: tipo '{$block['type']}' no válido. Tipos permitidos: " . implode(', ', $validTypes) . '.';
            }
            if (!isset($block['props']) || !is_array($block['props'])) {
                $errors[] = "Bloque {$i}: 'props' debe ser un objeto.";
            }
        }

        if (!isset($data['root'])) {
            $errors[] = 'Falta "root".';
        }

        return $errors;
    }

    /**
     * Puck requiere un `props.id` único por bloque (lo asigna solo cuando se
     * arma a mano en el editor). Sin esto el editor visual React solo
     * conserva el último bloque y duplica entradas al guardar.
     */
    protected function injectBlockIds(array $data): array
    {
        foreach ($data['content'] as $i => &$block) {
            $props = is_array($block['props'] ?? null) ? $block['props'] : [];
            $props['id'] = ($block['type'] ?? 'block') . '-' . uniqid() . '-' . $i;
            $block['props'] = $props;
        }
        unset($block);

        return $data;
    }

    // -----------------------------------------------------------------------
    // Imágenes
    // -----------------------------------------------------------------------

    /**
     * Reemplaza `imageKeywords` (pedido a la IA) por `imageUrl`/`url` reales,
     * resueltos vía ImageSourceService. Nunca falla: si no hay key de
     * Unsplash configurada o la búsqueda falla, usa un placeholder.
     */
    protected function resolveImages(array $data): array
    {
        foreach ($data['content'] as &$block) {
            $type  = $block['type'] ?? '';
            $props = is_array($block['props'] ?? null) ? $block['props'] : [];

            if ($type === 'ImageBlock' && isset($props['imageKeywords'])) {
                $resolved = $this->images->resolve((string) $props['imageKeywords']);
                $props['imageUrl'] = $resolved['url'];
                unset($props['imageKeywords']);
            }

            if ($type === 'Gallery' && isset($props['images']) && is_array($props['images'])) {
                foreach ($props['images'] as &$image) {
                    if (!is_array($image) || !isset($image['imageKeywords'])) {
                        continue;
                    }
                    $resolved = $this->images->resolve((string) $image['imageKeywords']);
                    $image['url'] = $resolved['url'];
                    unset($image['imageKeywords']);
                }
                unset($image);
            }

            $block['props'] = $props;
        }
        unset($block);

        return $data;
    }

    // -----------------------------------------------------------------------
    // Generación
    // -----------------------------------------------------------------------

    /**
     * @param Tenant $tenant
     * @param string $userPrompt  Descripción adicional del negocio provista por el usuario.
     * @param int    $retries     Intentos máximos de reparación (default 2).
     * @param AiGeneration|null $log  Registro ya creado (ej. por el controller antes de encolar
     *                                el job) a actualizar en vez de crear uno nuevo. Preserva
     *                                tenant_id/user_id ya seteados en ese registro.
     * @param string|null $archetypeHandle  Arquetipo elegido explícitamente por el usuario
     *                                      (ver Archetype); null = elegir uno al azar por nicho.
     * @return array{html: string, puck_data: array, log_id: int}|null
     */
    public function generate(Tenant $tenant, string $userPrompt, int $retries = 2, ?AiGeneration $log = null, ?string $archetypeHandle = null): ?array
    {
        $archetype = $this->resolveArchetype($tenant, $archetypeHandle);
        $this->resolveTheme($tenant, $archetype);

        $systemPrompt = $this->buildSystemPrompt($tenant, $archetype);
        $combinedPrompt = "DESCRIPCIÓN ADICIONAL DEL USUARIO:\n{$userPrompt}\n\nGenera el JSON de la página.";

        $lastError = null;
        $attempts  = 0;

        for ($i = 0; $i <= $retries; $i++) {
            if ($i > 0) {
                // Retry: añadir feedback de error al prompt
                $combinedPrompt .= "\n\nCORRECCIÓN REQUERIDA (intento anterior falló con estos errores de validación):\n- " . implode("\n- ", $lastError) . "\n\nVuelve a generar SOLO el JSON corregido.";
            }

            try {
                $result = $this->ai->complete($combinedPrompt, [
                    'system'      => $systemPrompt,
                    'provider'    => null,  // usa el default de AiFields
                    'max_tokens'  => 8000,
                    'temperature' => 0.5,
                ]);

                $attempts++;

                $raw = $result['content'] ?? '';
                // Limpiar posibles delimitadores de código markdown
                $raw = preg_replace('/^```(?:json)?\s*/', '', trim($raw));
                $raw = preg_replace('/\s*```$/', '', $raw);

                $data = json_decode($raw, true);

                if (!is_array($data)) {
                    $lastError = ['La respuesta no es JSON válido: ' . (json_last_error_msg() ?: 'error de parseo')];
                    continue;
                }

                $errors = $this->validatePuckJson($data);
                if (!empty($errors)) {
                    $lastError = $errors;
                    continue;
                }

                // Éxito — asignar ids únicos por bloque (Puck los requiere en
                // props.id; sin esto el editor visual solo conserva el último
                // bloque y duplica entradas al guardar) y resolver imágenes
                // reales antes de renderizar.
                $data = $this->injectBlockIds($data);
                $data = $this->resolveImages($data);
                $html = $this->renderer->render($data);

                $logData = [
                    'tenant_id'     => $tenant->id,
                    'user_id'       => $log->user_id ?? \BackendAuth::getUser()?->id,
                    'provider'      => $result['provider'] ?? null,
                    'model'         => $result['model'] ?? null,
                    'prompt'        => mb_substr($userPrompt, 0, 2000),
                    'input_tokens'  => $result['usage']['input_tokens'] ?? 0,
                    'output_tokens' => $result['usage']['output_tokens'] ?? 0,
                    'success'       => true,
                    'status'        => 'done',
                    'retry_count'   => $i,
                ];

                if ($log) {
                    $log->update($logData);
                } else {
                    $log = AiGeneration::create($logData);
                }

                return [
                    'html'      => $html,
                    'puck_data' => $data,
                    'log_id'    => $log->id,
                ];
            } catch (Exception $e) {
                $attempts++;
                $lastError = [$e->getMessage()];
                Log::error("Aero\\Sites\\SiteGenerator: intento {$attempts}: " . $e->getMessage());
            }
        }

        // Todos los intentos fallaron — registrar fallo
        $failData = [
            'tenant_id'     => $tenant->id,
            'user_id'       => $log->user_id ?? \BackendAuth::getUser()?->id,
            'provider'      => null,
            'prompt'        => mb_substr($userPrompt, 0, 2000),
            'success'       => false,
            'status'        => 'failed',
            'error_message' => is_array($lastError) ? implode('; ', $lastError) : ($lastError ?? 'Unknown'),
            'retry_count'   => $i ?? 0,
        ];

        if ($log) {
            $log->update($failData);
        } else {
            AiGeneration::create($failData);
        }

        return null;
    }
}
