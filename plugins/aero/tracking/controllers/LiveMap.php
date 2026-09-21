<?php namespace Aero\Tracking\Controllers;

use Aero\Tracking\Classes\CurrentTenant;
use Aero\Tracking\Models\Asset;
use Aero\Tracking\Models\Job;
use Backend\Classes\Controller;
use BackendMenu;
use Response;

/** Mapa en vivo (Leaflet + OSM). Sondea `positions` cada 5 s; Reverb llega después. */
class LiveMap extends Controller
{
    public $requiredPermissions = ['aero.tracking.use', 'aero.tracking.superadmin'];

    public $pageTitle = 'Mapa en vivo';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Tracking', 'tracking', 'livemap');
    }

    public function index(): void
    {
    }

    /** Falla cerrado: sin tenant resoluble y sin ser superadmin no se ve nada. */
    public function positions()
    {
        $assets = Asset::where('is_active', true);

        if (!CurrentTenant::isAdmin()) {
            $id = CurrentTenant::id();
            $id ? $assets->where('tenant_id', $id) : $assets->whereRaw('1 = 0');
        }

        $assets = $assets->orderBy('name')->get();
        $jobs = Job::whereIn('status', Job::OPEN)->whereIn('asset_id', $assets->pluck('id'))->get()->groupBy('asset_id');

        return Response::json(['assets' => $assets->map(fn ($a) => $a->present() + [
            'open_jobs' => ($jobs[$a->id] ?? collect())->map(fn ($j) => $j->title ?: $j->reference ?: $j->uuid)->values(),
        ])->values()]);
    }
}
