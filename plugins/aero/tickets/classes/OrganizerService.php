<?php namespace Aero\Tickets\Classes;

use Aero\Tickets\Models\Event;
use Aero\Tickets\Models\Organizer;
use Aero\Tickets\Models\Settings;
use ApplicationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Ciclo de vida del organizador: solicitud → aprobado | rechazado; aprobado ⇄ suspendido. Toda decisión deja auditoría y avisa. */
class OrganizerService
{
    public static bool $writing = false;

    protected static ?array $blocked = null;

    public static function flush(): void
    {
        self::$blocked = null;
    }

    protected static function write(callable $fn)
    {
        $p = self::$writing;
        self::$writing = true;
        try {
            return $fn();
        } finally {
            self::$writing = $p;
        }
    }

    /** Perfil del organizador (se crea al primer uso: pendiente si se exige aprobación, aprobado si no). */
    public static function ensure(int $tenantId): Organizer
    {
        return Organizer::where('tenant_id', $tenantId)->first() ?: self::write(function () use ($tenantId) {
            $needs = Settings::requireOrganizerApproval();
            $o = new Organizer();
            $o->tenant_id = $tenantId;
            $o->status = $needs ? 'pending' : 'approved';
            $o->requested_at = now();
            if (!$needs) {
                $o->decided_at = now();
                $o->decided_by_name = 'Automática (no se exige aprobación)';
            }
            $o->save();
            Audit::record('organizer.created', 'organizer', $o->id, ['tenant_id' => $tenantId, 'actor' => ['type' => 'system', 'id' => null, 'name' => 'Sistema'], 'note' => 'Estado inicial: ' . $o->status]);

            return $o;
        });
    }

    protected static function tenantActive(int $tenantId): bool
    {
        try {
            if (!class_exists(\Aero\Sites\Models\Tenant::class) || !Schema::hasTable('aero_sites_tenants')) {
                return true;
            }
            $t = \Aero\Sites\Models\Tenant::find($tenantId);

            return !$t || $t->status === 'active';
        } catch (\Throwable $e) {
            return true;
        }
    }

    /** Motivo por el que NO puede publicar eventos nuevos (null = puede). */
    public static function publishBlock(?int $tenantId): ?string
    {
        if (!$tenantId) {
            return 'Falta el espacio de trabajo.';
        }
        if (!self::tenantActive($tenantId)) {
            return 'Tu espacio de trabajo no está activo.';
        }
        $o = self::ensure($tenantId);
        $needs = Settings::requireOrganizerApproval();

        return match ($o->status) {
            'suspended' => 'Tu cuenta de organizador está suspendida' . ($o->decision_note ? ': ' . $o->decision_note : '.'),
            'rejected' => 'Tu solicitud de organizador fue rechazada' . ($o->decision_note ? ': ' . $o->decision_note : '.') . ' Corrige tus datos y solicítala de nuevo.',
            'pending' => $needs ? 'Tu cuenta de organizador está pendiente de aprobación.' : null,
            default => null,
        };
    }

    /** Motivo por el que sus eventos YA publicados no operan (ventas, catálogo, ingreso). */
    public static function operationBlock(?int $tenantId): ?string
    {
        if (!$tenantId) {
            return null;
        }
        if (in_array($tenantId, self::blockedTenantIds(), true)) {
            return 'El organizador está suspendido o su espacio no está activo.';
        }

        return null;
    }

    /** Espacios cuyo evento no puede operar: organizador suspendido o espacio inactivo. */
    public static function blockedTenantIds(): array
    {
        if (self::$blocked !== null) {
            return self::$blocked;
        }
        $ids = Organizer::where('status', 'suspended')->pluck('tenant_id')->all();
        try {
            if (class_exists(\Aero\Sites\Models\Tenant::class) && Schema::hasTable('aero_sites_tenants')) {
                $ids = array_merge($ids, \Aero\Sites\Models\Tenant::where('status', '!=', 'active')->pluck('id')->all());
            }
        } catch (\Throwable $e) {
        }

        return self::$blocked = array_values(array_unique(array_map('intval', $ids)));
    }

    /** Contacto del organizador para avisos: su correo de contacto o el del dueño del espacio. */
    public static function contact(int $tenantId): array
    {
        $o = Organizer::where('tenant_id', $tenantId)->first();
        $email = $o?->contact_email;
        $name = $o?->contact_name;
        try {
            if (!$email && class_exists(\Aero\Sites\Models\Tenant::class)) {
                $u = \Backend\Models\User::find(\Aero\Sites\Models\Tenant::find($tenantId)?->backend_user_id);
                $email = $u?->email;
                $name = $name ?: trim($u?->first_name . ' ' . $u?->last_name);
            }
        } catch (\Throwable $e) {
        }

        return [$email, $name ?: 'organizador'];
    }

    protected static function notify(Organizer $o, string $code, array $vars = []): void
    {
        [$email, $name] = self::contact($o->tenant_id);
        if ($email) {
            Templates::send($email, $name, $code, $vars + ['contact_name' => $name]);
        }
    }

    /** El organizador completa/actualiza sus datos. Si estaba rechazado, vuelve a pendiente para su revisión. */
    public static function submit(Organizer $o, array $data): Organizer
    {
        self::write(function () use ($o, $data) {
            $before = $o->getAttributes();
            $o->fill($data);
            if ($o->status === 'rejected') {
                $o->status = 'pending';
                $o->requested_at = now();
                $o->decision_note = null;
            }
            $o->save();
            Audit::record('organizer.updated', 'organizer', $o->id, ['tenant_id' => $o->tenant_id, 'changes' => Audit::diff($before, $o->getAttributes(), ['legal_name', 'document', 'contact_name', 'contact_email', 'contact_phone', 'city', 'status']), 'actor' => ['type' => 'organizer', 'id' => \BackendAuth::getUser()?->id, 'name' => \BackendAuth::getUser()?->login]]);
        });

        return $o;
    }

    protected static function decide(Organizer $o, string $to, array $from, ?string $note, bool $noteRequired): Organizer
    {
        $note = $note !== null ? trim($note) : null;
        if ($noteRequired && mb_strlen((string) $note) < 5) {
            throw new ApplicationException('Escribe el motivo (se le informa al organizador y queda en el historial).');
        }
        $actor = Audit::actor();
        if ($actor['type'] !== 'admin') {
            throw new ApplicationException('Se necesita un administrador autenticado.');
        }

        return DB::transaction(function () use ($o, $to, $from, $note, $actor) {
            $row = Organizer::lockForUpdate()->findOrFail($o->id);
            if ($row->status === $to) {
                return $row;
            }
            if (!in_array($row->status, $from, true)) {
                throw new ApplicationException('Un organizador ' . mb_strtolower(Organizer::$statuses[$row->status]) . ' no puede pasar a ' . mb_strtolower(Organizer::$statuses[$to]) . '.');
            }
            $prev = $row->status;
            self::write(function () use ($row, $to, $note, $actor) {
                $row->status = $to;
                $row->decided_at = now();
                $row->decided_by = $actor['id'];
                $row->decided_by_name = $actor['name'];
                $row->decision_note = $note ? mb_substr($note, 0, 500) : null;
                $row->save();
            });
            self::flush();
            Audit::record('organizer.' . $to, 'organizer', $row->id, ['tenant_id' => $row->tenant_id, 'changes' => ['status' => [$prev, $to]], 'note' => $note]);
            \Event::fire('aero.tickets.organizer' . ucfirst($to), [$row]);
            $o->setRawAttributes($row->getAttributes(), true);

            return $row;
        });
    }

    public static function approve(Organizer $o, ?string $note = null): Organizer
    {
        $r = self::decide($o, 'approved', ['pending', 'rejected', 'suspended'], $note, false);
        self::notify($r, 'organizer_approved', ['note_html' => $note ? '<p>' . e($note) . '</p>' : '']);

        return $r;
    }

    public static function reject(Organizer $o, string $note): Organizer
    {
        $r = self::decide($o, 'rejected', ['pending'], $note, true);
        self::notify($r, 'organizer_rejected', ['note' => $note]);

        return $r;
    }

    public static function suspend(Organizer $o, string $note): Organizer
    {
        $r = self::decide($o, 'suspended', ['approved', 'pending'], $note, true);
        self::notify($r, 'organizer_suspended', ['note' => $note]);

        return $r;
    }

    public static function reinstate(Organizer $o, ?string $note = null): Organizer
    {
        $r = self::decide($o, 'approved', ['suspended'], $note, false);
        self::notify($r, 'organizer_approved', ['note_html' => $note ? '<p>' . e($note) . '</p>' : '']);

        return $r;
    }
}
