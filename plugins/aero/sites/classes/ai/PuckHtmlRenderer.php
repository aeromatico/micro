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
        $blocks = $puckData['content'] ?? [];
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

    // -------------------------------------------------------------------
    // BLOQUES — secciones principales
    // -------------------------------------------------------------------

    protected function renderHero(array $p): string
    {
        $title    = $this->e($this->attr($p, 'title', 'Bienvenido a nuestro sitio'));
        $subtitle = $this->e($this->attr($p, 'subtitle', 'Descubre todo lo que tenemos para ofrecerte.'));
        $cta      = $this->attr($p, 'ctaLabel', '');
        $ctaUrl   = $this->attr($p, 'ctaUrl', '');

        $btn = '';
        if ($cta && $ctaUrl) {
            $btn = '<a href="' . $this->e($ctaUrl) . '" class="inline-block bg-white text-brand-primary-dark font-semibold px-8 py-4 rounded-brand hover:opacity-90 transition-opacity">' . $this->e($cta) . '</a>';
        }

        return '<section class="bg-brand-primary-dark text-white py-24 px-4 text-center">'
            . '<div class="max-w-4xl mx-auto">'
            . '<h1 class="font-heading text-4xl md:text-6xl font-bold mb-6 leading-tight">' . $title . '</h1>'
            . '<p class="text-xl md:text-2xl mb-10 opacity-90 leading-relaxed">' . $subtitle . '</p>'
            . $btn
            . '</div></section>';
    }

    protected function renderTextBlock(array $p): string
    {
        $bg        = ($this->attr($p, 'bgWhite', 'white') === 'gray') ? 'bg-brand-bg' : 'bg-white';
        $alignment = $this->attr($p, 'alignment', 'text-left');
        $heading   = $this->attr($p, 'heading', '');
        $content   = $this->raw($p, 'content', '');

        $head = $heading
            ? '<h2 class="font-heading text-3xl font-bold mb-6 text-brand-text">' . $this->e($heading) . '</h2>'
            : '';

        return '<section class="py-14 px-4 ' . $bg . '">'
            . '<div class="max-w-4xl mx-auto ' . $this->e($alignment) . '">'
            . $head
            . '<div class="prose prose-lg max-w-none text-gray-700">' . $content . '</div>'
            . '</div></section>';
    }

    protected function renderFeatureGrid(array $p): string
    {
        $title    = $this->attr($p, 'title', '');
        $features = $this->attr($p, 'features', []);
        $columns  = $this->attr($p, 'columns', '3');
        $colMap   = ['2' => 'md:grid-cols-2', '3' => 'md:grid-cols-3', '4' => 'md:grid-cols-4'];
        $colClass = $colMap[$columns] ?? 'md:grid-cols-3';

        $head = $title
            ? '<h2 class="font-heading text-3xl font-bold text-center mb-12 text-brand-text">' . $this->e($title) . '</h2>'
            : '';

        $cards = '';
        foreach ($features as $f) {
            $f = is_array($f) ? $f : [];
            $cards .= '<div class="bg-white p-8 rounded-2xl shadow-sm text-center">'
                . '<div class="text-5xl mb-4">' . $this->e($this->attr($f, 'icon', '')) . '</div>'
                . '<h3 class="font-heading text-xl font-bold mb-3 text-brand-text">' . $this->e($this->attr($f, 'title', '')) . '</h3>'
                . '<p class="text-gray-600 leading-relaxed">' . $this->e($this->attr($f, 'description', '')) . '</p>'
                . '</div>';
        }

        return '<section class="py-16 px-4 bg-brand-bg">'
            . '<div class="max-w-6xl mx-auto">'
            . $head
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
            ? '<figcaption class="text-center text-gray-500 text-sm mt-3 italic">' . $this->e($caption) . '</figcaption>'
            : '';

        return '<div class="py-8 px-4">'
            . '<figure class="' . $figure . '">'
            . '<img src="' . $this->e($url) . '" alt="' . $alt . '" class="w-full rounded-xl object-cover">'
            . $fig
            . '</figure></div>';
    }

    protected function renderCTASection(array $p): string
    {
        $solid   = $this->attr($p, 'style', 'solid') !== 'outline';
        $section = $solid ? 'bg-brand-primary text-white' : 'bg-brand-bg text-brand-text border-2 border-brand-primary';
        $button  = $solid ? 'bg-white text-brand-primary' : 'bg-brand-primary text-white';

        $heading = $this->e($this->attr($p, 'heading', '¿Listo para comenzar?'));
        $body    = $this->e($this->attr($p, 'body', 'Contáctanos hoy y descubre cómo podemos ayudarte.'));
        $btn     = $this->attr($p, 'buttonLabel', '');
        $btnUrl  = $this->attr($p, 'buttonUrl', '');

        $btnHtml = '';
        if ($btn && $btnUrl) {
            $btnHtml = '<a href="' . $this->e($btnUrl) . '" class="inline-block font-semibold px-8 py-4 rounded-brand transition-opacity hover:opacity-90 ' . $button . '">' . $this->e($btn) . '</a>';
        }

        return '<section class="' . $section . ' py-20 px-4 text-center">'
            . '<div class="max-w-2xl mx-auto">'
            . '<h2 class="font-heading text-3xl font-bold mb-4">' . $heading . '</h2>'
            . '<p class="text-lg mb-10 opacity-90 leading-relaxed">' . $body . '</p>'
            . $btnHtml
            . '</div></section>';
    }

    protected function renderDivider(array $p): string
    {
        $height  = $this->attr($p, 'height', 'h-8');
        $showLine = $this->attr($p, 'showLine', 'no');
        $line    = $showLine === 'yes' ? '<hr class="w-full border-gray-200">' : '';

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

        $btnHtml = '';
        if ($btn && $btnUrl) {
            $btnHtml = '<a href="' . $this->e($btnUrl) . '" class="inline-block bg-white text-brand-primary-dark font-semibold px-8 py-4 rounded-brand hover:opacity-90 transition-opacity">' . $this->e($btn) . '</a>';
        }

        return '<section class="py-16 px-4 bg-brand-primary-dark text-white">'
            . '<div class="max-w-4xl mx-auto ' . $this->e($align) . '">'
            . '<h2 class="font-heading text-3xl font-bold mb-4">' . $title . '</h2>'
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
        $items = $this->attr($p, 'items', []);

        $head = $title
            ? '<h2 class="font-heading text-3xl font-bold mb-10 text-brand-text text-center">' . $this->e($title) . '</h2>'
            : '';

        $list = '';
        foreach ($items as $item) {
            $item = is_array($item) ? $item : [];
            $list .= '<details class="group bg-brand-bg rounded-xl border border-gray-200 px-6 py-4">'
                . '<summary class="flex items-center justify-between cursor-pointer font-semibold text-brand-text list-none">'
                . '<span>' . $this->e($this->attr($item, 'question', '')) . '</span>'
                . '<span class="text-gray-500 group-open:rotate-180 transition-transform">▾</span>'
                . '</summary>'
                . '<div class="mt-3 text-gray-700 prose prose-sm max-w-none">' . $this->raw($item, 'answer', '') . '</div>'
                . '</details>';
        }

        return '<section class="py-16 px-4 bg-white">'
            . '<div class="max-w-3xl mx-auto">'
            . $head
            . '<div class="space-y-3">' . $list . '</div>'
            . '</div></section>';
    }

    protected function renderTabs(array $p): string
    {
        $tabs = $this->attr($p, 'tabs', []);

        $nav = '';
        $bodies = '';
        foreach ($tabs as $i => $tab) {
            $tab = is_array($tab) ? $tab : [];
            $nav .= '<a href="#tab-' . $i . '" class="px-4 py-2 text-sm font-semibold text-gray-600 border-b-2 border-transparent">'
                . $this->e($this->attr($tab, 'label', 'Pestaña')) . '</a>';
            $bodies .= '<div id="tab-' . $i . '" class="text-gray-700 prose prose-lg max-w-none">'
                . $this->raw($tab, 'content', '') . '</div>';
        }

        return '<section class="py-16 px-4 bg-white">'
            . '<div class="max-w-4xl mx-auto">'
            . '<div class="border-b border-gray-200 mb-6"><div class="flex flex-wrap gap-2">' . $nav . '</div></div>'
            . $bodies
            . '</div></section>';
    }

    protected function renderTestimonials(array $p): string
    {
        $title         = $this->attr($p, 'title', '');
        $testimonials  = $this->attr($p, 'testimonials', []);

        $head = $title
            ? '<h2 class="font-heading text-3xl font-bold text-center mb-12 text-brand-text">' . $this->e($title) . '</h2>'
            : '';

        $cards = '';
        foreach ($testimonials as $t) {
            $t = is_array($t) ? $t : [];
            $cards .= '<blockquote class="bg-white p-8 rounded-2xl shadow-sm">'
                . '<p class="text-gray-700 text-lg leading-relaxed mb-6">“' . $this->e($this->attr($t, 'quote', '')) . '”</p>'
                . '<footer>'
                . '<div class="font-bold text-brand-text">' . $this->e($this->attr($t, 'author', '')) . '</div>'
                . '<div class="text-gray-500 text-sm">' . $this->e($this->attr($t, 'role', '')) . '</div>'
                . '</footer></blockquote>';
        }

        return '<section class="py-16 px-4 bg-brand-bg">'
            . '<div class="max-w-6xl mx-auto">'
            . $head
            . '<div class="grid grid-cols-1 md:grid-cols-2 gap-8">' . $cards . '</div>'
            . '</div></section>';
    }

    protected function renderGallery(array $p): string
    {
        $title  = $this->attr($p, 'title', '');
        $images = $this->attr($p, 'images', []);

        $head = $title
            ? '<h2 class="font-heading text-3xl font-bold text-center mb-12 text-brand-text">' . $this->e($title) . '</h2>'
            : '';

        $imgs = '';
        foreach ($images as $img) {
            $img = is_array($img) ? $img : [];
            $imgs .= '<img src="' . $this->e($this->attr($img, 'url', '')) . '" alt="' . $this->e($this->attr($img, 'alt', 'Imagen')) . '" class="w-full rounded-xl object-cover">';
        }

        return '<section class="py-16 px-4 bg-white">'
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

        $media = $embed
            ? '<div class="rounded-2xl overflow-hidden"><div class="w-full aspect-video"><iframe src="' . $this->e($embed) . '" title="' . $this->e($caption ?: 'Video') . '" class="w-full h-full" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div></div>'
            : '<div class="w-full rounded-2xl bg-gray-100 text-gray-500 text-center py-24">Añade la URL de un video de YouTube o Vimeo</div>';

        $cap = $caption
            ? '<p class="text-center text-gray-500 text-sm mt-3 italic">' . $this->e($caption) . '</p>'
            : '';

        return '<section class="py-16 px-4 bg-white">'
            . '<div class="max-w-4xl mx-auto">' . $media . $cap . '</div>'
            . '</section>';
    }

    protected function renderLogoCloud(array $p): string
    {
        $title = $this->attr($p, 'title', '');
        $logos = $this->attr($p, 'logos', []);

        $head = $title
            ? '<h2 class="font-heading text-2xl font-bold text-center mb-10 text-brand-text">' . $this->e($title) . '</h2>'
            : '';

        $imgs = '';
        foreach ($logos as $logo) {
            $logo = is_array($logo) ? $logo : [];
            $imgs .= '<img src="' . $this->e($this->attr($logo, 'url', '')) . '" alt="' . $this->e($this->attr($logo, 'alt', 'Logo')) . '" class="h-12 w-auto opacity-75">';
        }

        return '<section class="py-16 px-4 bg-brand-bg">'
            . '<div class="max-w-6xl mx-auto">'
            . $head
            . '<div class="flex flex-wrap items-center justify-center gap-8">' . $imgs . '</div>'
            . '</div></section>';
    }

    protected function renderStats(array $p): string
    {
        $title = $this->attr($p, 'title', '');
        $stats = $this->attr($p, 'stats', []);

        $head = $title
            ? '<h2 class="font-heading text-3xl font-bold text-center mb-12">' . $this->e($title) . '</h2>'
            : '';

        $items = '';
        foreach ($stats as $s) {
            $s = is_array($s) ? $s : [];
            $items .= '<div>'
                . '<div class="text-5xl font-bold mb-2">' . $this->e($this->attr($s, 'value', '')) . '</div>'
                . '<div class="text-lg opacity-90">' . $this->e($this->attr($s, 'label', '')) . '</div>'
                . '</div>';
        }

        return '<section class="py-16 px-4 bg-brand-primary-dark text-white">'
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
            . '<span class="text-gray-300">' . str_repeat('★', 5 - $score) . '</span>';
        $txt   = $text ? '<p class="text-gray-600">' . $this->e($text) . '</p>' : '';

        return '<div class="py-8 px-4 text-center">'
            . '<div class="text-3xl mb-2">' . $stars . '</div>'
            . $txt
            . '</div>';
    }
}
