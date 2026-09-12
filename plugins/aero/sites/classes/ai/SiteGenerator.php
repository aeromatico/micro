<?php namespace Aero\Sites\Classes\Ai;

use Aero\AiFields\Models\Settings as AiSettings;
use Aero\AiFields\Services\AiService;
use Aero\Sites\Classes\Niches\NicheManager;
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
        if (isset($catalog['Hero']['fields']['bgImage'])) {
            unset($catalog['Hero']['fields']['bgImage']);
            $catalog['Hero']['fields']['bgImageKeywords'] = 'string|null (2-4 palabras en inglés para una foto de fondo, '
                . 'ej. "modern gym interior" — opcional: dejar vacío/omitir si no hay una foto realmente relevante; '
                . 'el bloque se ve bien igual con el fondo neutro del tema)';
        }

        if (isset($catalog['Hero']['fields']['image'])) {
            unset($catalog['Hero']['fields']['image']);
            $catalog['Hero']['fields']['imageKeywords'] = 'string|null (2-4 palabras en inglés para una foto de contenido, '
                . 'ej. "team meeting office" — SOLO completar si variant es "imagen-derecha" o "imagen-izquierda", '
                . 'dejar vacío/omitir en cualquier otra variante)';
        }

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

    protected function buildSystemPrompt(Tenant $tenant, array $archetype, ?DesignTheme $theme = null): string
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
        if ($blocks) {
            $lines = [];
            foreach ($blocks as $i => $block) {
                $line = ($i + 1) . '. ' . $block['type'];
                if (!empty($block['instruction'])) {
                    $line .= ' — ' . $block['instruction'];
                }
                $lines[] = $line;
            }
            $archetypeInstruction = "Como punto de partida recomendado para este tipo de negocio, usa esta secuencia de "
                . "bloques (podés ajustar cantidad/orden si la descripción del usuario lo amerita, pero mantené un hero "
                . "al inicio y un CTA al final). Cuando un bloque trae una instrucción específica, seguila al pie de la "
                . "letra para ese bloque:\n" . implode("\n", $lines);
        } else {
            $archetypeInstruction = 'Elegí una secuencia de 4 a 8 bloques con un hero al inicio y un CTA al final.';
        }

        $writingGuidance = '';
        if (!empty($archetype['tone_instructions'])) {
            $writingGuidance .= "\nTono y estilo de escritura: {$archetype['tone_instructions']}";
        }
        if (!empty($archetype['target_audience'])) {
            $writingGuidance .= "\nPúblico objetivo: {$archetype['target_audience']}";
        }

        $paletteGuidance = $this->buildPaletteGuidance($tenant, $theme);

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
{$writingGuidance}
{$paletteGuidance}
PROMPT;
    }

    /**
     * Describe la paleta/tema visual YA asignado al tenant (resuelto por
     * resolveTheme() antes de llamar acá) para que la IA elija props de
     * bloque (background/style/textColor, etc.) que luzcan bien con ella,
     * en vez de ignorarla — el objetivo es mantener homogeneidad visual
     * entre lo que genera la IA y el resto del sitio/tema del tenant.
     * Los colores nunca se escriben literal en el JSON (los props de color
     * son siempre tokens semánticos del catálogo, ver componentCatalog());
     * esto solo informa el "criterio" de la IA al elegir esos tokens.
     */
    protected function buildPaletteGuidance(Tenant $tenant, ?DesignTheme $theme): string
    {
        if (!$theme) {
            return "\nNo hay un tema visual asignado todavía — usá los valores por defecto de cada componente "
                . 'y evitá abusar de fondos de color fuerte.';
        }

        $colors = $tenant->getEffectiveCssVars()['light'] ?? [];
        $toneLabels = [
            'corporate' => 'corporativo, sobrio',
            'playful'   => 'divertido, desenfadado',
            'minimal'   => 'minimalista, con mucho espacio en blanco',
            'elegant'   => 'elegante, sofisticado',
            'bold'      => 'audaz, de alto contraste',
            'warm'      => 'cálido, cercano',
        ];
        $toneLabel = $toneLabels[$theme->tone] ?? $theme->tone;

        $imageMoods = [
            'corporate' => 'profesionales, luz natural, colores neutros — evitá fotos muy saturadas o llamativas',
            'playful'   => 'luminosas, coloridas, con gente sonriendo',
            'minimal'   => 'con poco recargo visual y espacio negativo, tonos neutros',
            'elegant'   => 'con buena iluminación, tonos suaves y sobrios, poco contraste',
            'bold'      => 'con contraste fuerte y colores vivos',
            'warm'      => 'con tonos tierra/dorados, ambiente acogedor',
        ];
        $imageMood = $imageMoods[$theme->tone] ?? 'coherentes con un tono ' . $toneLabel;

        return <<<GUIDANCE

Paleta y tema visual (ya asignado a este sitio, NO lo elegís vos):
- Tema: {$theme->name} (tono {$toneLabel})
- Primario: {$colors['--color-primary']} · Secundario: {$colors['--color-secondary']} · Acento: {$colors['--color-accent']}
- Fondo de página: {$colors['--color-surface-bg']} · Fondo de tarjetas: {$colors['--color-surface-alt']}

Reglas de paleta (para mantener homogeneidad con el resto del sitio):
- NUNCA escribas un color literal (hex/rgb) en ningún prop. Todo prop de tipo color/estilo (background, style, textColor, etc.) es siempre uno de los valores semánticos que ofrece el catálogo para ese componente — esos valores ya están ligados a esta paleta.
- Alterná fondos entre bloques consecutivos (no uses el fondo de marca/acento en más de 2 bloques seguidos), salvo que el tono del tema sea "bold".
- Para bgImageKeywords/imageKeywords: preferí fotos {$imageMood}, para que no choquen con esta paleta.
GUIDANCE;
    }

    // -----------------------------------------------------------------------
    // Arquetipo y tema
    // -----------------------------------------------------------------------

    /**
     * @param string|null $archetypeHandle  Elegido explícitamente por el usuario en el panel
     *                                       de generación. Si es null (o no existe/inactivo),
     *                                       se elige el más afín al userPrompt (o al azar si
     *                                       no hay ninguna señal de coincidencia).
     */
    protected function resolveArchetype(Tenant $tenant, ?string $archetypeHandle = null, string $userPrompt = ''): array
    {
        $niche = $this->resolveNiche($tenant->niche_type);

        $default = [
            'handle' => 'default',
            'blocks' => array_map(fn ($type) => ['type' => $type, 'instruction' => ''], ['Hero', 'FeatureGrid', 'Testimonials', 'CTASection']),
            'recommended_tones'  => [],
            'tone_instructions'  => $niche->getToneInstructions(),
            'target_audience'    => $niche->getTargetAudience(),
        ];

        if ($archetypeHandle) {
            $chosen = Archetype::active()->where('handle', $archetypeHandle)->first();
            if ($chosen) {
                return $this->archetypeToArray($chosen, $tenant);
            }
        }

        $candidates = Archetype::active()->forNiche($tenant->niche_type)->get();

        if ($candidates->isEmpty()) {
            return $default;
        }

        return $this->archetypeToArray($this->pickBestArchetype($candidates, $userPrompt), $tenant);
    }

    /**
     * NicheManager::make() ya cae a 'generic' si el handle no está
     * registrado, así que esto nunca lanza para un niche_type desconocido.
     */
    protected function resolveNiche(?string $nicheType): \Aero\Sites\Classes\Niches\NicheManagerInterface
    {
        return app(NicheManager::class)->make($nicheType ?: 'generic');
    }

    /**
     * Elige el arquetipo cuyo name/description/target_audience comparte más
     * palabras clave con el prompt del usuario, en vez de uno al azar — así
     * dos negocios con descripciones similares tienden a caer en el mismo
     * arquetipo. Si ningún candidato tiene solapamiento, cae a azar.
     */
    protected function pickBestArchetype($candidates, string $userPrompt): Archetype
    {
        $promptKeywords = $this->extractKeywords($userPrompt);

        if (empty($promptKeywords)) {
            return $candidates->random();
        }

        $scored = $candidates->map(function (Archetype $archetype) use ($promptKeywords) {
            $archetypeText = implode(' ', [
                $archetype->name,
                $archetype->description ?? '',
                $archetype->target_audience ?? '',
            ]);
            $archetypeKeywords = $this->extractKeywords($archetypeText);
            $score = count(array_intersect($promptKeywords, $archetypeKeywords));

            return ['archetype' => $archetype, 'score' => $score];
        });

        $maxScore = $scored->max('score');

        if (!$maxScore) {
            return $candidates->random();
        }

        return $scored->where('score', $maxScore)->pluck('archetype')->random();
    }

    /**
     * Palabras significativas de un texto en español (minúsculas, sin
     * puntuación, sin stopwords ni palabras de 2 letras o menos).
     */
    protected function extractKeywords(string $text): array
    {
        static $stopwords = [
            'de', 'la', 'el', 'en', 'un', 'una', 'unos', 'unas', 'y', 'a', 'que', 'los', 'las',
            'con', 'para', 'del', 'al', 'es', 'somos', 'ofrecemos', 'nuestro', 'nuestra',
            'tenemos', 'por', 'se', 'su', 'sus', 'muy', 'más', 'como', 'sobre', 'este', 'esta',
            'todo', 'toda', 'todos', 'todas', 'ser', 'está', 'están', 'sin', 'también',
        ];

        $text = mb_strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text) ?? '';
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $words = array_filter($words, fn ($w) => mb_strlen($w) > 2 && !in_array($w, $stopwords, true));

        return array_values(array_unique($words));
    }

    /**
     * Cuando el arquetipo no define tone_instructions/target_audience propios
     * (típicamente los universales, niche_type null), hereda los del nicho
     * del tenant — así ningún arquetipo llega a la IA sin guía de tono/
     * audiencia por el solo hecho de ser "universal".
     */
    protected function archetypeToArray(Archetype $archetype, Tenant $tenant): array
    {
        $niche = $this->resolveNiche($archetype->niche_type ?: $tenant->niche_type);

        return [
            'handle'             => $archetype->handle,
            'blocks'             => $archetype->blocks_with_instructions,
            'recommended_tones'  => $archetype->recommended_tones ?? [],
            'tone_instructions'  => $archetype->tone_instructions ?: $niche->getToneInstructions(),
            'target_audience'    => $archetype->target_audience ?: $niche->getTargetAudience(),
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

        $catalog = $this->componentCatalog();
        $validTypes = array_keys($catalog);

        foreach ($data['content'] as $i => $block) {
            if (!isset($block['type'])) {
                $errors[] = "Bloque {$i}: falta 'type'.";
                continue;
            }
            if (!in_array($block['type'], $validTypes, true)) {
                $errors[] = "Bloque {$i}: tipo '{$block['type']}' no válido. Tipos permitidos: " . implode(', ', $validTypes) . '.';
                continue;
            }
            if (!isset($block['props']) || !is_array($block['props'])) {
                $errors[] = "Bloque {$i}: 'props' debe ser un objeto.";
                continue;
            }

            // Campos requeridos según el catálogo (los marcados "|null" son opcionales).
            $expectedFields = $catalog[$block['type']]['fields'] ?? [];
            foreach ($expectedFields as $field => $hint) {
                $optional = str_ends_with($hint, '|null');
                if (!$optional && !array_key_exists($field, $block['props'])) {
                    $errors[] = "Bloque {$i} ({$block['type']}): falta el campo requerido '{$field}'.";
                }
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

    /**
     * Trunca (con "…") campos de texto que excedan un largo razonable para
     * su rol visual, para que un título/subtítulo demasiado largo no rompa
     * el layout en vez de dispararse silenciosamente roto en producción.
     * No toca campos de contenido HTML (content/answer) — ahí confiamos en
     * max_tokens y no queremos cortar HTML a la mitad de una etiqueta.
     */
    protected function enforceFieldLimits(array $data): array
    {
        static $limits = [
            'title' => 80, 'heading' => 80, 'subtitle' => 220, 'body' => 300,
            'ctaLabel' => 30, 'buttonLabel' => 30, 'text' => 200, 'caption' => 150,
            'alt' => 150, 'description' => 200, 'quote' => 280, 'author' => 60,
            'role' => 60, 'question' => 150, 'label' => 40, 'value' => 20,
        ];

        array_walk_recursive($data['content'], function (&$value, $key) use ($limits) {
            if (!is_string($value) || !isset($limits[$key])) {
                return;
            }
            $max = $limits[$key];
            if (mb_strlen($value) > $max) {
                $value = mb_substr($value, 0, $max - 1) . '…';
            }
        });

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

            if ($type === 'Hero') {
                $bgKeywords = $props['bgImageKeywords'] ?? '';
                unset($props['bgImageKeywords']);
                if ($bgKeywords) {
                    $resolved = $this->images->resolve((string) $bgKeywords);
                    $props['bgImage'] = $resolved['url'];
                }

                $imgKeywords = $props['imageKeywords'] ?? '';
                unset($props['imageKeywords']);
                if ($imgKeywords) {
                    $resolved = $this->images->resolve((string) $imgKeywords);
                    $props['image'] = $resolved['url'];
                }
            }

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
     * @param int|null    $connectorId  Modelo de IA elegido explícitamente por el usuario
     *                                  (Aero\Connector\Models\Connector, categoría "ai");
     *                                  null = usa el proveedor único configurado en
     *                                  Aero.AiFields (comportamiento de siempre).
     * @return array{html: string, puck_data: array, log_id: int}|null
     */
    /**
     * Llama al modelo de IA y normaliza la respuesta al shape que espera el
     * loop de generate() (`content`/`usage.input_tokens`/`usage.output_tokens`/
     * `provider`/`model`), sin importar si viene de Aero.AiFields (proveedor
     * único global) o de un Aero\Connector\Models\Connector elegido por el
     * usuario (varios modelos a elección — "Modelo de IA" en el panel).
     */
    protected function callAi(string $systemPrompt, string $userPrompt, ?int $connectorId): array
    {
        if (!$connectorId || !class_exists(\Aero\Connector\Models\Connector::class)) {
            return $this->ai->complete($userPrompt, [
                'system'      => $systemPrompt,
                'provider'    => null,  // usa el default de AiFields
                // Modelos "reasoning" (ej. deepseek-v4-flash) gastan tokens
                // de razonamiento del mismo presupuesto de max_tokens antes
                // de escribir el JSON final — con 8000 a veces se quedaban
                // sin espacio a mitad del JSON (respuesta inválida).
                'max_tokens'  => 16000,
                'temperature' => 0.5,
            ]);
        }

        $connector = \Aero\Connector\Models\Connector::find($connectorId);
        if (!$connector || !$connector->is_enabled) {
            throw new Exception('El modelo de IA elegido ya no está disponible.');
        }

        $response = app(\Aero\Connector\Classes\ConnectorClient::class)->send($connector, [
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'max_tokens' => 16000,
            'timeout'    => 150,
        ]);

        if (!$response->successful) {
            throw new Exception($response->error ?: "Fallo del proveedor (HTTP {$response->statusCode}).");
        }

        $content = \Aero\Connector\Classes\AiResponseText::extract($connector, $response);
        if (!$content) {
            throw new Exception('El modelo respondió sin contenido de texto.');
        }

        $body = is_array($response->body) ? $response->body : (json_decode((string) $response->rawBody, true) ?? []);
        $usage = $body['usage'] ?? [];

        return [
            'content'  => $content,
            'usage'    => [
                'input_tokens'  => $usage['prompt_tokens'] ?? $usage['input_tokens'] ?? 0,
                'output_tokens' => $usage['completion_tokens'] ?? $usage['output_tokens'] ?? 0,
            ],
            'provider' => $connector->provider_hint ?: $connector->type,
            'model'    => $connector->ai_model,
        ];
    }

    public function generate(Tenant $tenant, string $userPrompt, int $retries = 2, ?AiGeneration $log = null, ?string $archetypeHandle = null, ?int $connectorId = null): ?array
    {
        $archetype = $this->resolveArchetype($tenant, $archetypeHandle, $userPrompt);
        $theme     = $this->resolveTheme($tenant, $archetype);

        $systemPrompt = $this->buildSystemPrompt($tenant, $archetype, $theme);
        $combinedPrompt = "DESCRIPCIÓN ADICIONAL DEL USUARIO:\n{$userPrompt}\n\nGenera el JSON de la página.";

        $lastError = null;
        $attempts  = 0;

        for ($i = 0; $i <= $retries; $i++) {
            if ($i > 0) {
                // Retry: añadir feedback de error al prompt
                $combinedPrompt .= "\n\nCORRECCIÓN REQUERIDA (intento anterior falló con estos errores de validación):\n- " . implode("\n- ", $lastError) . "\n\nVuelve a generar SOLO el JSON corregido.";
            }

            try {
                $result = $this->callAi($systemPrompt, $combinedPrompt, $connectorId);

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
                // bloque y duplica entradas al guardar), truncar campos
                // demasiado largos y resolver imágenes reales antes de renderizar.
                $data = $this->injectBlockIds($data);
                $data = $this->enforceFieldLimits($data);
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
