<?php namespace Aero\Sites\Jobs;

use Aero\Sites\Classes\Ai\SiteGenerator;
use Aero\Sites\Models\AiGeneration;
use Aero\Sites\Models\Page;
use Aero\Sites\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Log;
use Throwable;

/**
 * Ejecuta SiteGenerator en background. El registro `AiGeneration` (status
 * pending) ya existe antes de encolar este job (creado por el controller),
 * para que el frontend pueda hacer polling del `log_id` desde el instante
 * en que se dispara la generación.
 */
class GenerateAiSiteJob implements ShouldQueue
{
    use Dispatchable, Queueable, InteractsWithQueue, SerializesModels;

    public int $tries = 1; // SiteGenerator ya reintenta internamente la corrección del JSON
    public int $timeout = 180;

    public function __construct(
        protected int $tenantId,
        protected string $prompt,
        protected int $generationId
    ) {
    }

    public function handle(): void
    {
        $log = AiGeneration::find($this->generationId);
        if (!$log || $log->status !== 'pending') {
            return;
        }

        $tenant = Tenant::find($this->tenantId);
        if (!$tenant) {
            $log->update(['status' => 'failed', 'error_message' => 'Tenant no encontrado.']);
            return;
        }

        $log->update(['status' => 'processing', 'step' => 'generating_content']);

        try {
            $result = (new SiteGenerator())->generate($tenant, $this->prompt, 2, $log);

            if (!$result) {
                // SiteGenerator ya marcó status=failed en $log si todos los intentos fallaron.
                $log->refresh();
                if ($log->status !== 'failed') {
                    $log->update(['status' => 'failed', 'error_message' => 'La IA no pudo generar contenido válido.']);
                }
                return;
            }

            $log->update(['step' => 'saving_page']);

            $page = Page::forTenant($tenant->id)->where('slug', '')->first();

            if ($page) {
                $page->puck_data = $result['puck_data'];
                $page->content   = $result['html'];
                $page->save();
            } else {
                $page = Page::create([
                    'tenant_id'    => $tenant->id,
                    'title'        => $tenant->name,
                    'slug'         => '',
                    'layout'       => 'home',
                    'is_published' => true,
                    'sort_order'   => 1,
                    'puck_data'    => $result['puck_data'],
                    'content'      => $result['html'],
                ]);
            }

            // El status/success ya lo dejó 'done' SiteGenerator::generate(); solo
            // falta enlazar la página resultante (SiteGenerator no la conoce).
            $log->update(['result_page_id' => $page->id, 'step' => null]);
        } catch (Throwable $e) {
            Log::error('Aero\\Sites\\GenerateAiSiteJob: ' . $e->getMessage());
            $log->update(['status' => 'failed', 'error_message' => $e->getMessage(), 'step' => null]);
        }
    }
}
