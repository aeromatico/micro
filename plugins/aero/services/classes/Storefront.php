<?php namespace Aero\Services\Classes;

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
    public static function navbarItem(): string
    {
        return '<div class="toolbar-item fix-width" style="padding:0"><ul class="mainmenu-items" data-main-menu style="margin:0;padding:0">'
            . '<li class="mainmenu-item" title="App Store"><a href="' . e(Backend::url('aero/services/myservices')) . '">'
            . '<span class="nav-icon"><i class="icon-shopping-basket"></i></span><span class="nav-label">App Store</span></a></li></ul></div>';
    }
}
