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
    public int $timeout = 280;

    public function __construct(public int $messageId)
    {
    }

    public function handle(): void
    {
        // La cola vuelve a ofrecer un trabajo que pasa de su `retry_after` (90 s): quien lo reclama primero
        // lo ejecuta y el otro lo descarta, así una respuesta nunca se genera dos veces.
        $claimed = Message::where('id', $this->messageId)->where('status', 'pending')->update(['status' => 'running']);

        if ($claimed && ($reply = Message::find($this->messageId))) {
            AgentRunner::run($reply);
        }
    }
}
