<?php namespace Aero\Gym\Components;

use Aero\Gym\Classes\Progress;
use Aero\Gym\Models\GymSettings;
use Aero\Gym\Models\Measurement;
use Aero\Gym\Models\Member;
use Aero\Sites\Models\Tenant;
use Cms\Classes\ComponentBase;

/**
 * Historial de progreso corporal del socio en el micrositio: registra fecha,
 * peso, estatura y medidas; calcula el IMC y muestra la evolución. El socio
 * solo ve y edita lo SUYO (usuario → Member del tenant del dominio).
 */
class GymProgress extends ComponentBase
{
    public ?Tenant $tenant = null;
    public $user = null;
    public ?Member $member = null;

    public array $rows = [];       // más reciente primero
    public ?array $latest = null;
    public array $changes = [];
    public array $charts = [];
    public ?float $height = null;
    public array $fields = Progress::FIELDS;

    public function componentDetails(): array
    {
        return ['name' => 'Gimnasio (progreso)', 'description' => 'Peso, medidas e IMC del socio.'];
    }

    public function onRun()
    {
        if (!$this->boot()) {
            return $this->controller->run('404');
        }
        $this->load();
    }

    protected function boot(): bool
    {
        $this->tenant = $this->tenant ?: Tenant::resolveFromDomain(request()->getHost());
        $this->user = $this->user ?: \Auth::getUser();
        if (!$this->tenant || !GymSettings::isEnabled($this->tenant->id) || !GymPortal::siteHasGym($this->tenant->id)) {
            return false;
        }

        $this->member = $this->user
            ? Member::where('tenant_id', $this->tenant->id)->where('user_id', $this->user->id)->first()
            : null;

        return true;
    }

    protected function load(): void
    {
        if (!$this->member) {
            return;
        }

        $history = Progress::history($this->member->id);
        $this->height = $history->whereNotNull('height_cm')->last()?->height_cm !== null ? (float) $history->whereNotNull('height_cm')->last()->height_cm : null;

        $present = fn (Measurement $m) => [
            'id' => $m->id, 'date' => $m->measured_on->format('d/m/Y'), 'iso' => $m->measured_on->toDateString(),
            'weight' => (float) $m->weight_kg, 'height' => $m->height_cm !== null ? (float) $m->height_cm : null,
            'bmi' => $m->bmi, 'cat' => Measurement::bmiCategory($m->bmi),
            'values' => collect(Progress::FIELDS)->map(fn ($meta, $f) => $m->{$f} !== null ? (float) $m->{$f} : null)->all(),
            'notes' => $m->notes, 'own' => $m->source === 'member',
        ];

        $this->rows = $history->reverse()->map($present)->values()->all();
        $this->latest = $this->rows[0] ?? null;
        $this->changes = Progress::changes($history);

        $palette = ['weight_kg' => '#6366f1', 'waist_cm' => '#d9822b', 'body_fat_pct' => '#c0392b'];
        foreach ($palette as $f => $color) {
            [$label, $unit] = Progress::FIELDS[$f];
            $svg = Progress::chart($history->map(fn ($m) => [$m->measured_on->format('d/m/Y'), $m->{$f} !== null ? (float) $m->{$f} : null])->all(), $color, $unit);
            if ($svg) {
                $this->charts[] = ['label' => $label, 'unit' => $unit, 'svg' => $svg];
            }
        }
        $bmiSvg = Progress::chart($history->map(fn ($m) => [$m->measured_on->format('d/m/Y'), $m->bmi])->all(), '#2e9e5b', '');
        if ($bmiSvg) {
            $this->charts[] = ['label' => 'IMC', 'unit' => '', 'svg' => $bmiSvg];
        }
    }

    public function onSave()
    {
        if (!$this->boot() || !$this->member) {
            throw new \ApplicationException('Ingresa con tu cuenta de socio.');
        }

        $in = array_map(fn ($v) => is_string($v) ? trim(str_replace(',', '.', $v)) : $v, (array) post());
        $data = ['member_id' => $this->member->id, 'tenant_id' => $this->member->tenant_id, 'source' => 'member'];
        $data['measured_on'] = ($in['measured_on'] ?? '') ?: today()->toDateString();
        $data['notes'] = isset($in['notes']) && $in['notes'] !== '' ? mb_substr(strip_tags($in['notes']), 0, 500) : null;
        foreach (array_merge(['weight_kg', 'height_cm'], array_keys(Progress::FIELDS)) as $f) {
            $data[$f] = isset($in[$f]) && $in[$f] !== '' ? $in[$f] : null;
        }

        try {
            if (strtotime($data['measured_on']) === false || strtotime($data['measured_on']) > time()) {
                throw new \ApplicationException('La fecha no es válida o es futura.');
            }
            $existing = Measurement::where('member_id', $this->member->id)->whereDate('measured_on', $data['measured_on'])->first();
            if ($existing && $existing->source === 'staff') {
                throw new \ApplicationException('Esa fecha ya tiene una medición tomada por el gimnasio. Elige otra fecha.');
            }
            $m = $existing ?: new Measurement();
            $m->fill($data);
            $m->save();
        } catch (\October\Rain\Database\ModelException $e) {
            throw new \ApplicationException(implode(' ', $e->getErrors()->all()));
        }

        $this->load();
        \Flash::success('Medición guardada');

        return ['#gym-progress' => $this->renderPartial('@history')];
    }

    public function onDelete()
    {
        if (!$this->boot() || !$this->member) {
            throw new \ApplicationException('Ingresa con tu cuenta de socio.');
        }

        // Solo lo que el propio socio registró; lo del gimnasio queda como respaldo.
        Measurement::where('member_id', $this->member->id)->where('source', 'member')->where('id', (int) post('id'))->delete();

        $this->load();
        \Flash::success('Medición eliminada');

        return ['#gym-progress' => $this->renderPartial('@history')];
    }
}
