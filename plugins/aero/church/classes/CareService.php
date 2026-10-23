<?php namespace Aero\Church\Classes;

use Aero\Church\Models\ChurchSettings;
use Aero\Church\Models\Member;
use Aero\Church\Models\Prayer;
use Aero\Church\Models\Visit;
use Illuminate\Support\Facades\DB;

/** Alta pública de visitantes y peticiones, e integración de un visitante como miembro. */
class CareService
{
    public function registerVisit(int $tenantId, array $d): Visit
    {
        $name = trim((string) ($d['name'] ?? ''));
        if ($name === '') {
            throw new ChurchException('Escribe tu nombre.');
        }
        if (empty($d['consent_data'])) {
            throw new ChurchException('Necesitamos tu autorización para guardar tus datos.');
        }
        if (empty($d['phone']) && empty($d['email'])) {
            throw new ChurchException('Déjanos un teléfono o un correo para poder saludarte.');
        }

        $visit = Visit::create([
            'tenant_id' => $tenantId, 'name' => mb_substr($name, 0, 120), 'phone' => $d['phone'] ?? null,
            'email' => !empty($d['email']) && filter_var($d['email'], FILTER_VALIDATE_EMAIL) ? $d['email'] : null,
            'how_heard' => mb_substr((string) ($d['how_heard'] ?? ''), 0, 200) ?: null,
            'interest' => mb_substr((string) ($d['interest'] ?? ''), 0, 1000) ?: null,
            'consent_data' => true, 'consent_messages' => !empty($d['consent_messages']),
            'visit_date' => now()->toDateString(), 'source' => 'qr',
        ]);

        \Event::fire('aero.church.visitRegistered', [$visit]);

        return $visit;
    }

    public function registerPrayer(int $tenantId, array $d): Prayer
    {
        $text = trim((string) ($d['request'] ?? ''));
        if ($text === '') {
            throw new ChurchException('Escribe tu petición.');
        }
        if (empty($d['consent_data'])) {
            throw new ChurchException('Necesitamos tu autorización para guardar tu petición.');
        }
        $wants = !empty($d['wants_contact']);
        if ($wants && empty($d['phone'])) {
            throw new ChurchException('Para que te contactemos necesitamos un teléfono.');
        }

        $prayer = Prayer::create([
            'tenant_id' => $tenantId, 'name' => mb_substr(trim((string) ($d['name'] ?? '')), 0, 120) ?: null,
            'phone' => $wants || !empty($d['phone']) ? ($d['phone'] ?? null) : null,
            'request' => mb_substr($text, 0, 2000), 'is_private' => !empty($d['is_private']),
            'wants_contact' => $wants, 'consent_data' => true, 'source' => 'web',
        ]);

        // El evento lleva solo ids: la petición no viaja por canales generales.
        \Event::fire('aero.church.prayerRequested', [$prayer]);

        return $prayer;
    }

    /** Convierte al visitante en miembro nuevo (una sola vez). */
    public function integrate(Visit $v): Member
    {
        return DB::transaction(function () use ($v) {
            $row = Visit::whereKey($v->id)->lockForUpdate()->firstOrFail();
            if ($row->member_id) {
                throw new ChurchException('Este visitante ya es miembro.');
            }

            $parts = preg_split('/\s+/', trim($row->name), 2);
            $member = Member::create([
                'tenant_id' => $row->tenant_id, 'branch_id' => $row->branch_id,
                'first_name' => $parts[0], 'last_name' => $parts[1] ?? null,
                'phone' => $row->phone, 'email' => $row->email, 'status' => 'new',
                'consent_messages' => $row->consent_messages, 'consent_data' => $row->consent_data,
                'notes' => $row->interest,
            ]);

            $row->member_id = $member->id;
            $row->status = 'integrated';
            $row->next_action_date = null;
            $row->save();
            $v->setRawAttributes($row->getAttributes(), true);

            \Event::fire('aero.church.visitIntegrated', [$row, $member]);

            return $member;
        });
    }

    /** Estadísticas simples de visitantes de un periodo. */
    public function visitStats(string $from, string $to, ?int $tenantId): array
    {
        $q = $tenantId ? Visit::forTenant($tenantId) : Visit::visible();
        $q->whereDate('visit_date', '>=', $from)->whereDate('visit_date', '<=', $to);
        $by = (clone $q)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->all();
        $total = array_sum($by);
        $integrated = $by['integrated'] ?? 0;

        return ['total' => $total, 'by_status' => $by, 'integrated' => $integrated, 'rate' => $total ? round($integrated / $total * 100, 1) : 0.0];
    }
}
