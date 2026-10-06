<?php namespace Aero\Tracking\Jobs;

use Aero\Tracking\Classes\Feeds\FeedService;
use Aero\Tracking\Models\Feed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Una vuelta de polling de un feed; FeedService la vuelve a encolar con retraso. */
class PollFeed implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public int $feedId)
    {
    }

    public function handle(): void
    {
        $feed = Feed::find($this->feedId);

        if ($feed && $feed->isActive()) {
            FeedService::poll($feed);
        }
    }
}
