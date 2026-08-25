<?php namespace Aero\Sites\Classes\Ai;

/**
 * Renderizador PHP de Puck data -> HTML.
 *
 * Convierte el JSON de `puck_data` (almacenado en Page) a HTML estático con
 * las mismas clases Tailwind que el editor visual (React).
 *
 * IMPORTANTE: Mantener sincronizado con
 * plugins/aero/sites/assets/puck-editor/src/components.jsx
 * (estructura HTML + clases). Usado para materializar `content` en el flujo
 * de generación con IA sin depender de Node/proc_open (no disponible en
 * hosts compartidos).
 */
class PuckHtmlRenderer
{
    /**
     * Renderiza el array de Puck data completo.
     *
     * @param array $puckData Ej: ['content' => [['type'=>'Hero','props'=>[...]]], 'root'=>['props'=>[]]]
     * @return string
     */
    public function render(array $puckData): string
    {
        return $this->renderBlocks($puckData['content'] ?? []);
    }

    /**
     * Renderiza una lista de bloques (top-level `content` o el array de un
     * campo `slot` anidado dentro de Grid/Flex — misma forma: [{type,props}]).
     */
    protected function renderBlocks(array $blocks): string
    {
        $html = '';

        foreach ($blocks as $block) {
            $type  = $block['type'] ?? '';
            $props = is_array($block['props'] ?? null) ? $block['props'] : [];
            $method = 'render' . ucfirst($type);

            if (method_exists($this, $method)) {
                $html .= $this->$method($props);
            }
        }

        return $html;
    }

    // -------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------

    protected function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    protected function attr($props, string $key, $default = '')
    {
        return $props[$key] ?? $default;
    }

    protected function raw($props, string $key, string $default = ''): string
    {
        return (string) ($props[$key] ?? $default);
    }

    /**
     * Ícono/emoji para FAQ/Tabs — sin SVG Tabler en PHP (limitación
     * preexistente, ver renderHero/renderFeatureGrid), solo texto/emoji.
     */
    /**
     * Espejo de <PickedIcon> en components.jsx: si `icon` es un ícono
     * Tabler (prefijo "tabler:"), renderiza el SVG inline con los mismos
     * paths que @tabler/icons-react (ver TablerIconPaths.php); si no,
     * asume emoji/texto libre y lo muestra en un <span> con font-size.
     */
    protected function pickedIconHtml(string $icon, string $className, int $size = 24): string
    {
        if ($icon === '') {
            return '';
        }

        if (str_starts_with($icon, 'tabler:')) {
            $name = substr($icon, 7);
            $paths = $this->tablerIconPaths()[$name] ?? null;
            if ($paths !== null) {
                return '<svg class="' . $className . '" width="' . $size . '" height="' . $size
                    . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" '
                    . 'stroke-linecap="round" stroke-linejoin="round">' . $paths . '</svg>';
            }
        }

        return '<span class="' . $className . '" style="font-size:' . $size . 'px;line-height:1;">' . $this->e($icon) . '</span>';
    }

    protected function tablerIconPaths(): array
    {
        static $paths = null;
        if ($paths === null) {
            $paths = require __DIR__ . '/TablerIconPaths.php';
        }
        return $paths;
    }

    /**
     * "Texto | URL" (una por línea) -> [['label'=>..,'url'=>..]] — mismo
     * patrón que parsePlanFeatures(), reutilizado para enlaces de FAQ.
     */
    protected function parseFaqLinks(string $text): array
    {
        $lines = array_filter(array_map('trim', explode("\n", $text)));
        $out = [];
        foreach ($lines as $line) {
            $parts = array_map('trim', explode('|', $line, 2));
            $label = $parts[0] ?? '';
            $url = $parts[1] ?? '';
            $out[] = ['label' => $label !== '' ? $label : $url, 'url' => $url !== '' ? $url : '#'];
        }
        return $out;
    }

    protected function faqLinksHtml(string $links, string $className = ''): string
    {
        $items = $this->parseFaqLinks($links);
        if (empty($items)) {
            return '';
        }
        $html = '';
        foreach ($items as $l) {
            $html .= '<a href="' . $this->e($l['url']) . '" class="text-sm font-semibold text-brand-primary hover:opacity-80 inline-flex items-center gap-1">'
                . $this->e($l['label']) . ' <span aria-hidden="true">→</span></a>';
        }
        return '<div class="flex flex-wrap gap-3 mt-3 ' . $className . '">' . $html . '</div>';
    }

    /**
     * Clase del botón para los bloques de alto impacto (Hero/Banner). Debe
     * reflejar exactamente heroButtonClasses() de components.jsx. El fallback
     * 'brand' cuando la prop no existe preserva el aspecto de páginas
     * guardadas antes de que existiera este campo.
     */
    protected function backgroundButtonClasses(string $background): string
    {
        return $background === 'brand'
            ? 'bg-white text-brand-primary-dark'
            : 'bg-brand-primary text-white';
    }

    /**
     * Espejo exacto de heroSecondaryButtonClasses() en components.jsx.
     */
    protected function backgroundSecondaryButtonClasses(string $background): string
    {
        return $background === 'brand'
            ? 'border-white text-white'
            : 'border-brand-primary text-brand-primary';
    }

    /**
     * Espejo exacto de HeroButtons en components.jsx: hasta 2 botones
     * (Botón 1 sólido, Botón 2 outline), en fila en desktop / apiladas en
     * mobile.
     */
    protected function heroButtons(array $p, string $justify): string
    {
        $ctaLabel  = $this->attr($p, 'ctaLabel', '');
        $ctaUrl    = $this->attr($p, 'ctaUrl', '');
        $cta2Label = $this->attr($p, 'cta2Label', '');
        $cta2Url   = $this->attr($p, 'cta2Url', '');
        $background = $this->attr($p, 'background', 'brand');

        $btn1 = ($ctaLabel && $ctaUrl)
            ? '<a href="' . $this->e($ctaUrl) . '" class="inline-block font-semibold px-8 py-4 rounded-brand hover:opacity-90 transition-opacity ' . $this->backgroundButtonClasses($background) . '">' . $this->e($ctaLabel) . '</a>'
            : '';
        $btn2 = ($cta2Label && $cta2Url)
            ? '<a href="' . $this->e($cta2Url) . '" class="inline-block font-semibold px-8 py-4 rounded-brand border-2 hover:opacity-90 transition-opacity ' . $this->backgroundSecondaryButtonClasses($background) . '">' . $this->e($cta2Label) . '</a>'
            : '';

        if ($btn1 === '' && $btn2 === '') {
            return '';
        }

        return '<div class="flex flex-col sm:flex-row gap-4 ' . $justify . '">' . $btn1 . $btn2 . '</div>';
    }

    protected function isValidHex($hex): bool
    {
        return is_string($hex) && preg_match('/^#[0-9a-fA-F]{6}$/', $hex) === 1;
    }

    /**
     * Espejo exacto de resolveSectionStyle() en components.jsx: el color
     * picker (customBgColor/customTextColor) es el toggle de "personalizado"
     * — en cuanto tiene un hex válido gana sobre el preset, sin necesidad de
     * un radio "Personalizado" aparte. Devuelve clases Tailwind fijas
     * (comportamiento idéntico al actual) salvo que haya un hex válido, en
     * cuyo caso va en `styleAttr` (para el atributo style).
     */
    protected function resolveSectionStyle(string $background, string $autoTextClass, array $opts = []): array
    {
        $customBgColor   = $opts['customBgColor'] ?? '';
        $textColor       = $opts['textColor'] ?? '';
        $customTextColor = $opts['customTextColor'] ?? '';

        $classes = [];
        $styleParts = [];

        if ($this->isValidHex($customBgColor)) {
            $styleParts[] = 'background-color:' . $customBgColor;
        } elseif ($background === 'brand') {
            $classes[] = 'bg-brand-primary-dark';
        } elseif ($background === 'surface') {
            $classes[] = 'bg-surface-alt';
        }

        if ($this->isValidHex($customTextColor)) {
            $styleParts[] = 'color:' . $customTextColor;
        } elseif ($textColor === 'light') {
            $classes[] = 'text-white';
        } elseif ($textColor === 'dark') {
            $classes[] = 'text-ink';
        } else {
            $classes[] = $autoTextClass;
        }

        return [
            'class'     => trim(implode(' ', array_filter($classes))),
            'styleAttr' => $styleParts ? implode(';', $styleParts) . ';' : '',
        ];
    }

    // -------------------------------------------------------------------
    // LAYOUT — contenedores estructurales (Grid/Flex admiten anidar
    // cualquier otro bloque vía `slot`; Space es un espaciador simple).
    // -------------------------------------------------------------------

    protected function renderGrid(array $p): string
    {
        $columns = $this->attr($p, 'columns', '2');
        $gap     = $this->attr($p, 'gap', 'medium');
        $content = is_array($p['content'] ?? null) ? $p['content'] : [];

        $colMap = ['2' => 'md:grid-cols-2', '3' => 'md:grid-cols-3', '4' => 'md:grid-cols-4'];
        $colClass = $colMap[$columns] ?? 'md:grid-cols-2';
        $gapMap = ['small' => 'gap-2', 'medium' => 'gap-4', 'large' => 'gap-8'];
        $gapClass = $gapMap[$gap] ?? 'gap-4';

        return '<section class="reveal py-8 px-4">'
            . '<div class="max-w-6xl mx-auto">'
            . '<div class="grid grid-cols-1 ' . $colClass . ' ' . $gapClass . '">' . $this->renderBlocks($content) . '</div>'
            . '</div></section>';
    }

    protected function renderFlex(array $p): string
    {
        $direction = $this->attr($p, 'direction', 'row');
        $wrap      = $this->attr($p, 'wrap', 'yes');
        $justify   = $this->attr($p, 'justify', 'start');
        $align     = $this->attr($p, 'align', 'stretch');
        $gap       = $this->attr($p, 'gap', 'medium');
        $content   = is_array($p['content'] ?? null) ? $p['content'] : [];

        $dirClass = $direction === 'column' ? 'flex-col' : 'flex-row';
        $wrapClass = $wrap === 'yes' ? 'flex-wrap' : 'flex-nowrap';
        $justifyMap = ['start' => 'justify-start', 'center' => 'justify-center', 'end' => 'justify-end', 'between' => 'justify-between'];
        $justifyClass = $justifyMap[$justify] ?? 'justify-start';
        $alignMap = ['start' => 'items-start', 'center' => 'items-center', 'end' => 'items-end', 'stretch' => 'items-stretch'];
        $alignClass = $alignMap[$align] ?? 'items-stretch';
        $gapMap = ['small' => 'gap-2', 'medium' => 'gap-4', 'large' => 'gap-8'];
        $gapClass = $gapMap[$gap] ?? 'gap-4';

        return '<section class="reveal py-8 px-4">'
            . '<div class="max-w-6xl mx-auto">'
            . '<div class="flex ' . $dirClass . ' ' . $wrapClass . ' ' . $justifyClass . ' ' . $alignClass . ' ' . $gapClass . '">' . $this->renderBlocks($content) . '</div>'
            . '</div></section>';
    }

    protected function renderSpace(array $p): string
    {
        $height = $this->attr($p, 'height', 'h-8');

        return '<div class="' . $this->e($height) . '"></div>';
    }

    // -------------------------------------------------------------------
    // BLOQUES — secciones principales
    // -------------------------------------------------------------------

    protected function renderHero(array $p): string
    {
        $title       = $this->e($this->attr($p, 'title', 'Bienvenido a nuestro sitio'));
        $subtitle    = $this->e($this->attr($p, 'subtitle', 'Descubre todo lo que tenemos para ofrecerte.'));
        $description = $this->attr($p, 'description', '');
        $bgImage     = $this->attr($p, 'bgImage', '');
        $image       = $this->attr($p, 'image', '');
        // Fallback 'centrado' preserva el layout de páginas guardadas antes
        // de que existiera este campo (ver plan, punto de regresión).
        $variant    = $this->attr($p, 'variant', 'centrado');
        $background = $this->attr($p, 'background', 'brand');
        $customBgColor   = $this->attr($p, 'customBgColor', '');
        $textColor       = $this->attr($p, 'textColor', 'auto');
        $customTextColor = $this->attr($p, 'customTextColor', '');

        $descHtml = $description !== '' ? '<p class="text-lg mb-10 opacity-75 leading-relaxed">' . $this->e($description) . '</p>' : '';

        $autoText = ($background === 'brand' || $bgImage) ? 'text-white' : 'text-ink';
        $resolved = $this->resolveSectionStyle($background, $autoText, [
            'customBgColor' => $customBgColor, 'textColor' => $textColor, 'customTextColor' => $customTextColor,
        ]);
        $bgImageStyle = $bgImage ? 'background-image:url(\'' . $this->e($bgImage) . '\');' : '';
        $styleAttr = trim($bgImageStyle . $resolved['styleAttr']);
        $style = $styleAttr ? ' style="' . $styleAttr . '"' : '';
        $overlay = $bgImage ? '<div class="absolute inset-0 bg-black/50"></div>' : '';

        // ---- imagen-derecha / imagen-izquierda: split de texto + imagen ---
        if ($variant === 'imagen-derecha' || $variant === 'imagen-izquierda') {
            $descHtmlSplit = $description !== '' ? '<p class="text-lg mb-8 opacity-75 leading-relaxed">' . $this->e($description) . '</p>' : '';
            $textCol = '<div class="text-left">'
                . '<h1 class="font-heading text-4xl md:text-6xl font-bold mb-6 leading-tight">' . $title . '</h1>'
                . '<p class="text-xl mb-6 opacity-90 leading-relaxed">' . $subtitle . '</p>'
                . $descHtmlSplit
                . $this->heroButtons($p, 'justify-start')
                . '</div>';
            $imageCol = $image
                ? '<div class="rounded-2xl overflow-hidden shadow-md aspect-video"><img src="' . $this->e($image) . '" alt="" class="w-full h-full object-cover"></div>'
                : '<div></div>';
            $cols = $variant === 'imagen-derecha' ? ($textCol . $imageCol) : ($imageCol . $textCol);

            return '<section class="' . trim('reveal relative ' . $resolved['class'] . ' py-20 px-4 bg-cover bg-center') . '"' . $style . '>'
                . $overlay
                . '<div class="relative max-w-6xl mx-auto grid md:grid-cols-2 gap-8 items-center">' . $cols . '</div>'
                . '</section>';
        }

        // ---- fondo-completo: bgImage full-bleed, alto impacto -------------
        if ($variant === 'fondo-completo') {
            return '<section class="' . trim('reveal relative ' . $resolved['class'] . ' py-32 px-4 text-center bg-cover bg-center') . '"' . $style . '>'
                . $overlay
                . '<div class="relative max-w-4xl mx-auto">'
                . '<h1 class="font-heading text-4xl md:text-6xl font-bold mb-6 leading-tight">' . $title . '</h1>'
                . '<p class="text-xl md:text-2xl mb-6 opacity-90 leading-relaxed">' . $subtitle . '</p>'
                . $descHtml
                . $this->heroButtons($p, 'justify-center')
                . '</div></section>';
        }

        // ---- minimal: solo texto, sin imágenes, mucho espacio -------------
        if ($variant === 'minimal') {
            $minimalStyle = $resolved['styleAttr'] ? ' style="' . $resolved['styleAttr'] . '"' : '';
            return '<section class="' . trim('reveal relative ' . $resolved['class'] . ' py-32 px-4 text-center') . '"' . $minimalStyle . '>'
                . '<div class="relative max-w-3xl mx-auto">'
                . '<h1 class="font-heading text-5xl md:text-6xl font-bold mb-8 leading-tight">' . $title . '</h1>'
                . '<p class="text-xl mb-10 opacity-75 leading-relaxed">' . $subtitle . '</p>'
                . $this->heroButtons($p, 'justify-center')
                . '</div></section>';
        }

        // ---- centrado: layout clásico (default) ----------------------------
        return '<section class="' . trim('reveal relative ' . $resolved['class'] . ' py-24 px-4 text-center bg-cover bg-center') . '"' . $style . '>'
            . $overlay
            . '<div class="relative max-w-4xl mx-auto">'
            . '<h1 class="font-heading text-4xl md:text-6xl font-bold mb-6 leading-tight">' . $title . '</h1>'
            . '<p class="text-xl md:text-2xl mb-6 opacity-90 leading-relaxed">' . $subtitle . '</p>'
            . $descHtml
            . $this->heroButtons($p, 'justify-center')
            . '</div></section>';
    }

    protected function renderTextBlock(array $p): string
    {
        $background = $this->attr($p, 'background', 'surface');
        $alignment = $this->attr($p, 'alignment', 'text-left');
        $heading   = $this->attr($p, 'heading', '');
        $content   = $this->raw($p, 'content', '');
        $customBgColor   = $this->attr($p, 'customBgColor', '');
        $textColor       = $this->attr($p, 'textColor', 'auto');
        $customTextColor = $this->attr($p, 'customTextColor', '');

        $resolved = $this->resolveSectionStyle($background, '', [
            'customBgColor' => $customBgColor, 'textColor' => $textColor, 'customTextColor' => $customTextColor,
        ]);
        $style = $resolved['styleAttr'] ? ' style="' . $resolved['styleAttr'] . '"' : '';
        $textOverride = $this->isValidHex($customTextColor);

        $head = $heading
            ? '<h2 class="font-heading2 text-3xl font-bold mb-6' . ($textOverride ? '' : ' text-brand-text') . '">' . $this->e($heading) . '</h2>'
            : '';

        return '<section class="' . trim('reveal py-14 px-4 ' . $resolved['class']) . '"' . $style . '>'
            . '<div class="max-w-4xl mx-auto ' . $this->e($alignment) . '">'
            . $head
            . '<div class="prose prose-lg dark:prose-invert max-w-none' . ($textOverride ? '' : ' text-ink-muted') . '">' . $content . '</div>'
            . '</div></section>';
    }

    /**
     * Espejo exacto de FeatureGrid en components.jsx. 'tarjetas' (default)
     * preserva el markup previo a `variant` — contenido guardado antes de
     * que existiera este campo sigue viéndose igual.
     */
    protected function renderFeatureGrid(array $p): string
    {
        $title       = $this->attr($p, 'title', '');
        $subtitle    = $this->attr($p, 'subtitle', '');
        $description = $this->attr($p, 'description', '');
        $ctaLabel    = $this->attr($p, 'ctaLabel', '');
        $ctaUrl      = $this->attr($p, 'ctaUrl', '');
        $image       = $this->attr($p, 'image', '');
        $features    = $this->attr($p, 'features', []);
        $columns     = $this->attr($p, 'columns', '3');
        $colMap      = ['2' => 'md:grid-cols-2', '3' => 'md:grid-cols-3', '4' => 'md:grid-cols-4'];
        $colClass    = $colMap[$columns] ?? 'md:grid-cols-3';
        $variant     = $this->attr($p, 'variant', 'tarjetas');
        $background  = $this->attr($p, 'background', '');
        $customBgColor   = $this->attr($p, 'customBgColor', '');
        $textColor       = $this->attr($p, 'textColor', 'auto');
        $customTextColor = $this->attr($p, 'customTextColor', '');

        $resolved = $this->resolveSectionStyle($background, '', [
            'customBgColor' => $customBgColor, 'textColor' => $textColor, 'customTextColor' => $customTextColor,
        ]);
        $style = $resolved['styleAttr'] ? ' style="' . $resolved['styleAttr'] . '"' : '';
        $textOverride = $this->isValidHex($customTextColor);

        $titleHtml = $title
            ? '<h2 class="font-heading2 text-3xl font-bold text-center mb-12' . ($textOverride ? '' : ' text-ink') . '">' . $this->e($title) . '</h2>'
            : '';

        $button = ($ctaLabel && $ctaUrl)
            ? '<a href="' . $this->e($ctaUrl) . '" class="inline-block font-semibold px-8 py-4 rounded-brand hover:opacity-90 transition-opacity ' . $this->backgroundButtonClasses($background) . '">' . $this->e($ctaLabel) . '</a>'
            : '';

        // ---- lista: ícono a la izquierda, título+descripción a la derecha
        if ($variant === 'lista') {
            $head = ($title ? '<h2 class="font-heading2 text-3xl font-bold mb-4' . ($textOverride ? '' : ' text-ink') . '">' . $this->e($title) . '</h2>' : '')
                . ($subtitle ? '<p class="text-lg mb-10 leading-relaxed' . ($textOverride ? '' : ' text-ink-muted') . '">' . $this->e($subtitle) . '</p>' : '');
            $rows = '';
            foreach ($features as $f) {
                $f = is_array($f) ? $f : [];
                $rows .= '<div class="flex items-start gap-4">'
                    . '<div class="flex-shrink-0 w-12 h-12 rounded-brand bg-brand-primary/10 flex items-center justify-center">' . $this->pickedIconHtml($this->attr($f, 'icon', ''), 'leading-none', 24) . '</div>'
                    . '<div><h3 class="font-heading2 text-lg font-bold mb-1' . ($textOverride ? '' : ' text-ink') . '">' . $this->e($this->attr($f, 'title', '')) . '</h3>'
                    . '<p' . ($textOverride ? '' : ' class="text-ink-muted leading-relaxed"') . '>' . $this->e($this->attr($f, 'description', '')) . '</p></div>'
                    . '</div>';
            }
            return '<section class="' . trim('reveal py-16 px-4 ' . $resolved['class']) . '"' . $style . '>'
                . '<div class="max-w-3xl mx-auto">' . $head . '<div class="flex flex-col gap-8">' . $rows . '</div></div></section>';
        }

        // ---- numeradas: pasos 01/02/03 en vez de ícono ---------------------
        if ($variant === 'numeradas') {
            $head = ($title ? '<h2 class="font-heading2 text-3xl font-bold text-center mb-4' . ($textOverride ? '' : ' text-ink') . '">' . $this->e($title) . '</h2>' : '')
                . ($subtitle ? '<p class="text-lg text-center max-w-2xl mx-auto mb-12' . ($textOverride ? '' : ' text-ink-muted') . '">' . $this->e($subtitle) . '</p>' : '');
            $cards = '';
            foreach (array_values($features) as $i => $f) {
                $f = is_array($f) ? $f : [];
                $num = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
                $cards .= '<div><div class="font-heading2 text-4xl font-bold text-brand-primary mb-3">' . $num . '</div>'
                    . '<h3 class="font-heading2 text-xl font-bold mb-3' . ($textOverride ? '' : ' text-ink') . '">' . $this->e($this->attr($f, 'title', '')) . '</h3>'
                    . '<p' . ($textOverride ? '' : ' class="text-ink-muted leading-relaxed"') . '>' . $this->e($this->attr($f, 'description', '')) . '</p></div>';
            }
            return '<section class="' . trim('reveal py-16 px-4 ' . $resolved['class']) . '"' . $style . '>'
                . '<div class="max-w-6xl mx-auto">' . $head . '<div class="grid grid-cols-1 ' . $colClass . ' gap-8">' . $cards . '</div></div></section>';
        }

        // ---- imagen-lateral: título+subtítulo+lista compacta+botón, imagen -
        if ($variant === 'imagen-lateral') {
            $head = ($title ? '<h2 class="font-heading2 text-3xl font-bold mb-4">' . $this->e($title) . '</h2>' : '')
                . ($subtitle ? '<p class="text-lg opacity-75 leading-relaxed mb-8">' . $this->e($subtitle) . '</p>' : '');
            $rows = '';
            foreach ($features as $f) {
                $f = is_array($f) ? $f : [];
                $rows .= '<div class="flex items-center gap-3">' . $this->pickedIconHtml($this->attr($f, 'icon', ''), 'leading-none', 24)
                    . '<span class="font-semibold">' . $this->e($this->attr($f, 'title', '')) . '</span></div>';
            }
            $imageCol = $image
                ? '<div class="rounded-2xl overflow-hidden shadow-md aspect-video"><img src="' . $this->e($image) . '" alt="" class="w-full h-full object-cover"></div>'
                : '<div></div>';
            return '<section class="' . trim('reveal py-20 px-4 ' . $resolved['class']) . '"' . $style . '>'
                . '<div class="max-w-6xl mx-auto grid md:grid-cols-2 gap-12 items-center">'
                . '<div>' . $head . '<div class="flex flex-col gap-5 mb-8">' . $rows . '</div>' . $button . '</div>'
                . $imageCol
                . '</div></section>';
        }

        // ---- destacado: encabezado grande + grid minimal de íconos ---------
        if ($variant === 'destacado') {
            $sectionClass = trim('reveal py-20 px-4 text-center ' . ($resolved['class'] ?: 'bg-brand-primary-dark text-white'));
            $head = ($title ? '<h2 class="font-heading2 text-3xl md:text-4xl font-bold mb-4">' . $this->e($title) . '</h2>' : '')
                . ($subtitle ? '<p class="text-xl opacity-90 leading-relaxed mb-4">' . $this->e($subtitle) . '</p>' : '')
                . ($description !== '' ? '<p class="opacity-75 leading-relaxed mb-8">' . $this->e($description) . '</p>' : '')
                . $button;
            $cards = '';
            foreach ($features as $f) {
                $f = is_array($f) ? $f : [];
                $cards .= '<div>' . $this->pickedIconHtml($this->attr($f, 'icon', ''), 'leading-none block mb-3', 32)
                    . '<h3 class="font-heading2 text-lg font-bold mb-2">' . $this->e($this->attr($f, 'title', '')) . '</h3>'
                    . '<p class="opacity-75 leading-relaxed">' . $this->e($this->attr($f, 'description', '')) . '</p></div>';
            }
            return '<section class="' . $sectionClass . '"' . $style . '>'
                . '<div class="max-w-3xl mx-auto mb-14">' . $head . '</div>'
                . '<div class="max-w-6xl mx-auto grid grid-cols-1 ' . $colClass . ' gap-8 text-left">' . $cards . '</div>'
                . '</section>';
        }

        // ---- tarjetas: layout clásico (default) -----------------------------
        $cards = '';
        foreach ($features as $f) {
            $f = is_array($f) ? $f : [];
            $cards .= '<div class="bg-surface-alt p-8 rounded-2xl shadow-sm text-center transition-all duration-300 hover:-translate-y-1 hover:shadow-lg">'
                . '<div class="mb-4">' . $this->pickedIconHtml($this->attr($f, 'icon', ''), 'leading-none', 48) . '</div>'
                . '<h3 class="font-heading2 text-xl font-bold mb-3 text-ink">' . $this->e($this->attr($f, 'title', '')) . '</h3>'
                . '<p class="text-ink-muted leading-relaxed">' . $this->e($this->attr($f, 'description', '')) . '</p>'
                . '</div>';
        }

        return '<section class="' . trim('reveal py-16 px-4 ' . $resolved['class']) . '"' . $style . '>'
            . '<div class="max-w-6xl mx-auto">'
            . $titleHtml
            . '<div class="grid grid-cols-1 ' . $colClass . ' gap-8">' . $cards . '</div>'
            . '</div></section>';
    }

    protected function renderImageBlock(array $p): string
    {
        $url     = $this->attr($p, 'imageUrl', '');
        $alt     = $this->e($this->attr($p, 'alt', 'Imagen'));
        $caption = $this->attr($p, 'caption', '');
        $size    = $this->attr($p, 'size', 'full');

        $figure = $size === 'centered' ? 'max-w-3xl mx-auto' : 'w-full';
        $fig    = $caption
            ? '<figcaption class="text-center text-ink-muted text-sm mt-3 italic">' . $this->e($caption) . '</figcaption>'
            : '';

        return '<div class="reveal py-8 px-4">'
            . '<figure class="' . $figure . '">'
            . '<img src="' . $this->e($url) . '" alt="' . $alt . '" class="w-full rounded-xl object-cover">'
            . $fig
            . '</figure></div>';
    }

    /**
     * Espejo exacto de CTASection en components.jsx. 'clasico' (default)
     * preserva el markup previo a `variant` — contenido guardado antes de
     * que existiera este campo sigue viéndose igual.
     */
    protected function renderCTASection(array $p): string
    {
        $solid   = $this->attr($p, 'style', 'solid') !== 'outline';
        $buttonClasses = $solid ? 'bg-white text-brand-primary' : 'bg-brand-primary text-white';

        $heading  = $this->e($this->attr($p, 'heading', '¿Listo para comenzar?'));
        $subtitle = $this->attr($p, 'subtitle', '');
        $body     = $this->e($this->attr($p, 'body', 'Contáctanos hoy y descubre cómo podemos ayudarte.'));
        $btn      = $this->attr($p, 'buttonLabel', '');
        $btnUrl   = $this->attr($p, 'buttonUrl', '');
        $cta2Label = $this->attr($p, 'cta2Label', '');
        $cta2Url   = $this->attr($p, 'cta2Url', '');
        $icon      = $this->attr($p, 'icon', '🚀');
        $image     = $this->attr($p, 'image', '');
        $variant   = $this->attr($p, 'variant', 'clasico');
        $background = $this->attr($p, 'background', '');
        $customBgColor   = $this->attr($p, 'customBgColor', '');
        $textColor       = $this->attr($p, 'textColor', 'auto');
        $customTextColor = $this->attr($p, 'customTextColor', '');

        $autoText = $solid ? 'text-white' : 'text-brand-text';
        $hasOverride = $background || $this->isValidHex($customBgColor) || ($textColor && $textColor !== 'auto');
        $styleAttrValue = '';

        if ($hasOverride) {
            $resolved = $this->resolveSectionStyle($background, $autoText, [
                'customBgColor' => $customBgColor, 'textColor' => $textColor, 'customTextColor' => $customTextColor,
            ]);
            $section = trim($resolved['class'] . (!$solid ? ' border-2 border-brand-primary' : ''));
            $styleAttrValue = $resolved['styleAttr'];
        } else {
            $section = $solid ? 'bg-brand-primary text-white' : 'bg-brand-bg text-brand-text border-2 border-brand-primary';
        }
        $style = $styleAttrValue ? ' style="' . $styleAttrValue . '"' : '';

        $button1 = ($btn && $btnUrl)
            ? '<a href="' . $this->e($btnUrl) . '" class="inline-block font-semibold px-8 py-4 rounded-brand transition-opacity hover:opacity-90 ' . $buttonClasses . '">' . $this->e($btn) . '</a>'
            : '';
        $button2Classes = $solid ? 'border-white text-white' : 'border-brand-primary text-brand-primary';
        $button2 = ($cta2Label && $cta2Url)
            ? '<a href="' . $this->e($cta2Url) . '" class="inline-block font-semibold px-8 py-4 rounded-brand border-2 transition-opacity hover:opacity-90 ' . $button2Classes . '">' . $this->e($cta2Label) . '</a>'
            : '';

        // ---- doble-boton: título+subtítulo+descripción+2 botones -----------
        if ($variant === 'doble-boton') {
            $subtitleHtml = $subtitle !== '' ? '<p class="text-xl mb-4 opacity-90 leading-relaxed">' . $this->e($subtitle) . '</p>' : '';
            return '<section class="reveal ' . $section . ' py-20 px-4 text-center"' . $style . '>'
                . '<div class="max-w-2xl mx-auto"><h2 class="font-heading2 text-3xl font-bold mb-4">' . $heading . '</h2>'
                . $subtitleHtml
                . '<p class="text-lg mb-10 opacity-90 leading-relaxed">' . $body . '</p>'
                . '<div class="flex flex-col sm:flex-row gap-4 justify-center">' . $button1 . $button2 . '</div>'
                . '</div></section>';
        }

        // ---- con-icono: ícono grande arriba, título+descripción+botón 1 ----
        if ($variant === 'con-icono') {
            return '<section class="reveal ' . $section . ' py-20 px-4 text-center"' . $style . '>'
                . '<div class="max-w-2xl mx-auto">' . $this->pickedIconHtml($icon, 'leading-none block mx-auto mb-6', 48)
                . '<h2 class="font-heading2 text-3xl font-bold mb-4">' . $heading . '</h2>'
                . '<p class="text-lg mb-10 opacity-90 leading-relaxed">' . $body . '</p>'
                . $button1
                . '</div></section>';
        }

        // ---- imagen-lateral: texto+2 botones a un lado, imagen al otro -----
        if ($variant === 'imagen-lateral') {
            $subtitleHtml = $subtitle !== '' ? '<p class="text-xl mb-4 opacity-90 leading-relaxed">' . $this->e($subtitle) . '</p>' : '';
            $imageCol = $image
                ? '<div class="rounded-2xl overflow-hidden shadow-md aspect-video"><img src="' . $this->e($image) . '" alt="" class="w-full h-full object-cover"></div>'
                : '<div></div>';
            return '<section class="reveal ' . $section . ' py-20 px-4"' . $style . '>'
                . '<div class="max-w-6xl mx-auto grid md:grid-cols-2 gap-12 items-center">'
                . '<div class="text-left"><h2 class="font-heading2 text-3xl font-bold mb-4">' . $heading . '</h2>'
                . $subtitleHtml
                . '<p class="text-lg mb-8 opacity-90 leading-relaxed">' . $body . '</p>'
                . '<div class="flex flex-col sm:flex-row gap-4">' . $button1 . $button2 . '</div></div>'
                . $imageCol
                . '</div></section>';
        }

        // ---- franja-minimal: ícono+título en línea a la izquierda, botón ---
        if ($variant === 'franja-minimal') {
            return '<section class="reveal ' . $section . ' py-8 px-4"' . $style . '>'
                . '<div class="max-w-6xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">'
                . '<div class="flex items-center gap-3">' . $this->pickedIconHtml($icon, 'leading-none', 28)
                . '<h2 class="font-heading2 text-xl font-bold">' . $heading . '</h2></div>'
                . $button1
                . '</div></section>';
        }

        // ---- clasico: layout original (default) -----------------------------
        return '<section class="reveal ' . $section . ' py-20 px-4 text-center"' . $style . '>'
            . '<div class="max-w-2xl mx-auto">'
            . '<h2 class="font-heading2 text-3xl font-bold mb-4">' . $heading . '</h2>'
            . '<p class="text-lg mb-10 opacity-90 leading-relaxed">' . $body . '</p>'
            . $button1
            . '</div></section>';
    }

    /**
     * Convierte el textarea "una característica por línea" de cada plan en
     * un array — espejo exacto de parsePlanFeatures() en components.jsx.
     */
    protected function parsePlanFeatures($features): array
    {
        $lines = explode("\n", (string) $features);
        $lines = array_map('trim', $lines);
        return array_values(array_filter($lines, fn ($l) => $l !== ''));
    }

    protected function planFeatureListHtml($features, string $extraClass = ''): string
    {
        $items = $this->parsePlanFeatures($features);
        if (empty($items)) {
            return '';
        }
        $lis = '';
        foreach ($items as $f) {
            $lis .= '<li class="flex items-start gap-2"><span class="text-brand-primary font-bold flex-shrink-0">&check;</span><span>' . $this->e($f) . '</span></li>';
        }
        return '<ul class="' . trim('flex flex-col gap-3 text-left ' . $extraClass) . '">' . $lis . '</ul>';
    }

    /**
     * `forceOnDark` para tarjetas con fondo sólido oscuro propio (ej. el
     * plan destacado en 'tres-planes-contraste'); el resto sigue el
     * contraste de la sección (backgroundButtonClasses), igual criterio que
     * Hero/CTASection — espejo de planButton() en components.jsx.
     */
    protected function planButtonHtml(array $plan, string $background, bool $forceOnDark = false): string
    {
        $ctaLabel = $this->attr($plan, 'ctaLabel', '');
        $ctaUrl   = $this->attr($plan, 'ctaUrl', '');
        if (!$ctaLabel || !$ctaUrl) {
            return '';
        }
        $classes = $forceOnDark ? 'bg-white text-brand-primary' : $this->backgroundButtonClasses($background);
        return '<a href="' . $this->e($ctaUrl) . '" class="block text-center font-semibold px-6 py-3 rounded-brand transition-opacity hover:opacity-90 ' . $classes . '">' . $this->e($ctaLabel) . '</a>';
    }

    /**
     * Espejo exacto de Pricing en components.jsx. 3 variantes de 3 planes,
     * 1 de 2 planes y 1 de 1 plan/producto único.
     */
    protected function renderPricing(array $p): string
    {
        $title       = $this->attr($p, 'title', '');
        $subtitle    = $this->attr($p, 'subtitle', '');
        $description = $this->attr($p, 'description', '');
        $plans       = $this->attr($p, 'plans', []);
        $plans       = is_array($plans) ? array_values(array_filter($plans, 'is_array')) : [];
        $variant     = $this->attr($p, 'variant', 'tres-planes');
        $background  = $this->attr($p, 'background', '');
        $customBgColor   = $this->attr($p, 'customBgColor', '');
        $textColor       = $this->attr($p, 'textColor', 'auto');
        $customTextColor = $this->attr($p, 'customTextColor', '');

        $resolved = $this->resolveSectionStyle($background, '', [
            'customBgColor' => $customBgColor, 'textColor' => $textColor, 'customTextColor' => $customTextColor,
        ]);
        $style = $resolved['styleAttr'] ? ' style="' . $resolved['styleAttr'] . '"' : '';
        $textOverride = $this->isValidHex($customTextColor);

        $head = ($title ? '<h2 class="font-heading2 text-3xl font-bold text-center mb-4' . ($textOverride ? '' : ' text-ink') . '">' . $this->e($title) . '</h2>' : '')
            . ($subtitle ? '<p class="text-lg text-center max-w-2xl mx-auto mb-4' . ($textOverride ? '' : ' text-ink-muted') . '">' . $this->e($subtitle) . '</p>' : '')
            . ($description !== '' ? '<p class="text-center max-w-2xl mx-auto mb-12' . ($textOverride ? '' : ' text-ink-muted') . '">' . $this->e($description) . '</p>' : '');

        // ---- tres-planes-contraste: plan destacado con fondo sólido invertido
        if ($variant === 'tres-planes-contraste') {
            $cards = '';
            foreach (array_slice($plans, 0, 3) as $plan) {
                $hl = $this->attr($plan, 'highlighted', 'no') === 'yes';
                $cardClass = $hl ? 'bg-brand-primary-dark text-white shadow-lg md:scale-105' : 'bg-surface-alt text-ink border border-surface-border';
                $descHtml = $this->attr($plan, 'description', '') !== '' ? '<p class="mb-6 ' . ($hl ? 'opacity-90' : 'text-ink-muted') . '">' . $this->e($this->attr($plan, 'description', '')) . '</p>' : '';
                $period = $this->attr($plan, 'period', '');
                $cards .= '<div class="p-8 rounded-2xl ' . $cardClass . '">'
                    . '<h3 class="font-heading2 text-xl font-bold mb-2">' . $this->e($this->attr($plan, 'name', '')) . '</h3>'
                    . $descHtml
                    . '<div class="mb-6"><span class="font-heading2 text-4xl font-bold">' . $this->e($this->attr($plan, 'price', '')) . '</span>'
                    . ($period !== '' ? '<span class="opacity-75">' . $this->e($period) . '</span>' : '') . '</div>'
                    . $this->planFeatureListHtml($this->attr($plan, 'features', ''), 'mb-8' . ($hl ? '' : ' text-ink-muted'))
                    . $this->planButtonHtml($plan, $background, $hl)
                    . '</div>';
            }
            return '<section class="' . trim('reveal py-16 px-4 ' . $resolved['class']) . '"' . $style . '>'
                . '<div class="max-w-6xl mx-auto">' . $head . '<div class="grid grid-cols-1 md:grid-cols-3 gap-8 items-center">' . $cards . '</div></div></section>';
        }

        // ---- tres-planes-tabla: minimal, columnas separadas por divisores --
        if ($variant === 'tres-planes-tabla') {
            $cols = '';
            foreach (array_slice($plans, 0, 3) as $plan) {
                $hl = $this->attr($plan, 'highlighted', 'no') === 'yes';
                $period = $this->attr($plan, 'period', '');
                $cols .= '<div class="text-center px-8 py-6">'
                    . ($hl ? '<span class="inline-block text-xs font-semibold uppercase tracking-wide text-brand-primary mb-2">Recomendado</span>' : '')
                    . '<h3 class="font-heading2 text-xl font-bold mb-2' . ($textOverride ? '' : ' text-ink') . '">' . $this->e($this->attr($plan, 'name', '')) . '</h3>'
                    . '<div class="mb-4"><span class="font-heading2 text-4xl font-bold text-brand-primary">' . $this->e($this->attr($plan, 'price', '')) . '</span>'
                    . ($period !== '' ? '<span' . ($textOverride ? '' : ' class="text-ink-muted"') . '>' . $this->e($period) . '</span>' : '') . '</div>'
                    . $this->planFeatureListHtml($this->attr($plan, 'features', ''), 'mb-8 justify-center' . ($textOverride ? '' : ' text-ink-muted'))
                    . $this->planButtonHtml($plan, $background)
                    . '</div>';
            }
            return '<section class="' . trim('reveal py-16 px-4 ' . $resolved['class']) . '"' . $style . '>'
                . '<div class="max-w-6xl mx-auto">' . $head . '<div class="grid grid-cols-1 md:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-surface-border">' . $cols . '</div></div></section>';
        }

        // ---- dos-planes: 2 columnas, tarjetas más anchas --------------------
        if ($variant === 'dos-planes') {
            $cards = '';
            foreach (array_slice($plans, 0, 2) as $plan) {
                $hl = $this->attr($plan, 'highlighted', 'no') === 'yes';
                $cardClass = $hl ? 'bg-surface-alt border-2 border-brand-primary shadow-lg' : 'bg-surface-alt border border-surface-border';
                $descHtml = $this->attr($plan, 'description', '') !== '' ? '<p class="mb-6' . ($textOverride ? '' : ' text-ink-muted') . '">' . $this->e($this->attr($plan, 'description', '')) . '</p>' : '';
                $period = $this->attr($plan, 'period', '');
                $cards .= '<div class="p-10 rounded-2xl ' . $cardClass . '">'
                    . '<h3 class="font-heading2 text-2xl font-bold mb-2' . ($textOverride ? '' : ' text-ink') . '">' . $this->e($this->attr($plan, 'name', '')) . '</h3>'
                    . $descHtml
                    . '<div class="mb-6"><span class="font-heading2 text-5xl font-bold text-brand-primary">' . $this->e($this->attr($plan, 'price', '')) . '</span>'
                    . ($period !== '' ? '<span' . ($textOverride ? '' : ' class="text-ink-muted"') . '>' . $this->e($period) . '</span>' : '') . '</div>'
                    . $this->planFeatureListHtml($this->attr($plan, 'features', ''), 'mb-8' . ($textOverride ? '' : ' text-ink-muted'))
                    . $this->planButtonHtml($plan, $background)
                    . '</div>';
            }
            return '<section class="' . trim('reveal py-16 px-4 ' . $resolved['class']) . '"' . $style . '>'
                . '<div class="max-w-4xl mx-auto">' . $head . '<div class="grid grid-cols-1 md:grid-cols-2 gap-8">' . $cards . '</div></div></section>';
        }

        // ---- un-plan: producto/servicio único, caja centrada ----------------
        if ($variant === 'un-plan') {
            $plan = $plans[0] ?? [];
            $descHtml = $this->attr($plan, 'description', '') !== '' ? '<p class="mb-6' . ($textOverride ? '' : ' text-ink-muted') . '">' . $this->e($this->attr($plan, 'description', '')) . '</p>' : '';
            $period = $this->attr($plan, 'period', '');
            $icon = $this->pickedIconHtml($this->attr($plan, 'icon', ''), 'leading-none block mx-auto mb-4', 40);
            return '<section class="' . trim('reveal py-20 px-4 text-center ' . $resolved['class']) . '"' . $style . '>'
                . '<div class="max-w-md mx-auto bg-surface-alt border border-surface-border rounded-2xl p-10 shadow-md">'
                . $icon
                . '<h3 class="font-heading2 text-2xl font-bold mb-2' . ($textOverride ? '' : ' text-ink') . '">' . $this->e($this->attr($plan, 'name', '')) . '</h3>'
                . $descHtml
                . '<div class="mb-8"><span class="font-heading2 text-5xl font-bold text-brand-primary">' . $this->e($this->attr($plan, 'price', '')) . '</span>'
                . ($period !== '' ? '<span' . ($textOverride ? '' : ' class="text-ink-muted"') . '>' . $this->e($period) . '</span>' : '') . '</div>'
                . $this->planFeatureListHtml($this->attr($plan, 'features', ''), 'mb-8' . ($textOverride ? '' : ' text-ink-muted'))
                . $this->planButtonHtml($plan, $background)
                . '</div></section>';
        }

        // ---- tres-planes: layout clásico en tarjetas (default) --------------
        $cards = '';
        foreach (array_slice($plans, 0, 3) as $plan) {
            $hl = $this->attr($plan, 'highlighted', 'no') === 'yes';
            $cardClass = 'bg-surface-alt ' . ($hl ? 'border-2 border-brand-primary shadow-lg md:scale-105' : 'border border-surface-border');
            $badge = $hl ? '<span class="absolute -top-3 left-1/2 -translate-x-1/2 bg-brand-primary text-white text-xs font-semibold uppercase tracking-wide px-3 py-1 rounded-full">Recomendado</span>' : '';
            $descHtml = $this->attr($plan, 'description', '') !== '' ? '<p class="mb-6' . ($textOverride ? '' : ' text-ink-muted') . '">' . $this->e($this->attr($plan, 'description', '')) . '</p>' : '';
            $period = $this->attr($plan, 'period', '');
            $cards .= '<div class="relative p-8 rounded-2xl ' . $cardClass . '">'
                . $badge
                . '<h3 class="font-heading2 text-xl font-bold mb-2' . ($textOverride ? '' : ' text-ink') . '">' . $this->e($this->attr($plan, 'name', '')) . '</h3>'
                . $descHtml
                . '<div class="mb-6"><span class="font-heading2 text-4xl font-bold text-brand-primary">' . $this->e($this->attr($plan, 'price', '')) . '</span>'
                . ($period !== '' ? '<span' . ($textOverride ? '' : ' class="text-ink-muted"') . '>' . $this->e($period) . '</span>' : '') . '</div>'
                . $this->planFeatureListHtml($this->attr($plan, 'features', ''), 'mb-8' . ($textOverride ? '' : ' text-ink-muted'))
                . $this->planButtonHtml($plan, $background)
                . '</div>';
        }

        return '<section class="' . trim('reveal py-16 px-4 ' . $resolved['class']) . '"' . $style . '>'
            . '<div class="max-w-6xl mx-auto">' . $head . '<div class="grid grid-cols-1 md:grid-cols-3 gap-8 items-center">' . $cards . '</div></div></section>';
    }

    protected function renderDivider(array $p): string
    {
        $height  = $this->attr($p, 'height', 'h-8');
        $showLine = $this->attr($p, 'showLine', 'no');
        $line    = $showLine === 'yes' ? '<hr class="w-full border-surface-border">' : '';

        return '<div class="' . $this->e($height) . ' flex items-center px-8">' . $line . '</div>';
    }

    // -------------------------------------------------------------------
    // COMPONENTES DE CONTENIDO — Pines
    // -------------------------------------------------------------------

    protected function renderBanner(array $p): string
    {
        $title = $this->e($this->attr($p, 'title', 'Título del anuncio'));
        $body  = $this->e($this->attr($p, 'body', 'Describe la promoción o mensaje importante de forma breve.'));
        $align = $this->attr($p, 'align', 'text-center');
        $btn   = $this->attr($p, 'buttonLabel', '');
        $btnUrl = $this->attr($p, 'buttonUrl', '');
        $background = $this->attr($p, 'background', 'brand');
        $customBgColor   = $this->attr($p, 'customBgColor', '');
        $textColor       = $this->attr($p, 'textColor', 'auto');
        $customTextColor = $this->attr($p, 'customTextColor', '');

        $btnHtml = '';
        if ($btn && $btnUrl) {
            $btnHtml = '<a href="' . $this->e($btnUrl) . '" class="inline-block font-semibold px-8 py-4 rounded-brand hover:opacity-90 transition-opacity ' . $this->backgroundButtonClasses($background) . '">' . $this->e($btn) . '</a>';
        }

        $autoText = $background === 'brand' ? 'text-white' : 'text-ink';
        $resolved = $this->resolveSectionStyle($background, $autoText, [
            'customBgColor' => $customBgColor, 'textColor' => $textColor, 'customTextColor' => $customTextColor,
        ]);
        $style = $resolved['styleAttr'] ? ' style="' . $resolved['styleAttr'] . '"' : '';

        return '<section class="' . trim('reveal py-16 px-4 ' . $resolved['class']) . '"' . $style . '>'
            . '<div class="max-w-4xl mx-auto ' . $this->e($align) . '">'
            . '<h2 class="font-heading2 text-3xl font-bold mb-4">' . $title . '</h2>'
            . '<p class="text-lg mb-8 opacity-90 leading-relaxed">' . $body . '</p>'
            . $btnHtml
            . '</div></section>';
    }

    protected function renderBadge(array $p): string
    {
        $styles = [
            'brand' => 'bg-brand-primary text-white',
            'green' => 'bg-green-100 text-green-800',
            'red'   => 'bg-red-100 text-red-800',
            'gray'  => 'bg-gray-100 text-gray-800',
        ];
        $variant = $this->attr($p, 'variant', 'brand');
        $cls     = $styles[$variant] ?? $styles['brand'];

        return '<span class="inline-flex items-center px-3 py-1 rounded-brand text-sm font-semibold ' . $cls . '">'
            . $this->e($this->attr($p, 'text', 'Nuevo'))
            . '</span>';
    }

    protected function renderFAQ(array $p): string
    {
        $title = $this->attr($p, 'title', '');
        $subtitle = $this->attr($p, 'subtitle', '');
        $description = $this->attr($p, 'description', '');
        $items = $this->attr($p, 'items', []);
        $variant = $this->attr($p, 'variant', 'acordeon-clasico');
        $background = $this->attr($p, 'background', '');
        $customBgColor   = $this->attr($p, 'customBgColor', '');
        $textColor       = $this->attr($p, 'textColor', 'auto');
        $customTextColor = $this->attr($p, 'customTextColor', '');

        $resolved = $this->resolveSectionStyle($background, '', [
            'customBgColor' => $customBgColor, 'textColor' => $textColor, 'customTextColor' => $customTextColor,
        ]);
        $style = $resolved['styleAttr'] ? ' style="' . $resolved['styleAttr'] . '"' : '';
        $textOverride = $this->isValidHex($customTextColor);
        $sectionClass = trim('reveal py-16 px-4 ' . $resolved['class']);
        $hasExtra = $subtitle !== '' || $description !== '';

        $titleHtml = $title !== ''
            ? '<h2 class="font-heading2 text-3xl font-bold text-center ' . ($hasExtra ? 'mb-3' : 'mb-10') . ($textOverride ? '' : ' text-ink') . '">' . $this->e($title) . '</h2>'
            : '';
        $subtitleHtml = $subtitle !== ''
            ? '<p class="text-center font-semibold text-brand-primary ' . ($description !== '' ? 'mb-3' : 'mb-10') . '">' . $this->e($subtitle) . '</p>'
            : '';
        $descriptionHtml = $description !== ''
            ? '<p class="max-w-2xl mx-auto text-center mb-10' . ($textOverride ? '' : ' text-ink-muted') . '">' . $this->e($description) . '</p>'
            : '';
        $head = $titleHtml . $subtitleHtml . $descriptionHtml;

        // ---- acordeon-exclusivo: solo una pregunta abierta a la vez ------
        if ($variant === 'acordeon-exclusivo') {
            $questions = array_map(fn($it) => $this->attr(is_array($it) ? $it : [], 'question', ''), $items);
            $groupName = 'faq-' . substr(md5(implode('|', $questions)), 0, 8);
            $list = '';
            foreach ($items as $i => $item) {
                $item = is_array($item) ? $item : [];
                $icon = $this->pickedIconHtml($this->attr($item, 'icon', ''), 'text-brand-primary flex-shrink-0', 18);
                $list .= '<details name="' . $this->e($groupName) . '" class="group bg-surface-alt rounded-xl border border-surface-border px-6 py-4 open:border-brand-primary">'
                    . '<summary class="flex items-center gap-3 cursor-pointer font-semibold text-ink list-none">'
                    . '<span class="flex-shrink-0 w-8 h-8 rounded-full bg-brand-primary/10 text-brand-primary text-sm font-bold flex items-center justify-center">' . ($i + 1) . '</span>'
                    . $icon
                    . '<span class="flex-1">' . $this->e($this->attr($item, 'question', '')) . '</span>'
                    . '<span class="text-ink-muted group-open:rotate-180 transition-transform flex-shrink-0">▾</span>'
                    . '</summary>'
                    . '<div class="mt-3 ml-11 text-ink-muted prose prose-sm dark:prose-invert max-w-none">' . $this->raw($item, 'answer', '') . '</div>'
                    . '<div class="ml-11">' . $this->faqLinksHtml($this->attr($item, 'links', '')) . '</div>'
                    . '</details>';
            }
            return '<section class="' . $sectionClass . '"' . $style . '>'
                . '<div class="max-w-3xl mx-auto">' . $head . '<div class="space-y-3">' . $list . '</div></div></section>';
        }

        // ---- tarjetas-grid: preguntas siempre visibles en grid -----------
        if ($variant === 'tarjetas-grid') {
            $cards = '';
            foreach ($items as $item) {
                $item = is_array($item) ? $item : [];
                $icon = $this->pickedIconHtml($this->attr($item, 'icon', ''), 'text-brand-primary flex-shrink-0', 28);
                $cards .= '<div class="bg-surface-alt rounded-2xl border border-surface-border p-6">'
                    . '<div class="flex items-center gap-3 mb-3">' . $icon
                    . '<h3 class="font-heading2 text-lg font-bold' . ($textOverride ? '' : ' text-ink') . '">' . $this->e($this->attr($item, 'question', '')) . '</h3>'
                    . '</div>'
                    . '<div class="prose prose-sm dark:prose-invert max-w-none' . ($textOverride ? '' : ' text-ink-muted') . '">' . $this->raw($item, 'answer', '') . '</div>'
                    . $this->faqLinksHtml($this->attr($item, 'links', ''))
                    . '</div>';
            }
            return '<section class="' . $sectionClass . '"' . $style . '>'
                . '<div class="max-w-5xl mx-auto">' . $head . '<div class="grid grid-cols-1 md:grid-cols-2 gap-6">' . $cards . '</div></div></section>';
        }

        // ---- conversacional: burbujas de chat -----------------------------
        if ($variant === 'conversacional') {
            $rows = '';
            foreach ($items as $item) {
                $item = is_array($item) ? $item : [];
                $iconInner = $this->attr($item, 'icon', '') !== ''
                    ? $this->pickedIconHtml($this->attr($item, 'icon', ''), 'text-brand-primary', 18)
                    : '<span class="text-sm">💬</span>';
                $rows .= '<div class="flex flex-col gap-2">'
                    . '<div class="flex justify-end"><div class="max-w-md bg-brand-primary text-white rounded-2xl rounded-br-sm px-5 py-3 font-semibold">' . $this->e($this->attr($item, 'question', '')) . '</div></div>'
                    . '<div class="flex items-start gap-3">'
                    . '<div class="w-9 h-9 rounded-full bg-surface-alt border border-surface-border flex items-center justify-center flex-shrink-0">' . $iconInner . '</div>'
                    . '<div class="max-w-md bg-surface-alt border border-surface-border rounded-2xl rounded-tl-sm px-5 py-3">'
                    . '<div class="prose prose-sm dark:prose-invert max-w-none text-ink-muted">' . $this->raw($item, 'answer', '') . '</div>'
                    . $this->faqLinksHtml($this->attr($item, 'links', ''))
                    . '</div></div></div>';
            }
            return '<section class="' . $sectionClass . '"' . $style . '>'
                . '<div class="max-w-2xl mx-auto">' . $head . '<div class="flex flex-col gap-6">' . $rows . '</div></div></section>';
        }

        // ---- dividido-lateral: panel de intro + acordeón a la derecha ----
        if ($variant === 'dividido-lateral') {
            $sideTitle = $title !== '' ? '<h2 class="font-heading2 text-3xl font-bold mb-4' . ($textOverride ? '' : ' text-ink') . '">' . $this->e($title) . '</h2>' : '';
            $sideSubtitle = $subtitle !== '' ? '<p class="text-lg font-semibold mb-3 text-brand-primary">' . $this->e($subtitle) . '</p>' : '';
            $sideDescription = $description !== '' ? '<p class="mb-6' . ($textOverride ? '' : ' text-ink-muted') . '">' . $this->e($description) . '</p>' : '';
            $list = '';
            foreach ($items as $item) {
                $item = is_array($item) ? $item : [];
                $icon = $this->pickedIconHtml($this->attr($item, 'icon', ''), 'text-brand-primary flex-shrink-0', 18);
                $list .= '<details class="group bg-surface-alt rounded-xl border border-surface-border px-6 py-4">'
                    . '<summary class="flex items-center justify-between gap-3 cursor-pointer font-semibold text-ink list-none">'
                    . '<span class="flex items-center gap-3">' . $icon . '<span>' . $this->e($this->attr($item, 'question', '')) . '</span></span>'
                    . '<span class="text-ink-muted group-open:rotate-180 transition-transform flex-shrink-0">▾</span>'
                    . '</summary>'
                    . '<div class="mt-3 text-ink-muted prose prose-sm dark:prose-invert max-w-none">' . $this->raw($item, 'answer', '') . '</div>'
                    . $this->faqLinksHtml($this->attr($item, 'links', ''))
                    . '</details>';
            }
            return '<section class="' . $sectionClass . '"' . $style . '>'
                . '<div class="max-w-6xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-10">'
                . '<div class="md:col-span-1">' . $sideTitle . $sideSubtitle . $sideDescription . '</div>'
                . '<div class="md:col-span-2 space-y-3">' . $list . '</div>'
                . '</div></section>';
        }

        // ---- acordeon-clasico: layout original (default) -----------------
        $list = '';
        foreach ($items as $item) {
            $item = is_array($item) ? $item : [];
            $icon = $this->pickedIconHtml($this->attr($item, 'icon', ''), 'text-brand-primary flex-shrink-0', 18);
            $list .= '<details class="group bg-surface-alt rounded-xl border border-surface-border px-6 py-4">'
                . '<summary class="flex items-center justify-between gap-3 cursor-pointer font-semibold text-ink list-none">'
                . '<span class="flex items-center gap-3">' . $icon . '<span>' . $this->e($this->attr($item, 'question', '')) . '</span></span>'
                . '<span class="text-ink-muted group-open:rotate-180 transition-transform flex-shrink-0">▾</span>'
                . '</summary>'
                . '<div class="mt-3 text-ink-muted prose prose-sm dark:prose-invert max-w-none">' . $this->raw($item, 'answer', '') . '</div>'
                . $this->faqLinksHtml($this->attr($item, 'links', ''))
                . '</details>';
        }

        return '<section class="' . $sectionClass . '"' . $style . '>'
            . '<div class="max-w-3xl mx-auto">'
            . $head
            . '<div class="space-y-3">' . $list . '</div>'
            . '</div></section>';
    }

    protected function renderTabs(array $p): string
    {
        $title = $this->attr($p, 'title', '');
        $tabs = $this->attr($p, 'tabs', []);
        $variant = $this->attr($p, 'variant', 'clasicas');
        $background = $this->attr($p, 'background', '');
        $customBgColor   = $this->attr($p, 'customBgColor', '');
        $textColor       = $this->attr($p, 'textColor', 'auto');
        $customTextColor = $this->attr($p, 'customTextColor', '');

        $resolved = $this->resolveSectionStyle($background, '', [
            'customBgColor' => $customBgColor, 'textColor' => $textColor, 'customTextColor' => $customTextColor,
        ]);
        $style = $resolved['styleAttr'] ? ' style="' . $resolved['styleAttr'] . '"' : '';
        $textOverride = $this->isValidHex($customTextColor);
        $sectionClass = trim('reveal py-16 px-4 ' . $resolved['class']);

        $labels = array_map(fn($t) => $this->attr(is_array($t) ? $t : [], 'label', ''), $tabs);
        $groupName = 'tabs-' . substr(md5(implode('|', $labels)), 0, 8);
        $maxIndexed = 8;

        $radios = '';
        foreach (array_slice($tabs, 0, $maxIndexed) as $i => $tab) {
            $checked = $i === 0 ? ' checked' : '';
            $radios .= '<input type="radio" name="' . $this->e($groupName) . '" id="' . $this->e($groupName) . '-' . $i . '"' . $checked
                . ' class="sr-only puck-tabs-radio puck-tabs-radio-' . $i . '">';
        }

        $titleHtml = $title !== ''
            ? '<h2 class="font-heading2 text-3xl font-bold mb-8 text-center' . ($textOverride ? '' : ' text-ink') . '">' . $this->e($title) . '</h2>'
            : '';

        $panels = '';
        foreach ($tabs as $i => $tab) {
            $tab = is_array($tab) ? $tab : [];
            $panels .= '<div class="puck-tabs-panel puck-tabs-panel-' . $i . ' text-ink-muted prose prose-lg dark:prose-invert max-w-none">'
                . $this->raw($tab, 'content', '') . '</div>';
        }

        // ---- pildoras: nav en píldoras redondeadas ------------------------
        if ($variant === 'pildoras') {
            $nav = '';
            foreach ($tabs as $i => $tab) {
                $tab = is_array($tab) ? $tab : [];
                $icon = $this->pickedIconHtml($this->attr($tab, 'icon', ''), 'flex-shrink-0', 16);
                $nav .= '<label for="' . $this->e($groupName) . '-' . $i . '" class="puck-tabs-label puck-tabs-label-' . $i . ' inline-flex items-center gap-2 cursor-pointer px-5 py-2 rounded-full text-sm font-semibold border border-surface-border text-ink-muted">'
                    . $icon . '<span>' . $this->e($this->attr($tab, 'label', 'Pestaña')) . '</span></label>';
            }
            return '<section class="' . $sectionClass . '"' . $style . '>'
                . '<div class="max-w-4xl mx-auto puck-tabs" data-variant="pildoras">' . $radios . $titleHtml
                . '<div class="flex flex-wrap gap-2 mb-8 justify-center">' . $nav . '</div>' . $panels . '</div></section>';
        }

        // ---- verticales: nav lateral + contenido a la derecha -------------
        if ($variant === 'verticales') {
            $nav = '';
            foreach ($tabs as $i => $tab) {
                $tab = is_array($tab) ? $tab : [];
                $icon = $this->pickedIconHtml($this->attr($tab, 'icon', ''), 'flex-shrink-0', 18);
                $nav .= '<label for="' . $this->e($groupName) . '-' . $i . '" class="puck-tabs-label puck-tabs-label-' . $i . ' flex items-center gap-3 cursor-pointer px-4 py-3 rounded-xl border border-transparent text-ink-muted font-semibold">'
                    . $icon . '<span>' . $this->e($this->attr($tab, 'label', 'Pestaña')) . '</span></label>';
            }
            return '<section class="' . $sectionClass . '"' . $style . '>'
                . '<div class="max-w-4xl mx-auto puck-tabs" data-variant="verticales">' . $radios . $titleHtml
                . '<div class="grid grid-cols-1 md:grid-cols-4 gap-8">'
                . '<div class="flex md:flex-col gap-2 md:col-span-1">' . $nav . '</div>'
                . '<div class="md:col-span-3">' . $panels . '</div>'
                . '</div></div></section>';
        }

        // ---- tarjetas: nav como mini-tarjetas + panel debajo ---------------
        if ($variant === 'tarjetas') {
            $nav = '';
            foreach ($tabs as $i => $tab) {
                $tab = is_array($tab) ? $tab : [];
                $icon = $this->pickedIconHtml($this->attr($tab, 'icon', ''), 'flex-shrink-0', 24);
                $nav .= '<label for="' . $this->e($groupName) . '-' . $i . '" class="puck-tabs-label puck-tabs-label-' . $i . ' cursor-pointer flex flex-col items-center gap-2 text-center p-4 rounded-2xl border-2 border-surface-border bg-surface-alt">'
                    . $icon . '<span class="text-sm font-semibold">' . $this->e($this->attr($tab, 'label', 'Pestaña')) . '</span></label>';
            }
            return '<section class="' . $sectionClass . '"' . $style . '>'
                . '<div class="max-w-4xl mx-auto puck-tabs" data-variant="tarjetas">' . $radios . $titleHtml
                . '<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">' . $nav . '</div>'
                . '<div class="bg-surface-alt border border-surface-border rounded-2xl p-8">' . $panels . '</div>'
                . '</div></section>';
        }

        // ---- numeradas: nav como pasos numerados -----------------------------
        if ($variant === 'numeradas') {
            $nav = '';
            foreach ($tabs as $i => $tab) {
                $tab = is_array($tab) ? $tab : [];
                $nav .= '<label for="' . $this->e($groupName) . '-' . $i . '" class="puck-tabs-label puck-tabs-label-' . $i . ' cursor-pointer flex flex-col items-center gap-2 text-center">'
                    . '<span class="w-10 h-10 rounded-full bg-surface-alt border-2 border-surface-border text-ink-muted font-bold flex items-center justify-center">' . ($i + 1) . '</span>'
                    . '<span class="text-sm font-semibold text-ink-muted">' . $this->e($this->attr($tab, 'label', 'Pestaña')) . '</span></label>';
            }
            return '<section class="' . $sectionClass . '"' . $style . '>'
                . '<div class="max-w-4xl mx-auto puck-tabs" data-variant="numeradas">' . $radios . $titleHtml
                . '<div class="flex flex-wrap justify-center gap-6 mb-10">' . $nav . '</div>'
                . '<div class="max-w-3xl mx-auto bg-surface-alt border border-surface-border rounded-2xl p-8">' . $panels . '</div>'
                . '</div></section>';
        }

        // ---- clasicas: nav subrayada (default) -------------------------------
        $nav = '';
        foreach ($tabs as $i => $tab) {
            $tab = is_array($tab) ? $tab : [];
            $icon = $this->pickedIconHtml($this->attr($tab, 'icon', ''), 'flex-shrink-0', 16);
            $nav .= '<label for="' . $this->e($groupName) . '-' . $i . '" class="puck-tabs-label puck-tabs-label-' . $i . ' inline-flex items-center gap-2 cursor-pointer px-4 py-2 text-sm font-semibold text-ink-muted border-b-2 border-transparent">'
                . $icon . '<span>' . $this->e($this->attr($tab, 'label', 'Pestaña')) . '</span></label>';
        }

        return '<section class="' . $sectionClass . '"' . $style . '>'
            . '<div class="max-w-4xl mx-auto puck-tabs" data-variant="clasicas">' . $radios . $titleHtml
            . '<div class="border-b border-surface-border mb-6"><div class="flex flex-wrap gap-2">' . $nav . '</div></div>'
            . $panels
            . '</div></section>';
    }

    protected function renderTestimonials(array $p): string
    {
        $title         = $this->attr($p, 'title', '');
        $testimonials  = $this->attr($p, 'testimonials', []);
        $background = $this->attr($p, 'background', '');
        $customBgColor   = $this->attr($p, 'customBgColor', '');
        $textColor       = $this->attr($p, 'textColor', 'auto');
        $customTextColor = $this->attr($p, 'customTextColor', '');

        $resolved = $this->resolveSectionStyle($background, '', [
            'customBgColor' => $customBgColor, 'textColor' => $textColor, 'customTextColor' => $customTextColor,
        ]);
        $style = $resolved['styleAttr'] ? ' style="' . $resolved['styleAttr'] . '"' : '';
        $textOverride = $this->isValidHex($customTextColor);

        $head = $title
            ? '<h2 class="font-heading2 text-3xl font-bold text-center mb-12' . ($textOverride ? '' : ' text-ink') . '">' . $this->e($title) . '</h2>'
            : '';

        $cards = '';
        foreach ($testimonials as $t) {
            $t = is_array($t) ? $t : [];
            $cards .= '<blockquote class="bg-surface-alt p-8 rounded-2xl shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-lg">'
                . '<p class="text-ink-muted text-lg leading-relaxed mb-6">“' . $this->e($this->attr($t, 'quote', '')) . '”</p>'
                . '<footer>'
                . '<div class="font-bold text-ink">' . $this->e($this->attr($t, 'author', '')) . '</div>'
                . '<div class="text-ink-muted text-sm">' . $this->e($this->attr($t, 'role', '')) . '</div>'
                . '</footer></blockquote>';
        }

        return '<section class="' . trim('reveal py-16 px-4 ' . $resolved['class']) . '"' . $style . '>'
            . '<div class="max-w-6xl mx-auto">'
            . $head
            . '<div class="grid grid-cols-1 md:grid-cols-2 gap-8">' . $cards . '</div>'
            . '</div></section>';
    }

    protected function renderGallery(array $p): string
    {
        $title  = $this->attr($p, 'title', '');
        $images = $this->attr($p, 'images', []);
        $variant = $this->attr($p, 'variant', 'grid-uniforme');
        $background = $this->attr($p, 'background', '');
        $customBgColor   = $this->attr($p, 'customBgColor', '');
        $textColor       = $this->attr($p, 'textColor', 'auto');
        $customTextColor = $this->attr($p, 'customTextColor', '');

        $resolved = $this->resolveSectionStyle($background, '', [
            'customBgColor' => $customBgColor, 'textColor' => $textColor, 'customTextColor' => $customTextColor,
        ]);
        $style = $resolved['styleAttr'] ? ' style="' . $resolved['styleAttr'] . '"' : '';
        $textOverride = $this->isValidHex($customTextColor);
        $sectionClass = trim('reveal py-16 px-4 ' . $resolved['class']);

        $head = $title
            ? '<h2 class="font-heading2 text-3xl font-bold text-center mb-12' . ($textOverride ? '' : ' text-ink') . '">' . $this->e($title) . '</h2>'
            : '';

        // ---- masonry: columnas CSS, altura natural por imagen ----------------
        if ($variant === 'masonry') {
            $imgs = '';
            foreach ($images as $img) {
                $img = is_array($img) ? $img : [];
                $caption = $this->attr($img, 'caption', '');
                $overlay = $caption !== ''
                    ? '<div class="absolute inset-0 flex items-end rounded-xl bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity"><span class="text-white text-sm p-3">' . $this->e($caption) . '</span></div>'
                    : '';
                $imgs .= '<div class="mb-4 break-inside-avoid relative group">'
                    . '<img src="' . $this->e($this->attr($img, 'url', '')) . '" alt="' . $this->e($this->attr($img, 'alt', 'Imagen')) . '" class="w-full rounded-xl object-cover">'
                    . $overlay . '</div>';
            }
            return '<section class="' . $sectionClass . '"' . $style . '>'
                . '<div class="max-w-6xl mx-auto">' . $head . '<div class="columns-2 md:columns-3 gap-4">' . $imgs . '</div></div></section>';
        }

        // ---- carrusel: scroll horizontal con snap, sin JS ---------------------
        if ($variant === 'carrusel') {
            $imgs = '';
            foreach ($images as $img) {
                $img = is_array($img) ? $img : [];
                $caption = $this->attr($img, 'caption', '');
                $capHtml = $caption !== '' ? '<p class="text-sm mt-2' . ($textOverride ? '' : ' text-ink-muted') . '">' . $this->e($caption) . '</p>' : '';
                $imgs .= '<div class="snap-center flex-shrink-0 w-72 md:w-80">'
                    . '<img src="' . $this->e($this->attr($img, 'url', '')) . '" alt="' . $this->e($this->attr($img, 'alt', 'Imagen')) . '" class="w-full aspect-square rounded-xl object-cover">'
                    . $capHtml . '</div>';
            }
            return '<section class="reveal py-16 ' . $resolved['class'] . '"' . $style . '>'
                . '<div class="max-w-6xl mx-auto px-4">' . $head . '</div>'
                . '<div class="flex gap-4 overflow-x-auto snap-x snap-mandatory px-4 pb-2">' . $imgs . '</div></section>';
        }

        // ---- lightbox: click para ampliar (CSS puro, :target) -----------------
        if ($variant === 'lightbox') {
            $urls = array_map(fn($im) => $this->attr(is_array($im) ? $im : [], 'url', ''), $images);
            $groupName = 'gallery-' . substr(md5(implode('|', $urls)), 0, 8);
            $thumbs = '';
            $overlays = '';
            foreach ($images as $i => $img) {
                $img = is_array($img) ? $img : [];
                $url = $this->e($this->attr($img, 'url', ''));
                $alt = $this->e($this->attr($img, 'alt', 'Imagen'));
                $caption = $this->attr($img, 'caption', '');
                $anchorId = $groupName . '-' . $i;
                $thumbs .= '<a href="#' . $anchorId . '" class="block cursor-pointer"><img src="' . $url . '" alt="' . $alt . '" class="w-full aspect-square rounded-xl object-cover hover:opacity-90 transition-opacity"></a>';
                $capHtml = $caption !== '' ? '<p class="text-white text-center mt-3">' . $this->e($caption) . '</p>' : '';
                $overlays .= '<div id="' . $anchorId . '" class="puck-lightbox fixed inset-0 z-50 items-center justify-center bg-black/90 p-4">'
                    . '<a href="#" class="absolute inset-0" aria-label="Cerrar"></a>'
                    . '<div class="relative max-w-3xl w-full"><img src="' . $url . '" alt="' . $alt . '" class="w-full rounded-xl">' . $capHtml . '</div>'
                    . '</div>';
            }
            return '<section class="' . $sectionClass . '"' . $style . '>'
                . '<div class="max-w-6xl mx-auto">' . $head
                . '<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">' . $thumbs . '</div>'
                . $overlays . '</div></section>';
        }

        // ---- editorial-alterno: 2 columnas, imagen grande cada 3 -------------
        if ($variant === 'editorial-alterno') {
            $imgs = '';
            foreach ($images as $i => $img) {
                $img = is_array($img) ? $img : [];
                $big = $i % 3 === 0;
                $caption = $this->attr($img, 'caption', '');
                $overlay = $caption !== ''
                    ? '<div class="absolute inset-0 flex items-end rounded-xl bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity"><span class="text-white text-sm p-3">' . $this->e($caption) . '</span></div>'
                    : '';
                $imgs .= '<div class="relative group' . ($big ? ' col-span-2' : '') . '">'
                    . '<img src="' . $this->e($this->attr($img, 'url', '')) . '" alt="' . $this->e($this->attr($img, 'alt', 'Imagen')) . '" class="w-full rounded-xl object-cover ' . ($big ? 'aspect-video' : 'aspect-square') . '">'
                    . $overlay . '</div>';
            }
            return '<section class="' . $sectionClass . '"' . $style . '>'
                . '<div class="max-w-5xl mx-auto">' . $head . '<div class="grid grid-cols-2 gap-4">' . $imgs . '</div></div></section>';
        }

        // ---- grid-uniforme: layout original (default) --------------------------
        $imgs = '';
        foreach ($images as $img) {
            $img = is_array($img) ? $img : [];
            $imgs .= '<img src="' . $this->e($this->attr($img, 'url', '')) . '" alt="' . $this->e($this->attr($img, 'alt', 'Imagen')) . '" class="w-full rounded-xl object-cover">';
        }

        return '<section class="' . $sectionClass . '"' . $style . '>'
            . '<div class="max-w-6xl mx-auto">'
            . $head
            . '<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">' . $imgs . '</div>'
            . '</div></section>';
    }

    protected function renderVideo(array $p): string
    {
        $url     = $this->attr($p, 'url', '');
        $caption = $this->attr($p, 'caption', '');

        $embed = null;
        if ($url) {
            if (preg_match('#(?:youtube\.com/(?:watch\?v=|embed/)|youtu\.be/)([A-Za-z0-9_-]{6,})#', $url, $m)) {
                $embed = 'https://www.youtube.com/embed/' . $m[1];
            } elseif (preg_match('#vimeo\.com/(\d+)#', $url, $m)) {
                $embed = 'https://player.vimeo.com/video/' . $m[1];
            } else {
                $embed = $url;
            }
        }

        // Sin URL configurada no hay nada que mostrarle a un visitante real:
        // este renderer produce el HTML del sitio público (`content`), no el
        // editor — a diferencia de <PickedIcon> en components.jsx, que sí
        // muestra el placeholder "Añade la URL..." porque ahí el que lo ve
        // es el propio editor del tenant.
        if (!$embed) {
            return '';
        }

        $media = '<div class="rounded-2xl overflow-hidden"><div class="w-full aspect-video"><iframe src="' . $this->e($embed) . '" title="' . $this->e($caption ?: 'Video') . '" class="w-full h-full" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div></div>';

        $cap = $caption
            ? '<p class="text-center text-ink-muted text-sm mt-3 italic">' . $this->e($caption) . '</p>'
            : '';

        return '<section class="reveal py-16 px-4">'
            . '<div class="max-w-4xl mx-auto">' . $media . $cap . '</div>'
            . '</section>';
    }

    protected function renderLogoCloud(array $p): string
    {
        $title = $this->attr($p, 'title', '');
        $logos = $this->attr($p, 'logos', []);

        $head = $title
            ? '<h2 class="font-heading2 text-2xl font-bold text-center mb-10 text-ink">' . $this->e($title) . '</h2>'
            : '';

        $imgs = '';
        foreach ($logos as $logo) {
            $logo = is_array($logo) ? $logo : [];
            $imgs .= '<img src="' . $this->e($this->attr($logo, 'url', '')) . '" alt="' . $this->e($this->attr($logo, 'alt', 'Logo')) . '" class="h-12 w-auto opacity-75">';
        }

        return '<section class="reveal py-16 px-4">'
            . '<div class="max-w-6xl mx-auto">'
            . $head
            . '<div class="flex flex-wrap items-center justify-center gap-8">' . $imgs . '</div>'
            . '</div></section>';
    }

    protected function renderStats(array $p): string
    {
        $title = $this->attr($p, 'title', '');
        $stats = $this->attr($p, 'stats', []);
        $variant = $this->attr($p, 'variant', 'tres-columnas');
        $background = $this->attr($p, 'background', 'brand');
        $customBgColor   = $this->attr($p, 'customBgColor', '');
        $textColor       = $this->attr($p, 'textColor', 'auto');
        $customTextColor = $this->attr($p, 'customTextColor', '');

        $autoText = $background === 'brand' ? 'text-white' : 'text-ink';
        $resolved = $this->resolveSectionStyle($background, $autoText, [
            'customBgColor' => $customBgColor, 'textColor' => $textColor, 'customTextColor' => $customTextColor,
        ]);
        $style = $resolved['styleAttr'] ? ' style="' . $resolved['styleAttr'] . '"' : '';
        $sectionClass = trim('reveal py-16 px-4 ' . $resolved['class']);

        $head = $title
            ? '<h2 class="font-heading2 text-3xl font-bold text-center mb-12">' . $this->e($title) . '</h2>'
            : '';

        // ---- con-iconos: ícono arriba de cada número --------------------------
        if ($variant === 'con-iconos') {
            $items = '';
            foreach ($stats as $s) {
                $s = is_array($s) ? $s : [];
                $icon = $this->pickedIconHtml($this->attr($s, 'icon', ''), 'mx-auto mb-3', 32);
                $desc = $this->attr($s, 'description', '');
                $descHtml = $desc !== '' ? '<div class="text-sm opacity-75 mt-1">' . $this->e($desc) . '</div>' : '';
                $items .= '<div>' . $icon
                    . '<div class="text-5xl font-bold mb-2">' . $this->e($this->attr($s, 'value', '')) . '</div>'
                    . '<div class="text-lg opacity-90">' . $this->e($this->attr($s, 'label', '')) . '</div>'
                    . $descHtml . '</div>';
            }
            return '<section class="' . $sectionClass . '"' . $style . '>'
                . '<div class="max-w-6xl mx-auto">' . $head . '<div class="grid grid-cols-1 sm:grid-cols-3 gap-8 text-center">' . $items . '</div></div></section>';
        }

        // ---- franja-destacada: franja sólida de marca, divisores verticales ---
        if ($variant === 'franja-destacada') {
            $items = '';
            foreach ($stats as $s) {
                $s = is_array($s) ? $s : [];
                $desc = $this->attr($s, 'description', '');
                $descHtml = $desc !== '' ? '<div class="text-sm opacity-75 mt-1">' . $this->e($desc) . '</div>' : '';
                $items .= '<div class="py-4 sm:py-0">'
                    . '<div class="text-5xl font-bold mb-2">' . $this->e($this->attr($s, 'value', '')) . '</div>'
                    . '<div class="text-lg opacity-90">' . $this->e($this->attr($s, 'label', '')) . '</div>'
                    . $descHtml . '</div>';
            }
            $titleHtml = $title !== '' ? '<h2 class="font-heading2 text-3xl font-bold text-center mb-12">' . $this->e($title) . '</h2>' : '';
            return '<section class="reveal py-16 px-4 bg-brand-primary text-white">'
                . '<div class="max-w-6xl mx-auto">' . $titleHtml
                . '<div class="grid grid-cols-1 sm:grid-cols-3 divide-y sm:divide-y-0 sm:divide-x divide-white/20 text-center">' . $items . '</div></div></section>';
        }

        // ---- contador-destacado: un stat grande + el resto más chico ----------
        if ($variant === 'contador-destacado') {
            $list = array_values($stats);
            $first = $list[0] ?? null;
            $rest = array_slice($list, 1);

            $firstHtml = '';
            if ($first) {
                $first = is_array($first) ? $first : [];
                $icon = $this->pickedIconHtml($this->attr($first, 'icon', ''), 'mx-auto mb-3 text-brand-primary', 40);
                $desc = $this->attr($first, 'description', '');
                $descHtml = $desc !== '' ? '<div class="text-sm opacity-75 mt-1">' . $this->e($desc) . '</div>' : '';
                $firstHtml = '<div class="mb-10">' . $icon
                    . '<div class="text-7xl font-bold mb-2 text-brand-primary">' . $this->e($this->attr($first, 'value', '')) . '</div>'
                    . '<div class="text-xl opacity-90">' . $this->e($this->attr($first, 'label', '')) . '</div>'
                    . $descHtml . '</div>';
            }

            $restHtml = '';
            foreach ($rest as $s) {
                $s = is_array($s) ? $s : [];
                $restHtml .= '<div>'
                    . '<div class="text-3xl font-bold mb-1">' . $this->e($this->attr($s, 'value', '')) . '</div>'
                    . '<div class="text-sm opacity-75">' . $this->e($this->attr($s, 'label', '')) . '</div>'
                    . '</div>';
            }
            $restBlock = $restHtml !== ''
                ? '<div class="grid grid-cols-2 sm:grid-cols-3 gap-8 pt-8 border-t border-surface-border">' . $restHtml . '</div>'
                : '';

            return '<section class="' . $sectionClass . '"' . $style . '>'
                . '<div class="max-w-5xl mx-auto text-center">' . $head . $firstHtml . $restBlock . '</div></section>';
        }

        // ---- tarjetas-elevadas: cada estadística en su propia tarjeta ---------
        if ($variant === 'tarjetas-elevadas') {
            $items = '';
            foreach ($stats as $s) {
                $s = is_array($s) ? $s : [];
                $icon = $this->pickedIconHtml($this->attr($s, 'icon', ''), 'mx-auto mb-3 text-brand-primary', 32);
                $desc = $this->attr($s, 'description', '');
                $descHtml = $desc !== '' ? '<div class="text-sm opacity-75 mt-1">' . $this->e($desc) . '</div>' : '';
                $items .= '<div class="bg-surface-alt border border-surface-border rounded-2xl shadow-md p-8 text-center">' . $icon
                    . '<div class="text-4xl font-bold mb-2 text-brand-primary">' . $this->e($this->attr($s, 'value', '')) . '</div>'
                    . '<div class="text-lg font-semibold">' . $this->e($this->attr($s, 'label', '')) . '</div>'
                    . $descHtml . '</div>';
            }
            return '<section class="' . $sectionClass . '"' . $style . '>'
                . '<div class="max-w-6xl mx-auto">' . $head . '<div class="grid grid-cols-1 sm:grid-cols-3 gap-6">' . $items . '</div></div></section>';
        }

        // ---- tres-columnas: layout original (default) --------------------------
        $items = '';
        foreach ($stats as $s) {
            $s = is_array($s) ? $s : [];
            $items .= '<div>'
                . '<div class="text-5xl font-bold mb-2">' . $this->e($this->attr($s, 'value', '')) . '</div>'
                . '<div class="text-lg opacity-90">' . $this->e($this->attr($s, 'label', '')) . '</div>'
                . '</div>';
        }

        return '<section class="' . $sectionClass . '"' . $style . '>'
            . '<div class="max-w-6xl mx-auto">'
            . $head
            . '<div class="grid grid-cols-1 sm:grid-cols-3 gap-8 text-center">' . $items . '</div>'
            . '</div></section>';
    }

    protected function renderRating(array $p): string
    {
        $score = (int) ($p['score'] ?? 5);
        $text  = $this->attr($p, 'text', '');
        $score = max(0, min(5, $score));

        $stars = '<span class="text-brand-accent">' . str_repeat('★', $score) . '</span>'
            . '<span class="text-surface-border">' . str_repeat('★', 5 - $score) . '</span>';
        $txt   = $text ? '<p class="text-ink-muted">' . $this->e($text) . '</p>' : '';

        return '<div class="py-8 px-4 text-center">'
            . '<div class="text-3xl mb-2">' . $stars . '</div>'
            . $txt
            . '</div>';
    }
}
