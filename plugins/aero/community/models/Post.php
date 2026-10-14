<?php namespace Aero\Community\Models;

use Aero\Community\Classes\CurrentTenant;
use Aero\Community\Classes\Sla;
use BackendAuth;
use Event;
use Model;

class Post extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_community_posts';

    public $fillable = [
        'tenant_id', 'user_id', 'type', 'visibility', 'status', 'title', 'body', 'area',
        'official_response', 'is_pinned', 'is_locked', 'page_url', 'screenshot', 'context',
    ];

    public $rules = [
        'title' => 'required|max:180',
        'body'  => 'required|max:8000',
        'type'  => 'required|in:discussion,question,idea,bug,help',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'is_locked' => 'boolean',
    ];

    public $jsonable = ['context'];

    protected $dates = ['responded_at', 'sla_due_at', 'overdue_notified_at', 'last_activity_at'];

    public $hasMany = [
        'comments' => [Comment::class, 'key' => 'post_id'],
    ];

    public static function typeOptions(): array
    {
        return [
            'discussion' => 'Conversación',
            'question'   => 'Pregunta',
            'idea'       => 'Sugerencia',
            'bug'        => 'Error',
            'help'       => 'Necesito ayuda',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            'open'        => 'Recibida',
            'reviewing'   => 'En análisis',
            'planned'     => 'Planificada',
            'in_progress' => 'En desarrollo',
            'shipped'     => 'Implementada',
            'answered'    => 'Respondida',
            'declined'    => 'No por ahora',
            'closed'      => 'Cerrada',
        ];
    }

    public function getTypeOptions(): array
    {
        return self::typeOptions();
    }

    public function getStatusOptions(): array
    {
        return self::statusOptions();
    }

    public function getTypeLabelAttribute(): string
    {
        return self::typeOptions()[$this->type] ?? $this->type;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusOptions()[$this->status] ?? $this->status;
    }

    public function getTenantNameAttribute(): ?string
    {
        return CurrentTenant::name($this->tenant_id);
    }

    public function getAuthorNameAttribute(): string
    {
        $user = $this->user_id ? \Backend\Models\User::find($this->user_id) : null;
        $name = $user ? trim($user->first_name . ' ' . $user->last_name) : 'Usuario';

        // Otros negocios ven solo el nombre de pila; el dueño y el equipo, el negocio completo.
        $sameTenant = $this->tenant_id && $this->tenant_id === CurrentTenant::id();
        if (!CurrentTenant::isAdmin() && !$sameTenant) {
            return $user ? ($user->first_name ?: 'Usuario') : 'Usuario';
        }

        return $this->tenant_name ? "{$name} · {$this->tenant_name}" : $name;
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->sla_due_at && !$this->responded_at && $this->sla_due_at->isPast();
    }

    public function getSlaLabelAttribute(): string
    {
        if (!$this->sla_due_at) {
            return '';
        }
        if ($this->responded_at) {
            return 'Respondida';
        }

        return $this->is_overdue ? 'Vencida' : 'Respuesta en pocos minutos';
    }

    /** Lo que un usuario puede ver: lo público y lo privado de su tenant; el equipo todo. */
    public function scopeVisibleToCurrent($query)
    {
        if (CurrentTenant::isAdmin()) {
            return $query;
        }

        $tenantId = CurrentTenant::id();
        $userId = BackendAuth::getUser()?->id;

        return $query->where(function ($q) use ($tenantId, $userId) {
            $q->where('visibility', 'public');
            if ($tenantId) {
                $q->orWhere('tenant_id', $tenantId);
            } elseif ($userId) {
                $q->orWhere('user_id', $userId);
            }
        });
    }

    public function scopeAwaitingResponse($query)
    {
        return $query->whereNotNull('sla_due_at')->whereNull('responded_at');
    }

    public function canBeSeenByCurrent(): bool
    {
        return static::visibleToCurrent()->whereKey($this->id)->exists();
    }

    public function beforeCreate(): void
    {
        $this->sla_due_at = $this->sla_due_at ?: Sla::dueFor($this->type);
        $this->last_activity_at = now();
    }

    public function afterCreate(): void
    {
        Event::fire('aero.community.postCreated', [$this]);
    }

    /** El primer movimiento del equipo (respuesta o cambio de estado) cuenta como respuesta. */
    public function beforeUpdate(): void
    {
        $answered = $this->isDirty('official_response') && filled($this->official_response);
        $moved = $this->isDirty('status') && $this->status !== 'open';

        if (($answered || $moved) && !$this->responded_at && CurrentTenant::isAdmin()) {
            $this->responded_at = now();
            $this->responded_by = BackendAuth::getUser()?->id;
        }
    }

    public function afterUpdate(): void
    {
        if ($this->wasChanged('status') || $this->wasChanged('official_response')) {
            Event::fire('aero.community.postUpdated', [$this, $this->wasChanged('status')]);
        }
    }
}
