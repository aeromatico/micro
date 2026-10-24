<?php namespace Aero\Producer\Controllers;

use Aero\Producer\Classes\ElevenLabs;
use Aero\Producer\Models\Voice;
use Backend\Classes\Controller;
use BackendMenu;
use Flash;

/** Voces curadas: el tenant solo ve las que la plataforma activa (solo superadmin). */
class Voices extends Controller
{
    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.producer.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Producer', 'producer', 'voices');
    }

    /** Trae las voces de la llave activa; llegan inactivas y no pisan las ya curadas. */
    public function index_onImportElevenLabs()
    {
        $client = ElevenLabs::platform();
        if (!$client) {
            Flash::error('No hay una llave activa de ElevenLabs en Llaves de proveedores.');

            return;
        }

        try {
            $created = 0;
            foreach ($client->voices() as $v) {
                $voice = Voice::firstOrCreate(
                    ['provider' => 'elevenlabs', 'voice_id' => $v['id']],
                    ['name' => $v['name'], 'gender' => $v['gender'] ?? null, 'preview_url' => $v['preview_url'] ?? null, 'is_active' => false]
                );
                $voice->wasRecentlyCreated && $created++;
            }
        } catch (\Throwable $e) {
            Flash::error($e->getMessage());

            return;
        }

        $created ? Flash::success("{$created} voz(ces) importadas. Actívalas para mostrarlas a los tenants.") : Flash::warning('No hay voces nuevas.');

        return \Backend::redirect('aero/producer/voices');
    }
}
