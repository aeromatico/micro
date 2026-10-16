<?php namespace Aero\Invoicing;

use Backend;
use System\Classes\PluginBase;

/**
 * Facturación electrónica de Bolivia (SIAT). Cada tenant factura con su(s)
 * NIT(s); por detrás habla con un proveedor (hoy Floogos). Independiente:
 * no exige otros plugins; Credits, Finance y Notify se acoplan por eventos
 * aero.invoicing.invoiceIssued / invoiceRejected / invoiceVoided / invoiceFailed.
 */
class Plugin extends PluginBase
{
    public function pluginDetails(): array
    {
        return [
            'name'        => 'Facturación',
            'description' => 'Facturación electrónica de Bolivia (SIAT): emisores por tenant, facturas, CUFD y anulación.',
            'author'      => 'Aero',
            'icon'        => 'icon-file-text',
        ];
    }

    public function registerPermissions(): array
    {
        return [
            'aero.invoicing.use' => [
                'tab'   => 'Facturación',
                'label' => 'Usar Facturación: sus emisores, emitir y anular sus facturas',
            ],
            'aero.invoicing.superadmin' => [
                'tab'   => 'Facturación',
                'label' => 'Administrar Facturación: driver, ambiente, credenciales del proveedor y datos de todos los tenants',
            ],
        ];
    }

    public function registerNavigation(): array
    {
        $perms = ['aero.invoicing.use', 'aero.invoicing.superadmin'];

        return [
            'invoicing' => [
                'label'       => 'Facturación',
                'url'         => Backend::url('aero/invoicing/invoices'),
                'icon'        => 'icon-file-text',
                'iconSvg'     => null,
                'permissions' => $perms,
                'order'       => 525,
                'sideMenu'    => [
                    'invoices' => ['label' => 'Facturas', 'icon' => 'icon-file-text', 'url' => Backend::url('aero/invoicing/invoices'), 'permissions' => $perms],
                    'issuers'  => ['label' => 'Emisores', 'icon' => 'icon-building', 'url' => Backend::url('aero/invoicing/issuers'), 'permissions' => $perms],
                ],
            ],
        ];
    }
}
