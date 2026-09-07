<?php namespace Aero\Chatbots\Controllers;

use Backend\Classes\Controller;
use BackendMenu;

/**
 * Catálogo global de modelos de IA disponibles para los bots (menú
 * "Configuración"). A diferencia de Bots, acá NO hay scoping por tenant:
 * solo el superadmin (permiso `aero.chatbots.superadmin`) puede entrar.
 */
class AiModels extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.chatbots.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Chatbots', 'chatbots', 'configuracion');
    }
}
