<?php namespace Aero\Producer\Classes;

use Aero\Producer\Models\Project;

/**
 * Trae a Producer los videos creados con video-cli (carpetas `videos/<nombre>/`
 * con video.json, y opcionalmente metadata.json) y los archivos que ya están
 * publicados en Medios (`videos/<nombre>-vN.mp4`, `<nombre>-vertical-vN.mp4`).
 */
class Importer
{
    public static function sourceRoot(): string
    {
        return base_path('videos');
    }

    public static function mediaRoot(): string
    {
        return storage_path('app/media');
    }

    /** Carpetas con video.json que aún no son proyecto de la plataforma. */
    public static function available(): array
    {
        $known = Project::whereNull('tenant_id')->pluck('slug')->all();
        $out = [];

        foreach (glob(self::sourceRoot() . '/*/video.json') ?: [] as $file) {
            $slug = basename(dirname($file));
            if (!str_ends_with($slug, '-vertical') && !in_array($slug, $known, true)) {
                $out[] = $slug;
            }
        }

        sort($out);

        return $out;
    }

    public static function import(string $slug): ?Project
    {
        if (!preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug) || str_ends_with($slug, '-vertical')) {
            return null;
        }

        $dir = self::sourceRoot() . '/' . $slug;
        $video = self::readJson($dir . '/video.json');
        if (!$video) {
            return null;
        }

        $project = Project::whereNull('tenant_id')->where('slug', $slug)->first() ?: new Project(['tenant_id' => null, 'slug' => $slug]);
        $project->name = $project->name ?: trim(strip_tags(str_replace('<br>', ' ', (string) ($video['titulo'] ?? $slug))));
        $project->kind = 'video';
        $project->voice_id = $project->voice_id ?: ($video['voice'] ?? null);
        $project->source_dir = 'videos/' . $slug;
        $project->script = $project->script ?: collect($video['scenes'] ?? [])->pluck('text')->filter()->implode("\n\n");

        // Lo que el usuario ya editó en el panel manda; el archivo solo rellena huecos.
        $project->metadata = self::mergeMetadata((array) $project->metadata, self::readMetadata($dir . '/metadata.json'));
        $project->outputs = self::outputs($slug);
        $project->status = $project->outputs ? ($project->status === 'published' ? 'published' : 'rendered') : ($project->status === 'draft' ? 'designed' : $project->status);
        $project->save();

        return $project;
    }

    /** Archivos finales de cada orientación (la versión más alta) que existen en Medios. */
    public static function outputs(string $slug): array
    {
        $outputs = [];
        foreach (['16:9' => $slug, '9:16' => $slug . '-vertical'] as $orientation => $base) {
            $best = null;
            foreach (glob(self::mediaRoot() . '/videos/' . $base . '-v*.mp4') ?: [] as $file) {
                if (preg_match('/-v(\d+)\.mp4$/', $file, $m) && (!$best || (int) $m[1] > $best[0])) {
                    $best = [(int) $m[1], $file];
                }
            }
            if ($best) {
                $outputs[] = [
                    'orientation' => $orientation,
                    'version'     => 'v' . $best[0],
                    'path'        => 'videos/' . basename($best[1]),
                ];
            }
        }

        return $outputs;
    }

    protected static function readJson(string $file): ?array
    {
        if (!is_file($file)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? $data : null;
    }

    /** metadata.json → texto plano: las listas se vuelven «a, b, c». */
    protected static function readMetadata(string $file): array
    {
        $raw = self::readJson($file) ?? [];
        $meta = [];
        foreach (Project::METADATA_KEYS as $key) {
            $value = $raw[$key] ?? null;
            if (is_array($value)) {
                $value = implode($key === 'hashtags' ? ' ' : ', ', array_map('strval', $value));
            }
            if ($value !== null && $value !== '') {
                $meta[$key] = (string) $value;
            }
        }

        return $meta;
    }

    protected static function mergeMetadata(array $current, array $fromFile): array
    {
        foreach ($fromFile as $key => $value) {
            if (!isset($current[$key]) || $current[$key] === '') {
                $current[$key] = $value;
            }
        }

        return $current;
    }
}
