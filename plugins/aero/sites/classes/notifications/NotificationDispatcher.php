<?php namespace Aero\Sites\Classes\Notifications;

use Aero\Sites\Models\ContactSubmission;
use Aero\Sites\Models\NotificationChannel;
use Event;
use Log;

class NotificationDispatcher
{
    protected array $drivers = [];
    protected bool $booted = false;

    protected function boot(): void
    {
        if ($this->booted) return;
        Event::fire('aero.sites.registerNotificationDrivers', [$this]);
        $this->booted = true;
    }

    public function register(string $type, string $class): void
    {
        $this->drivers[$type] = $class;
    }

    public function dispatch(ContactSubmission $submission): void
    {
        $this->boot();

        $channels = NotificationChannel::forTenant($submission->tenant_id)
            ->enabled()
            ->orderBy('sort_order')
            ->get();

        if ($channels->isEmpty()) {
            $submission->markAsSent();
            return;
        }

        $successCount = 0;
        $failCount = 0;

        foreach ($channels as $channel) {
            $driver = $this->resolveDriver($channel);
            if (!$driver) {
                Log::warning("Aero\\Sites: No driver for channel type '{$channel->type}'");
                $failCount++;
                continue;
            }

            try {
                $sent = $driver->send($submission, $channel);
                $sent ? $successCount++ : $failCount++;
            } catch (\Exception $e) {
                Log::error("Aero\\Sites: Notification failed for channel {$channel->id}: " . $e->getMessage());
                $failCount++;
            }
        }

        if ($successCount > 0 && $failCount === 0) {
            $submission->markAsSent();
        } elseif ($successCount > 0) {
            $submission->markAsPartial();
        } else {
            $submission->markAsFailed();
        }
    }

    protected function resolveDriver(NotificationChannel $channel): ?NotificationDriverInterface
    {
        $class = $this->drivers[$channel->type] ?? null;
        if (!$class) return null;
        return new $class();
    }
}
