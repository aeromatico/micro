<?php namespace Aero\School\Jobs;

use Aero\School\Classes\Announcer;
use Aero\School\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendAnnouncementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Un solo intento: reintentar reenviaría el aviso a quienes ya lo recibieron.
    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public int $announcementId)
    {
    }

    public function handle(Announcer $announcer): void
    {
        if ($a = Announcement::find($this->announcementId)) {
            $announcer->send($a);
        }
    }
}
