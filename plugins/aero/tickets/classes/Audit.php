<?php namespace Aero\Tickets\Classes;

use Aero\Tickets\Models\Audit as AuditRow;

/** Escribe la auditoría de la plataforma. Nunca rompe la operación que audita. */
class Audit
{
    public static function actor(): array
    {
        $u = \BackendAuth::getUser();

        return $u ? ['type' => 'admin', 'id' => $u->id, 'name' => trim($u->first_name . ' ' . $u->last_name) ?: $u->login] : ['type' => 'system', 'id' => null, 'name' => 'Sistema'];
    }

    /** {campo: [antes, después]} solo de los campos que cambiaron. */
    public static function diff(array $before, array $after, array $fields): array
    {
        $out = [];
        foreach ($fields as $f) {
            $b = $before[$f] ?? null;
            $a = $after[$f] ?? null;
            $norm = fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d H:i:s') : (is_bool($v) ? (int) $v : $v);
            if ((string) $norm($b) !== (string) $norm($a)) {
                $out[$f] = [$norm($b), $norm($a)];
            }
        }

        return $out;
    }

    /**
     * @param array $o tenant_id?, event_id?, changes?, note?, actor?
     */
    public static function record(string $action, string $subjectType, ?int $subjectId, array $o = []): void
    {
        try {
            $actor = $o['actor'] ?? self::actor();
            AuditRow::create([
                'tenant_id' => $o['tenant_id'] ?? null, 'event_id' => $o['event_id'] ?? null, 'subject_type' => $subjectType, 'subject_id' => $subjectId,
                'action' => $action, 'actor_type' => $actor['type'], 'actor_id' => $actor['id'] ?? null, 'actor_name' => $actor['name'] ?? null,
                'changes' => ($o['changes'] ?? []) ?: null, 'note' => isset($o['note']) ? mb_substr((string) $o['note'], 0, 500) : null,
            ]);
        } catch (\Throwable $e) {
            \Log::error('aero.tickets: no se pudo escribir la auditoría (' . $action . '): ' . $e->getMessage());
        }
    }
}
