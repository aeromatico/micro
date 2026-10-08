<?php namespace Aero\Services\Console;

use Aero\Services\Classes\OfferBuilder;
use Aero\Services\Models\Service;
use Illuminate\Console\Command;

/** Aplica al servicio la propuesta que dejó `services:offer-proposal`. Es la decisión de una persona. */
class OfferApply extends Command
{
    protected $signature = 'services:offer-apply
        {service : ID o slug del servicio}
        {--show : Solo muestra el resumen de la propuesta, sin aplicar}
        {--revert : Devuelve el servicio a como estaba antes de la última aplicación}';

    protected $description = 'Aplica (o muestra) la propuesta de oferta guardada para un servicio.';

    public function handle(): int
    {
        $service = Service::where('id', $this->argument('service'))->orWhere('slug', $this->argument('service'))->first();
        if (!$service) {
            $this->error('Servicio no encontrado.');

            return self::FAILURE;
        }

        if ($this->option('revert')) {
            return $this->revert($service);
        }

        $file = storage_path("app/service-offers/{$service->slug}.proposal.json");
        $offer = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (!is_array($offer) || empty($offer['summary']) || empty($offer['code'])) {
            $this->error("No hay una propuesta válida para «{$service->slug}».");

            return self::FAILURE;
        }

        if ($this->option('show')) {
            $this->line(json_encode([
                'summary'      => $offer['summary'],
                'description'  => mb_substr((string) $offer['description'], 0, 400),
                'features'     => count($offer['features']),
                'requirements' => count($offer['requirements']),
                'html_bytes'   => strlen($offer['code']),
                'docs'         => count($offer['docs']),
                'public'       => (bool) $service->is_active,
                'file'         => $file,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        // Copia de lo que había, por si la oferta aplicada no convence (--revert).
        file_put_contents(storage_path("app/service-offers/{$service->slug}.previous.json"), json_encode(
            $service->only(['summary', 'description', 'features', 'requirements', 'code']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        // Categoría '' : no se agrega ninguna (las del servicio ya las eligió una persona).
        (new OfferBuilder(null))->apply($service, $offer, '');
        @rename($file, storage_path("app/service-offers/{$service->slug}.applied.json"));
        @unlink(storage_path("app/service-offers/{$service->slug}.proposal.html"));

        $this->info("Oferta aplicada a «{$service->name}».");

        return self::SUCCESS;
    }

    protected function revert(Service $service): int
    {
        $file = storage_path("app/service-offers/{$service->slug}.previous.json");
        $prev = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (!is_array($prev)) {
            $this->error('No hay una copia anterior para revertir.');

            return self::FAILURE;
        }

        $service->fill($prev)->save();
        (new OfferBuilder(null))->dumpForTailwind($service);
        @unlink($file);
        $this->info("«{$service->name}» volvió a su contenido anterior.");

        return self::SUCCESS;
    }
}
