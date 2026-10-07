<?php namespace Aero\Workspaces\Jobs;

use Aero\Workspaces\Classes\AgentRunner;
use Aero\Workspaces\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Completa la respuesta pendiente de un agente (varias vueltas al modelo y a sus herramientas). */
class RunAgentTurnJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 170;

    public function __construct(public int $messageId)
    {
    }

    public function handle(): void
    {
        $reply = Message::find($this->messageId);

        if ($reply && $reply->status === 'pending') {
            AgentRunner::run($reply);
        }
    }
}
