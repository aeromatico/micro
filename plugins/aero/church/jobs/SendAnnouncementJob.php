<?php namespace Aero\Church\Jobs;

use Aero\Church\Classes\AnnouncementService;
use Aero\Church\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendAnnouncementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public int $announcementId)
    {
    }

    public function handle(AnnouncementService $service): void
    {
        $a = Announcement::find($this->announcementId);
        if ($a && $a->status === 'sending') {
            $service->deliver($a);
        }
    }
}
