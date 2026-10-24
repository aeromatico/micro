<?php namespace Aero\Producer\Jobs;

use Aero\Producer\Classes\Production;
use Aero\Producer\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Envía el pedido y luego revisa cada 30 s hasta que el video esté listo (máx. ~25 min). */
class ProduceOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    public function __construct(public Order $order, public int $checks = 0)
    {
    }

    public function handle(): void
    {
        $order = $this->order->fresh();
        if (!$order) {
            return;
        }

        try {
            if ($order->provider_ref) {
                // Ya está en el proveedor (o falló solo la consulta): se vuelve a mirar, nunca se paga dos veces.
                $order->forceFill(['status' => 'producing', 'error_message' => null])->save();
            } else {
                Production::submit($order);
                $order->refresh();
            }

            if (Production::poll($order)) {
                return;
            }

            if ($this->checks >= 50) {
                Production::fail($order, 'El video tardó demasiado en producirse.');

                return;
            }

            static::dispatch($order, $this->checks + 1)->delay(now()->addSeconds(30));
        } catch (\Throwable $e) {
            Production::fail($order, $e->getMessage());
        }
    }
}
