<?php namespace Aero\Workspaces\Components;

use Aero\Workspaces\Classes\Workspace;
use Aero\Workspaces\Models\Settings;
use Cms\Classes\ComponentBase;

/**
 * Catálogo PÚBLICO de la Oficina IA (themes/master → /workspaces/agentes). Solo
 * muestra: contratar se hace eligiendo un plan (el botón lleva a los precios) y
 * desde el panel del tenant, así que aquí no hay ninguna acción que escriba.
 *
 * Expone lo mismo que ve un tenant en su Mercado (Workspace::agent): nunca el
 * prompt de sistema ni el modelo.
 */
class AgentsMarket extends ComponentBase
{
    protected ?array $cache = null;

    public function componentDetails(): array
    {
        return [
            'name'        => 'Catálogo público de agentes',
            'description' => 'Muestra el catálogo de agentes de IA; contratar lleva a los planes.',
        ];
    }

    public function market(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $agents = Workspace::market(0); // sin tenant: nadie «lo tiene contratado»

        return $this->cache = [
            'agents'   => $agents,
            'charging' => Settings::chargeEnabled(),
            'avatars'  => url('plugins/aero/workspaces/assets/img/avatars.png'),
            'cats'     => array_values(array_unique(array_filter(array_column($agents, 'category')))),
        ];
    }
}
