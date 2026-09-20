<?php namespace Aero\Docs\Classes;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Markdown → HTML (CommonMark + GFM: tablas, tareas, tachado, autolinks),
 * con anclas en los títulos, callouts estilo GitHub (> [!NOTE]) y tabla de contenidos.
 */
class Markdown
{
    protected const CALLOUTS = [
        'NOTE'      => 'Nota',
        'TIP'       => 'Consejo',
        'IMPORTANT' => 'Importante',
        'WARNING'   => 'Atención',
        'CAUTION'   => 'Cuidado',
    ];

    /** @return array{html:string, toc:array<int,array{level:int,id:string,text:string}>, minutes:int} */
    public static function render(?string $markdown): array
    {
        $markdown = (string) $markdown;

        $env = new Environment([
            'html_input'         => 'allow',
            'allow_unsafe_links' => false,
            'heading_permalink'  => [
                'html_class'          => 'docs-anchor',
                'id_prefix'           => '',
                'fragment_prefix'     => '',
                'apply_id_to_heading' => true,
                'insert'              => 'after',
                'symbol'              => '#',
                'title'               => 'Enlace a esta sección',
                'aria_hidden'         => true,
                'min_heading_level'   => 2,
                'max_heading_level'   => 4,
            ],
        ]);
        $env->addExtension(new CommonMarkCoreExtension());
        $env->addExtension(new GithubFlavoredMarkdownExtension());
        $env->addExtension(new HeadingPermalinkExtension());

        $html = (string) (new MarkdownConverter($env))->convert($markdown);
        $html = static::callouts($html);

        $toc = [];
        if (preg_match_all('/<h([23])[^>]*\sid="([^"]+)"[^>]*>(.*?)<\/h\1>/s', $html, $m, PREG_SET_ORDER)) {
            foreach ($m as $h) {
                $text = trim(html_entity_decode(strip_tags($h[3])), " \t\n\r#");
                $toc[] = ['level' => (int) $h[1], 'id' => $h[2], 'text' => $text];
            }
        }

        $words = str_word_count(strip_tags($html));

        return ['html' => $html, 'toc' => $toc, 'minutes' => max(1, (int) ceil($words / 200))];
    }

    protected static function callouts(string $html): string
    {
        $re = '/<blockquote>\s*<p>\[!(' . implode('|', array_keys(self::CALLOUTS)) . ')\]\s*(?:<br\s*\/?>)?\s*/i';

        return preg_replace_callback($re, function ($m) {
            $type = strtoupper($m[1]);

            return '<blockquote class="callout callout-' . strtolower($type) . '">'
                . '<p class="callout-title">' . self::CALLOUTS[$type] . '</p><p>';
        }, $html);
    }
}
