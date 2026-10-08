<?php namespace Aero\Credits\Controllers;

use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;

class CreditCoupons extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.credits.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Credits', 'credits', 'creditcoupons');
    }

    public function formBeforeCreate($model)
    {
        $model->created_by = BackendAuth::getUser()->id;
    }
}
