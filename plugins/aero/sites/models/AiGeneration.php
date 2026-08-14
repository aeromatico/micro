<?php namespace Aero\Sites\Models;

use Model;

class AiGeneration extends Model
{
    public $table = 'aero_sites_ai_generations';

    protected $fillable = [
        'tenant_id', 'user_id', 'provider', 'model',
        'prompt', 'input_tokens', 'output_tokens',
        'success', 'error_message', 'retry_count',
        'status', 'step', 'result_page_id', 'archetype_handle',
    ];

    public $belongsTo = [
        'tenant'     => [Tenant::class],
        'user'       => [\Backend\Models\User::class, 'key' => 'user_id'],
        'resultPage' => [Page::class, 'key' => 'result_page_id'],
    ];

    public function isFinished(): bool
    {
        return in_array($this->status, ['done', 'failed'], true);
    }
}
