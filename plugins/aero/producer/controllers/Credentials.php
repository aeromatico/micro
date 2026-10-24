<?php namespace Aero\Producer\Controllers;

use Aero\Producer\Classes\ElevenLabs;
use Aero\Producer\Classes\ScopesToTenant;
use Aero\Producer\Models\Credential;
use Backend\Classes\Controller;
use BackendMenu;
use Flash;

class Credentials extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.producer.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Producer', 'producer', 'credentials');
    }

    /** Por ahora solo la plataforma guarda llaves; los tenants usarán la suya pagando en créditos. */
    public function formBeforeCreate($model): void
    {
        $model->tenant_id = null;
        $model->provider = $model->provider ?: 'elevenlabs';
    }

    /** Gasta 1 unidad de cuota y guarda el resultado en la llave. */
    public function update_onTest($recordId = null)
    {
        $credential = $this->scopeToTenant(Credential::query())->findOrFail($recordId);

        try {
            $key = $credential->plainKey();
            if (!$key) {
                throw new \RuntimeException('La llave está vacía.');
            }
            if ($credential->provider === 'heygen') {
                $balance = (new \Aero\Producer\Classes\HeyGen($key))->balance();
                $message = 'La llave funciona (HeyGen responde). Saldo: US$' . number_format($balance, 2) . '.';
            } else {
                (new ElevenLabs($key))->test();
                $message = 'La llave funciona (ElevenLabs responde).';
            }
            $credential->forceFill(['last_status' => 'ok', 'last_message' => $message, 'last_checked_at' => now()])->save();
            Flash::success('La llave funciona.');
        } catch (\Throwable $e) {
            $credential->forceFill(['last_status' => 'error', 'last_message' => mb_substr($e->getMessage(), 0, 400), 'last_checked_at' => now()])->save();
            Flash::error($e->getMessage());
        }

        return \Backend::redirect('aero/producer/credentials/update/' . $credential->id);
    }
}
