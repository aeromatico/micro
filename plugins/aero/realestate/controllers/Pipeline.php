<?php namespace Aero\Realestate\Controllers;

use Aero\Realestate\Classes\LeadService;
use Aero\Realestate\Classes\RealestateException;
use Aero\Realestate\Models\Lead;
use Aero\Realestate\Models\Visit;
use Backend\Classes\Controller;
use BackendMenu;

/** Embudo comercial: tablero por etapa; las oportunidades se arrastran de una columna a otra. */
class Pipeline extends Controller
{
    public $requiredPermissions = ['aero.realestate.use', 'aero.realestate.agent', 'aero.realestate.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Realestate', 'realestate', 'pipeline');
    }

    /** Columnas del tablero con sus tarjetas (las cerradas, solo los últimos 30 días). */
    protected function board(): array
    {
        $board = [];
        foreach (Lead::$stages as $k => $label) {
            $q = Lead::visible()->where('stage', $k)->with(['client', 'property', 'agent'])->orderByRaw('next_follow_up_at IS NULL, next_follow_up_at')->orderByDesc('id');
            if (in_array($k, ['closed_won', 'closed_lost'], true)) {
                $q->where('closed_at', '>=', now()->subDays(30));
            }
            $board[$k] = ['label' => $label, 'total' => (clone $q)->count(), 'items' => $q->limit(25)->get()];
        }

        return $board;
    }

    public function index(): void
    {
        $this->pageTitle = 'Embudo comercial';
        $this->vars['board'] = $this->board();
        $this->vars['visits'] = Visit::visible()->upcoming()->with(['property', 'client', 'agent'])->orderBy('starts_at')->limit(15)->get();
    }

    /**
     * Mueve una oportunidad a otra etapa (arrastrar y soltar o el selector de la tarjeta).
     * Las reglas son las de siempre (LeadService): perder pide motivo y cerrar la operación se hace desde la oportunidad.
     */
    public function onMove()
    {
        $lead = Lead::visible()->findOrFail((int) post('id'));
        try {
            app(LeadService::class)->moveTo($lead, (string) post('stage'), ($n = trim((string) post('note'))) !== '' ? $n : null);
        } catch (RealestateException $e) {
            throw new \ApplicationException($e->getMessage());
        }

        // Se devuelve el tablero tal como quedó en el servidor (cuenta de cada columna, orden y seguimientos).
        return ['#pipeline-board' => $this->makePartial('board', ['board' => $this->board()])];
    }
}
