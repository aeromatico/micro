<?php namespace Aero\Medical\Classes;

use Aero\Medical\Models\Consultation;
use Aero\Medical\Models\Diagnosis;
use Aero\Medical\Models\LabResult;
use Aero\Medical\Models\Order;
use Aero\Medical\Models\OrderItem;
use Aero\Medical\Models\Patient;
use Aero\Medical\Models\Prescription;
use Aero\Medical\Models\PrescriptionItem;
use Aero\Medical\Models\Vitals;

/**
 * Hoja de seguimiento: una columna por visita (más una por fecha con solo laboratorio) y
 * filas de mediciones, laboratorio, diagnósticos, receta, órdenes y firma. Solo las notas
 * FIRMADAS aportan datos; un borrador aparece como columna sin contenido. Los valores
 * fuera de los rangos del negocio llevan flag low/high.
 */
class FollowUpSheet
{
    public static function build(Patient $p, int $limit = 12): array
    {
        $tenantId = (int) $p->tenant_id;
        $mode = $p->kind;
        $species = $p->species;

        $notes = Consultation::where('tenant_id', $tenantId)->where('patient_id', $p->id)
            ->orderByDesc('consulted_at')->orderByDesc('id')->limit($limit)->get()->reverse()->values();
        $signedIds = $notes->where('status', 'signed')->pluck('id')->all();

        $vitals = Vitals::whereIn('consultation_id', $signedIds)->get()->keyBy('consultation_id');
        $dx = Diagnosis::whereIn('consultation_id', $signedIds)->orderBy('position')->get()->groupBy('consultation_id');
        $rx = Prescription::whereIn('consultation_id', $signedIds)->get()->keyBy('consultation_id');
        $rxItems = $rx->isEmpty() ? collect() : PrescriptionItem::whereIn('prescription_id', $rx->pluck('id'))->orderBy('position')->get()->groupBy('prescription_id');
        $ord = Order::whereIn('consultation_id', $signedIds)->get()->keyBy('consultation_id');
        $ordItems = $ord->isEmpty() ? collect() : OrderItem::whereIn('order_id', $ord->pluck('id'))->orderBy('position')->get()->groupBy('order_id');

        $columns = [];
        foreach ($notes as $n) {
            $columns[] = [
                'key' => 'n' . $n->id, 'date' => $n->consulted_at->copy()->startOfDay(), 'note' => $n,
                'signed' => $n->isSigned(), 'label' => $n->kind_label,
            ];
        }

        // Laboratorio: ventana de fechas de las visitas (+ resultados sueltos más recientes).
        $labQuery = LabResult::where('tenant_id', $tenantId)->where('patient_id', $p->id);
        $labs = $labQuery->orderBy('measured_on')->orderBy('id')->get();
        if ($columns) {
            $from = $columns[0]['date'];
            $labs = $labs->filter(fn ($l) => $l->measured_on->gte($from));
        } else {
            $labs = $labs->take(-$limit)->values();
        }
        $visitDays = collect($columns)->mapWithKeys(fn ($c) => [$c['date']->toDateString() => $c['key']])->all();
        foreach ($labs->pluck('measured_on')->map->toDateString()->unique() as $day) {
            if (!isset($visitDays[$day])) {
                $columns[] = ['key' => 'l' . $day, 'date' => \Carbon\Carbon::parse($day), 'note' => null, 'signed' => null, 'label' => 'Laboratorio'];
                $visitDays[$day] = 'l' . $day;
            }
        }
        usort($columns, fn ($a, $b) => [$a['date']->timestamp, $a['key']] <=> [$b['date']->timestamp, $b['key']]);

        $rows = [];

        // Mediciones
        foreach (VitalsSchema::fields($mode) as $name => $def) {
            $cells = [];
            $any = false;
            foreach ($columns as $col) {
                $v = $col['note'] && $col['signed'] ? ($vitals[$col['note']->id] ?? null) : null;
                $val = $v?->{$name};
                if ($val !== null && $val !== '') {
                    $any = true;
                    $cells[$col['key']] = ['text' => VitalsSchema::format($name, $val), 'flag' => isset($def['options']) ? null : Ranges::flag($tenantId, $mode, $species, $name, $val)];
                }
            }
            if ($any) {
                $rows[] = ['group' => 'Mediciones', 'label' => $def['label'], 'unit' => $def['unit'], 'cells' => $cells];
            }
        }

        // Laboratorio (una fila por analito, cada resultado en la columna de su fecha)
        $byAnalyte = $labs->groupBy(fn ($l) => $l->metricKey());
        foreach ($byAnalyte as $metric => $results) {
            $cells = [];
            foreach ($results as $l) {
                $key = $visitDays[$l->measured_on->toDateString()];
                $text = rtrim(rtrim(number_format((float) $l->value, 3, '.', ''), '0'), '.');
                $prev = $cells[$key]['text'] ?? null;
                $cells[$key] = ['text' => $prev ? $prev . ' / ' . $text : $text, 'flag' => Ranges::flag($tenantId, $mode, $species, (string) $metric, $l->value), 'lab_id' => $l->id];
            }
            $first = $results->first();
            $rows[] = ['group' => 'Laboratorio', 'label' => $first->analyte_name, 'unit' => $first->unit, 'cells' => $cells];
        }

        // Diagnósticos, receta, órdenes
        $text = function (string $group, string $label, callable $cell) use ($columns) {
            $cells = [];
            foreach ($columns as $col) {
                if ($col['note'] && $col['signed'] && ($t = $cell($col['note']))) {
                    $cells[$col['key']] = ['text' => $t, 'flag' => null];
                }
            }

            return $cells ? ['group' => $group, 'label' => $label, 'unit' => null, 'cells' => $cells] : null;
        };
        $rows[] = $text('Registro', 'Diagnósticos', fn ($n) => ($dx[$n->id] ?? collect())->map(fn ($d) => ($d->is_primary ? '★ ' : '') . $d->display . ' (' . mb_strtolower($d->certainty_label) . ')')->implode("\n"));
        $rows[] = $text('Registro', 'Receta', fn ($n) => isset($rx[$n->id]) ? ($rxItems[$rx[$n->id]->id] ?? collect())->map(fn ($i) => trim($i->medication . ' ' . $i->dose . ' ' . $i->frequency))->implode("\n") : '');
        $rows[] = $text('Registro', 'Órdenes', fn ($n) => isset($ord[$n->id]) ? ($ordItems[$ord[$n->id]->id] ?? collect())->pluck('exam_name')->implode("\n") : '');
        $rows = array_values(array_filter($rows));

        return ['columns' => $columns, 'rows' => $rows];
    }
}
