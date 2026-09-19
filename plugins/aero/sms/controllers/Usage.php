<?php namespace Aero\Sms\Controllers;

use Aero\Sms\Models\Message;
use Backend\Classes\Controller;
use BackendMenu;

/** Consumo agregado por tenant y por key: lo que hay que saber para cobrar y auditar. */
class Usage extends Controller
{
    public $requiredPermissions = ['aero.sms.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Sms', 'sms', 'usage');
        $this->pageTitle = 'Consumo por tenant';
    }

    public function index(): void
    {
        $days = max(1, (int) input('days', 30));

        $this->vars['days'] = $days;
        $this->vars['rows'] = Message::where('created_at', '>=', now()->subDays($days))
            ->selectRaw("tenant_id, api_key_id, MAX(consumer) as consumer, COUNT(*) as messages, SUM(segments) as segments,
                SUM(status = 'delivered') as delivered, SUM(status IN ('failed','undelivered')) as failed,
                SUM(CASE WHEN refunded_at IS NULL THEN credits_charged ELSE 0 END) as credits")
            ->groupBy('tenant_id', 'api_key_id')
            ->orderByDesc('messages')
            ->get()
            ->each(fn ($r) => $r->tenant_label = (new Message(['tenant_id' => $r->tenant_id]))->tenant_name);
    }
}
