<?php namespace Aero\Producer\Classes;

use Aero\Producer\Models\Project;

/**
 * Puente blando hacia Aero.Youtube: deja las subidas como BORRADOR PRIVADO con los
 * datos del proyecto ya rellenos, para que solo haya que revisarlas y aprobarlas
 * en YouTube → Subidas. Nunca sube nada por sí mismo.
 */
class YoutubeBridge
{
    public static function available(): bool
    {
        return class_exists(\Aero\Youtube\Models\Upload::class) && \Schema::hasTable('aero_youtube_uploads');
    }

    /** @return array{created:int, skipped:int} */
    public static function send(Project $project, ?int $userId): array
    {
        $created = $skipped = 0;
        $upload = \Aero\Youtube\Models\Upload::class;

        foreach ((array) $project->outputs as $output) {
            $path = (string) ($output['path'] ?? '');
            if ($path === '' || $upload::where('file_path', $path)->whereIn('status', ['draft', 'queued', 'uploading', 'done'])->exists()) {
                $skipped++;
                continue;
            }

            $description = (string) $project->meta('description');
            $hashtags = trim((string) $project->meta('hashtags'));
            if ($hashtags !== '' && !str_contains($description, $hashtags)) {
                $description = rtrim($description) . "\n\n" . $hashtags;
            }
            // Vertical y corto: YouTube lo clasifica como Short con esta etiqueta.
            if (($output['orientation'] ?? '') === '9:16' && !stripos($description, '#shorts')) {
                $description = rtrim($description) . "\n#Shorts";
            }

            $upload::create([
                'tenant_id'       => $project->tenant_id,
                'backend_user_id' => $userId,
                'file_path'       => $path,
                'title'           => mb_substr((string) ($project->meta('title') ?: $project->name), 0, 100),
                'description'     => $description,
                'tags'            => mb_substr((string) $project->meta('tags'), 0, 480),
                'category_id'     => (string) ($project->meta('category_id') ?: '28'),
                'privacy'         => 'private',
                'status'          => 'draft',
            ]);
            $created++;
        }

        return compact('created', 'skipped');
    }
}
