<?php namespace Aero\Services\Console;

use Aero\Services\Classes\OfferBuilder;
use Aero\Services\Models\Service;
use Illuminate\Console\Command;

/**
 * Propuesta de oferta de un servicio, SIN tocar el servicio. Es lo único que el agente de docs
 * puede ejecutar sobre servicios: la aplicación la decide una persona con `services:offer-apply`.
 *
 *   --evidence           base del servicio + evidencia de los plugins ligados (JSON)
 *   --from-json=archivo  valida la oferta redactada y deja la propuesta en storage/app/service-offers/
 */
class OfferProposal extends Command
{
    protected $signature = 'services:offer-proposal
        {service : ID o slug del servicio}
        {--evidence : Muestra la base del servicio y la evidencia de los plugins ligados}
        {--from-json= : Valida una oferta redactada (JSON) y la guarda como propuesta}';

    protected $description = 'Valida y guarda la propuesta de oferta de un servicio sin modificar el servicio.';

    public function handle(): int
    {
        $service = Service::where('id', $this->argument('service'))->orWhere('slug', $this->argument('service'))->first();
        if (!$service) {
            $this->error('Servicio no encontrado.');

            return self::FAILURE;
        }

        $builder = new OfferBuilder(null);

        if ($this->option('evidence')) {
            $this->line(json_encode([
                'service' => [
                    'id'           => $service->id,
                    'name'         => $service->name,
                    'slug'         => $service->slug,
                    'public'       => (bool) $service->is_active,
                    'summary'      => $service->summary,
                    'description'  => $service->description,
                    'features'     => $service->features,
                    'requirements' => $service->requirements,
                    'plans'        => $service->plans,
                    'categories'   => $service->categories->pluck('name')->all(),
                    'plugin_links' => $service->plugin_links,
                    'has_code'     => filled($service->code),
                ],
                'plugins' => $builder->evidence($service),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $file = (string) $this->option('from-json');
        if (!$file || !is_file($file)) {
            $this->error('Indica --from-json=archivo (o --evidence).');

            return self::FAILURE;
        }

        try {
            $offer = $builder->fromJson((string) file_get_contents($file), null, $service);
        }
        catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $dir = storage_path('app/service-offers');
        @mkdir($dir, 0775, true);
        file_put_contents("{$dir}/{$service->slug}.proposal.json", json_encode($offer, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        // .html: el build de Tailwind del tema lo escanea (storage/app/service-offers/*.html), así la vista previa ya trae sus clases.
        file_put_contents("{$dir}/{$service->slug}.proposal.html", $offer['code']);

        $this->line('Resumen: ' . $offer['summary']);
        $this->line('Características: ' . count($offer['features']) . ' · Requisitos: ' . count($offer['requirements']) . ' · HTML: ' . strlen($offer['code']) . ' bytes · Docs enlazados: ' . count($offer['docs']));
        $this->info("Propuesta guardada (el servicio NO se modificó): {$dir}/{$service->slug}.proposal.json");

        return self::SUCCESS;
    }
}
