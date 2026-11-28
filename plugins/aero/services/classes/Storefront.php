<?php namespace Aero\Services\Classes;

use Aero\Services\Models\Service;
use Backend;

/**
 * HTML del ícono "App Store" del menú inferior del panel (mismo punto de
 * extensión que Wallet: Aero\Credits\Plugin::bootNavbarWidget(), que lo
 * inserta ANTES de Wallet). Vive acá, no en Aero.Credits, porque es este
 * plugin el dueño de la pantalla; Credits solo lo intercala vía
 * class_exists() para garantizar el orden sin depender de en qué orden
 * arrancan los plugins.
 */
class Storefront
{
    /** Aviso bajo el formulario de login cuando el destino pendiente es una compra; '' en cualquier otro caso. */
    public static function signinNotice(): string
    {
        $path = (string) parse_url((string) session('url.intended'), PHP_URL_PATH);

        if (!preg_match('~aero/services/myservices/buy/([A-Za-z0-9_-]+)$~', $path, $m)) {
            return '';
        }

        $service = Service::active()->where('slug', $m[1])->first();

        if (!$service) {
            return '';
        }

        return '<div class="alert alert-info" style="margin-top:20px;clear:both">'
            . 'Inicia sesión para completar la compra de <strong>' . e($service->name) . '</strong>.<br>'
            . '¿Aún no tienes cuenta? <a href="' . e(url('comprar')) . '"><strong>Crear cuenta</strong></a>'
            . ' &middot; <a href="' . e(url('store')) . '">Volver a la tienda</a></div>';
    }

    public static function navbarItem(): string
    {
        return '<div class="toolbar-item fix-width" style="padding:0"><ul class="mainmenu-items" data-main-menu style="margin:0;padding:0">'
            . '<li class="mainmenu-item" title="App Store"><a href="' . e(Backend::url('aero/services/myservices')) . '">'
            . '<span class="nav-icon"><i class="icon-shopping-basket"></i></span><span class="nav-label">App Store</span></a></li></ul></div>';
    }
}
