<?php namespace Aero\Church\Classes;

use Aero\Church\Models\Announcement;
use Aero\Church\Models\Member;
use Illuminate\Support\Collection;

/**
 * Quién recibe un comunicado. Reglas de privacidad (no negociables):
 *  - solo con consentimiento de mensajes y teléfono;
 *  - un MENOR no recibe nada en su teléfono: el aviso va al tutor, si el tutor consintió;
 *  - un teléfono recibe una sola vez (familias que comparten número).
 */
class Recipients
{
    /** @return array{recipients: Collection, skipped: int} cada recipient = [member_id, phone, name] */
    public function resolve(int $tenantId, array $audience): array
    {
        $members = $this->segment($tenantId, $audience)->with('guardian')->get();

        $out = collect();
        $skipped = 0;
        foreach ($members as $m) {
            $target = $this->target($m);
            if (!$target) {
                $skipped++;
                continue;
            }
            if ($out->has($target['phone'])) {
                continue; // mismo teléfono: ya está en la lista
            }
            $out->put($target['phone'], ['member_id' => $m->id] + $target);
        }

        return ['recipients' => $out->values(), 'skipped' => $skipped];
    }

    public function forAnnouncement(Announcement $a): array
    {
        return $this->resolve((int) $a->tenant_id, (array) $a->audience);
    }

    /** Teléfono y nombre al que se escribe por este miembro, o null si no se le puede escribir. */
    public function target(Member $m): ?array
    {
        $holder = $m->is_minor ? $m->guardian : $m;
        if (!$holder || !$holder->consent_messages) {
            return null;
        }
        if ($m->is_minor && $holder->is_minor) {
            return null;
        }

        $phone = preg_replace('/\D+/', '', (string) $holder->phone);

        return $phone ? ['phone' => $phone, 'name' => $holder->first_name] : null;
    }

    protected function segment(int $tenantId, array $audience)
    {
        $q = Member::forTenant($tenantId);

        $statuses = array_values(array_intersect((array) ($audience['statuses'] ?? []), array_keys(Member::STATUSES)));
        $q->whereIn('status', $statuses ?: ['active', 'new']);

        if (!empty($audience['member_ids'])) {
            $q->whereIn('id', array_map('intval', (array) $audience['member_ids']));
        }
        if (!empty($audience['branch_id'])) {
            $q->where('branch_id', (int) $audience['branch_id']);
        }
        if (!empty($audience['group_id'])) {
            $gid = (int) $audience['group_id'];
            $q->whereIn('id', \Aero\Church\Models\GroupMember::where('tenant_id', $tenantId)->where('group_id', $gid)->whereNull('left_at')->select('member_id'));
        }
        if (!empty($audience['event_id'])) {
            $eid = (int) $audience['event_id'];
            // Inscritos al evento: ignora el filtro de estado (el aviso es para quienes se inscribieron).
            $q = Member::forTenant($tenantId)->whereIn('id', \Aero\Church\Models\EventRegistration::where('tenant_id', $tenantId)
                ->where('event_id', $eid)->where('status', 'registered')->whereNotNull('member_id')->select('member_id'));
        }

        return $q;
    }
}
