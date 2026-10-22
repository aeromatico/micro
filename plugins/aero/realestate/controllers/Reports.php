<?php namespace Aero\Realestate\Controllers;

use Aero\Realestate\Classes\Stats;
use Aero\Realestate\Models\Offer;
use Backend\Classes\Controller;
use BackendMenu;

/** Reportes de la administración: productividad por agente, demanda y comisiones, con exportación a CSV. */
class Reports extends Controller
{
    public $requiredPermissions = ['aero.realestate.use', 'aero.realestate.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Realestate', 'realestate', 'reports');
        $this->pageTitle = 'Reportes';
    }

    public function index(): void
    {
        $stats = Stats::forPanel((array) request()->query());
        $this->vars['stats'] = $stats;
        $this->vars['agents'] = $stats->byAgent();
        $this->vars['demand'] = $stats->demand();
        $this->vars['commissions'] = $stats->commissions();
        $this->vars['conversion'] = $stats->conversion();
        $this->vars['isAdmin'] = \Aero\Realestate\Classes\CurrentTenant::isAdmin();
        $this->vars['tenants'] = $this->vars['isAdmin'] && class_exists(\Aero\Sites\Models\Tenant::class)
            ? \Aero\Sites\Models\Tenant::orderBy('name')->pluck('name', 'id')->all() : [];
    }

    /** CSV (UTF-8 con BOM, abre bien en Excel) de la tabla elegida: agents | demand. */
    public function export($what = 'agents')
    {
        $stats = Stats::forPanel((array) request()->query());
        $rows = [];
        if ($what === 'demand') {
            $rows[] = ['Dimensión', 'Valor', 'Oportunidades', 'Cerradas'];
            foreach (['city' => 'Ciudad', 'zone' => 'Zona', 'type' => 'Tipo'] as $k => $label) {
                foreach ($stats->demand()[$k] as $r) {
                    $rows[] = [$label, $r['k'], $r['n'], $r['won']];
                }
            }
        } else {
            $rows[] = ['Agente', 'Oportunidades', 'Contactadas', 'Visitas realizadas', 'Cierres', 'Ventas', 'Alquileres', 'Anticréticos', 'Conversión %', 'Días promedio a cierre', 'Moneda', 'Comisión inmobiliaria', 'Comisión agente'];
            foreach ($stats->byAgent() as $a) {
                $base = [$a['name'], $a['leads'], $a['contacted'], $a['visits'], $a['won'], $a['by_modality']['sale'], $a['by_modality']['rent'], $a['by_modality']['anticretico'], $a['rate'] ?? '', $a['avg_days'] ?? ''];
                if (!$a['money']) {
                    $rows[] = array_merge($base, ['', '', '']);
                }
                foreach ($a['money'] as $cur => $m) {
                    $rows[] = array_merge($base, [$cur, number_format($m['agency'] ?? 0, 2, '.', ''), number_format($m['agent'] ?? 0, 2, '.', '')]);
                }
            }
        }

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($rows as $r) {
                fputcsv($out, array_map(fn ($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'" . $v : $v, $r), ',', '"', '\\'); // sin inyección de fórmulas
            }
            fclose($out);
        }, 'inmobiliaria-' . ($what === 'demand' ? 'demanda' : 'agentes') . '-' . now()->format('Ymd') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
