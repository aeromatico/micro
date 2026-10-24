<?php namespace Aero\Producer\Controllers;

use Aero\Producer\Models\Style;
use Backend\Classes\Controller;
use BackendMenu;

/** Galería de estilos: el tenant elige uno y pasa al formulario del pedido. */
class Studio extends Controller
{
    public $requiredPermissions = ['aero.producer.use', 'aero.producer.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Producer', 'producer', 'studio');
        $this->pageTitle = 'Nuevo video';
    }

    public function index(): void
    {
        $this->vars['styles'] = Style::active()->get();
    }
}
