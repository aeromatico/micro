<?php namespace Aero\Telegram\Controllers;

use Aero\Hello\Models\Account;
use Aero\Telegram\Classes\TelegramApi;
use Aero\Telegram\Models\TelegramBot;
use Backend\Classes\Controller;
use BackendMenu;
use Flash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Alta de bots de Telegram: valida el token con getMe, deja la cuenta en
 * Aero.Hello (driver=telegram) y registra el webhook con su secret_token.
 */
class Bots extends Controller
{
    public $requiredPermissions = ['aero.telegram.manage'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Telegram', 'telegram', 'bots');
    }

    public function index()
    {
        $this->pageTitle = 'aero.telegram::lang.bots.title';
        $this->vars['bots'] = TelegramBot::with('account')->orderByDesc('id')->get();
        $this->vars['tenants'] = $this->tenantOptions();
    }

    public function onConnect()
    {
        $token = trim((string) post('token'));
        if ($token === '') {
            throw new \ApplicationException(trans('aero.telegram::lang.bots.token_required'));
        }

        try {
            $api = new TelegramApi($token);
            $me = $api->getMe();
        } catch (RuntimeException $e) {
            throw new \ApplicationException(trans('aero.telegram::lang.bots.token_invalid', ['error' => $e->getMessage()]));
        }

        $botId = (int) $me['id'];
        $externalId = 'telegram:' . $botId;
        $tenantId = post('tenant_id') ?: null;

        $attributes = [
            'driver'            => 'telegram',
            'platform'          => 'telegram',
            'label'             => $me['first_name'] ?? ($me['username'] ?? $externalId),
            'external_username' => $me['username'] ?? null,
            'status'            => 'connected',
            'is_enabled'        => true,
            'tenant_id'         => $tenantId,
            'connected_at'      => now(),
        ];

        $account = Account::where('zernio_account_id', $externalId)->first();
        if ($account) {
            Account::where('id', $account->id)->update($attributes);
            $account->refresh();
        } else {
            $account = Account::create($attributes + ['zernio_account_id' => $externalId]);
        }

        $bot = TelegramBot::firstOrNew(['account_id' => $account->id]);
        $bot->bot_id = $botId;
        $bot->username = $me['username'] ?? null;
        $bot->token = $token;
        if (!$bot->webhook_secret) {
            $bot->webhook_secret = Str::random(64);
        }
        $bot->save();

        $this->registerWebhook($account, $bot, $api);

        Flash::success(trans('aero.telegram::lang.bots.connected', ['name' => $account->label]));

        return ['#telegram-bots' => $this->makePartial('list', ['bots' => TelegramBot::with('account')->orderByDesc('id')->get()])];
    }

    public function onReconfigure()
    {
        $bot = TelegramBot::with('account')->findOrFail((int) post('id'));

        $this->registerWebhook($bot->account, $bot, new TelegramApi($bot->token));

        Flash::success(trans('aero.telegram::lang.bots.reconfigured'));

        return ['#telegram-bots' => $this->makePartial('list', ['bots' => TelegramBot::with('account')->orderByDesc('id')->get()])];
    }

    protected function registerWebhook(Account $account, TelegramBot $bot, TelegramApi $api): void
    {
        $api->setWebhook(url('api/v1/telegram/webhooks/' . $account->id), $bot->webhook_secret);
    }

    protected function tenantOptions(): array
    {
        if (!class_exists(\Aero\Sites\Models\Tenant::class)) {
            return [];
        }

        return ['' => trans('aero.telegram::lang.bots.tenant_none')]
            + \Aero\Sites\Models\Tenant::orderBy('name')->pluck('name', 'id')->all();
    }
}
