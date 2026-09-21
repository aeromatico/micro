<?php namespace Aero\Services\Console;

use Aero\Connector\Models\Connector;
use Aero\Services\Classes\OfferBuilder;
use Aero\Services\Models\Service;
use Illuminate\Console\Command;

class BuildOffer extends Command
{
    protected $signature = 'services:build-offer
        {service : ID o slug del servicio}
        {--connector= : ID del conector de IA (por defecto, el primer ai_anthropic)}
        {--category=Panel : Categoría de servicios a asignar}
        {--dry-run : Muestra la oferta sin guardarla}
        {--from-json= : Aplica una oferta ya redactada (JSON) en vez de llamar a la API de IA}
        {--evidence : Solo muestra la evidencia que se le entregaría a la IA}';

    protected $description = 'Arma la oferta del servicio (resumen, descripción, página HTML) analizando los plugins ligados.';

    public function handle(): int
    {
        $service = Service::where('id', $this->argument('service'))->orWhere('slug', $this->argument('service'))->first();
        if (!$service) {
            $this->error('Servicio no encontrado.');

            return self::FAILURE;
        }

        $connector = $this->option('connector') ? Connector::find($this->option('connector')) : null;
        $builder = new OfferBuilder($connector);

        if ($this->option('evidence')) {
            $this->line(json_encode($builder->evidence($service), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        try {
            if ($file = $this->option('from-json')) {
                $offer = $builder->fromJson((string) file_get_contents($file), null, $service);
            }
            else {
                $this->info("Analizando plugins ligados de «{$service->name}»…");
                $offer = $builder->build($service);
            }
        }
        catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('Resumen: ' . $offer['summary']);
        $this->line('Características: ' . count($offer['features']) . ' · Requisitos: ' . count($offer['requirements']) . ' · HTML: ' . strlen($offer['code']) . ' bytes · Docs: ' . implode(',', $offer['docs']));

        if ($this->option('dry-run')) {
            $dir = storage_path('app/service-offers');
            @mkdir($dir, 0775, true);
            file_put_contents("{$dir}/{$service->slug}.json", json_encode($offer, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            file_put_contents("{$dir}/{$service->slug}.html", $offer['code']);
            $this->warn("Dry-run: guardado en {$dir}/{$service->slug}.(json|html), el servicio no se modificó.");

            return self::SUCCESS;
        }

        $builder->apply($service, $offer, (string) $this->option('category'));
        $this->info('Oferta guardada en el servicio.');

        return self::SUCCESS;
    }
}
