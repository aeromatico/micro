<?php namespace Aero\Notify\Jobs;

use Aero\Notify\Classes\Notify;
use Aero\Notify\Models\Delivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Transmite una entrega ya renderizada (status 'queued'). Reintenta con
 * backoff; el estado 'failed' solo se marca cuando se agotan los intentos.
 */
class DeliverNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(public int $deliveryId)
    {
    }

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(): void
    {
        $delivery = Delivery::find($this->deliveryId);

        if (!$delivery || $delivery->status !== 'queued') {
            return;
        }

        Notify::transmit($delivery, throwOnError: true);
    }

    public function failed(\Throwable $e): void
    {
        Delivery::find($this->deliveryId)?->markFailed($e->getMessage());
        \Log::error("Aero.Notify: entrega #{$this->deliveryId} agotó reintentos: " . $e->getMessage());
    }
}
