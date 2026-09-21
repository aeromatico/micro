<?php namespace Aero\Crm\Models;

use Aero\Crm\Classes\TenantUsers;
use Aero\Sites\Models\Tenant;
use Aero\Sites\Models\TenantUser;
use Backend\Models\User;
use Db;
use Model;

/**
 * Ticket de soporte. tenant_id NULL = mesa de ayuda de la plataforma; con
 * valor = la del tenant. Numeración correlativa por ámbito (seq).
 */
class Ticket extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Purgeable;

    public const OPEN = 'open', PENDING = 'pending', RESOLVED = 'resolved', CLOSED = 'closed';

    public $table = 'aero_crm_tickets';

    public $fillable = [
        'tenant_id', 'origin_tenant_id', 'subject', 'description', 'department_id', 'contact_id',
        'frontend_user_id', 'access_token', 'unread_for_agent', 'last_customer_reply_at',
        'requester_name', 'requester_email', 'requester_phone',
        'assigned_to', 'status', 'priority', 'source', 'new_reply', 'reply_internal',
    ];

    public $attributes = ['status' => self::OPEN, 'priority' => 'normal', 'source' => 'manual'];

    public $rules = [
        'tenant_id'     => 'nullable|exists:aero_sites_tenants,id',
        'subject'       => 'required|max:255',
        'department_id' => 'required|exists:aero_crm_departments,id',
        'status'        => 'in:open,pending,resolved,closed',
        'priority'      => 'in:low,normal,high,urgent',
        'requester_email' => 'nullable|email',
    ];

    public $customMessages = ['department_id.required' => 'Elegí el departamento.'];

    /** Campos de formulario que no son columnas (respuesta nueva). */
    protected $purgeable = ['new_reply', 'reply_internal'];

    protected $dates = ['first_response_at', 'closed_at'];

    public $belongsTo = [
        'tenant'       => [Tenant::class],
        'originTenant' => [Tenant::class, 'key' => 'origin_tenant_id'],
        'department'   => [Department::class],
        'contact'      => [Contact::class],
        'assignee'     => [User::class, 'key' => 'assigned_to'],
        'frontendUser' => [\RainLab\User\Models\User::class, 'key' => 'frontend_user_id'],
    ];

    public $hasMany = [
        'replies' => [TicketReply::class, 'order' => 'created_at asc'],
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $t) {
            $t->seq = 1 + (int) Db::table('aero_crm_tickets')
                ->when($t->tenant_id, fn ($q) => $q->where('tenant_id', $t->tenant_id), fn ($q) => $q->whereNull('tenant_id'))
                ->lockForUpdate()
                ->max('seq');
        });

        static::saving(function (self $t) {
            foreach (['assigned_to', 'contact_id'] as $col) {
                if ($t->{$col} === '' || $t->{$col} === '0') {
                    $t->{$col} = null;
                }
            }

            $done = in_array($t->status, [self::RESOLVED, self::CLOSED], true);
            $t->closed_at = $done ? ($t->closed_at ?: now()) : null;
        });
    }

    public static function statusOptions(): array
    {
        return [self::OPEN => 'Abierto', self::PENDING => 'En espera', self::RESOLVED => 'Resuelto', self::CLOSED => 'Cerrado'];
    }

    public static function priorityOptions(): array
    {
        return ['low' => 'Baja', 'normal' => 'Normal', 'high' => 'Alta', 'urgent' => 'Urgente'];
    }

    public function getStatusOptions(): array { return static::statusOptions(); }
    public function getPriorityOptions(): array { return static::priorityOptions(); }

    public function getNumberAttribute(): string
    {
        return '#' . str_pad((string) $this->seq, 4, '0', STR_PAD_LEFT);
    }

    public function getDepartmentIdOptions(): array
    {
        return Department::active()
            ->inScope($this->exists ? $this->tenant_id : TenantUsers::currentTenantId())
            ->orderBy('name')->pluck('name', 'id')->all();
    }

    /** Solo integrantes del departamento elegido (o del ámbito si aún no hay). */
    public function getAssignedToOptions(): array
    {
        $dept = $this->department_id ? Department::find($this->department_id) : null;

        if ($dept) {
            $ids = array_merge($dept->users()->pluck('backend_users.id')->all(), array_filter((array) $this->assigned_to));

            return array_intersect_key(TenantUsers::options($dept->tenant_id, $ids), array_flip($ids));
        }

        return [];
    }

    public function getContactIdOptions(): array
    {
        $tenantId = $this->exists ? $this->tenant_id : TenantUsers::currentTenantId();

        return Contact::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId), fn ($q) => $q->whereRaw('1 = 0'))
            ->orderBy('first_name')->get()
            ->mapWithKeys(fn ($c) => [$c->id => $c->full_name])->all();
    }

    /**
     * Tickets que ve un usuario en un ámbito. Ven todo: superadmin, dueño del
     * tenant y colaboradores con rol admin. El resto solo lo de sus
     * departamentos o lo asignado a ellos.
     */
    public function scopeVisibleTo($query, User $user, ?int $tenantId)
    {
        $tenantId ? $query->where('tenant_id', $tenantId) : $query->whereNull('tenant_id');

        if (static::seesEverything($user, $tenantId)) {
            return $query;
        }

        $deptIds = Db::table('aero_crm_department_user')->where('user_id', $user->id)->pluck('department_id');

        return $query->where(fn ($q) => $q->whereIn('department_id', $deptIds)->orWhere('assigned_to', $user->id));
    }

    public static function seesEverything(User $user, ?int $tenantId): bool
    {
        if ($user->is_superuser) {
            return true;
        }

        if (!$tenantId) {
            return false;
        }

        return (int) Tenant::where('id', $tenantId)->value('backend_user_id') === (int) $user->id
            || TenantUser::where('tenant_id', $tenantId)->where('user_id', $user->id)->where('role', 'admin')->exists();
    }
}
