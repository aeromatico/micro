<?php namespace Aero\Help\Classes;

use Aero\Help\Models\SearchLog;
use Aero\Help\Models\Topic;
use Illuminate\Support\Str;

/**
 * Búsqueda sin IA sobre temas, artículos y guías de la plataforma. Puntúa por
 * dónde cae cada palabra (título > palabras clave > resumen > contenido).
 * Toda búsqueda queda registrada: las de cero resultados son el backlog.
 */
class Search
{
    protected const STOP = [
        'como', 'para', 'que', 'una', 'uno', 'por', 'con', 'los', 'las', 'del', 'mis', 'mas', 'sus', 'esta', 'este',
        'puedo', 'quiero', 'necesito', 'hacer', 'donde', 'cual', 'cuando', 'tengo', 'ayuda', 'favor', 'hola',
    ];

    /** @return array<int, string> */
    public static function tokens(string $query): array
    {
        $words = preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii($query)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter($words, fn ($w) => strlen($w) >= 3 && !in_array($w, self::STOP, true))));
    }

    /** @return array<int, array{kind: string, id: int, title: string, summary: ?string, score: float}> */
    public static function run(string $query, int $limit = 6): array
    {
        $tokens = self::tokens($query);
        if (!$tokens) {
            return [];
        }

        $hits = [];
        $add = function (string $kind, $id, string $title, ?string $summary, float $score) use (&$hits) {
            $key = $kind . ':' . $id;
            $hits[$key] = ['kind' => $kind, 'id' => (int) $id, 'title' => $title, 'summary' => $summary, 'score' => ($hits[$key]['score'] ?? 0) + $score];
        };
        $fold = fn (?string $s) => Str::lower(Str::ascii((string) $s));

        foreach (Topic::published()->get() as $topic) {
            foreach ($tokens as $t) {
                $score = (str_contains($fold($topic->title), $t) ? 3 : 0)
                    + (str_contains($fold($topic->keywords), $t) ? 2.5 : 0)
                    + (str_contains($fold($topic->summary), $t) ? 1 : 0);
                if ($score > 0) {
                    $add('topic', $topic->id, $topic->title, $topic->summary, $score + 1);
                }
            }
        }

        if (Integrations::docs()) {
            $articles = \Aero\Docs\Models\Article::platform()->published()
                ->where(function ($q) use ($tokens) {
                    foreach ($tokens as $t) {
                        $q->orWhere('title', 'like', "%{$t}%")->orWhere('excerpt', 'like', "%{$t}%")->orWhere('content', 'like', "%{$t}%");
                    }
                })->limit(40)->get(['id', 'title', 'excerpt', 'content']);

            foreach ($articles as $article) {
                $title = $fold($article->title);
                $excerpt = $fold($article->excerpt);
                $content = $fold($article->content);
                foreach ($tokens as $t) {
                    $score = (str_contains($title, $t) ? 3 : 0) + (str_contains($excerpt, $t) ? 1 : 0) + (str_contains($content, $t) ? 0.5 : 0);
                    if ($score > 0) {
                        $add('doc', $article->id, $article->title, $article->excerpt, $score);
                    }
                }
            }
        }

        if (Integrations::docs()) {
            $guides = \Aero\Docs\Models\Guide::platform()->published()
                ->where(function ($q) use ($tokens) {
                    foreach ($tokens as $t) {
                        $q->orWhere('title', 'like', "%{$t}%");
                    }
                })->limit(20)->get(['id', 'title']);

            foreach ($guides as $guide) {
                foreach ($tokens as $t) {
                    if (str_contains($fold($guide->title), $t)) {
                        $add('guide', $guide->id, $guide->title, 'Guía interactiva', 3);
                    }
                }
            }
        }

        uasort($hits, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice(array_values($hits), 0, $limit);
    }

    public static function log(string $query, int $results, ?string $area): void
    {
        try {
            SearchLog::create([
                'tenant_id'  => CurrentTenant::id(),
                'user_id'    => \BackendAuth::getUser()?->id,
                'query'      => mb_substr(trim($query), 0, 255),
                'area'       => $area ? mb_substr($area, 0, 150) : null,
                'results'    => min($results, 65535),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Aero.Help: no se pudo registrar la búsqueda: ' . $e->getMessage());
        }

        if ($results === 0) {
            \Event::fire('aero.help.gapFound', [$query, $area]);
        }
    }
}
