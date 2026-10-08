<?php namespace Aero\Workflows\Jobs;

use Aero\Workflows\Classes\WorkflowRunner;
use Aero\Workflows\Models\Run;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunWorkflowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;

    public function __construct(public int $runId, public bool $resume = false) {}

    public function handle(): void
    {
        $run = Run::find($this->runId);

        if (!$run) {
            return;
        }

        $this->resume ? WorkflowRunner::resume($run) : WorkflowRunner::execute($run);
    }
}
