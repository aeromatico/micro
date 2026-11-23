<?php namespace Aero\Social\Models;

use Aero\Social\Classes\CurrentTenant;
use Model;

/**
 * Una publicación: texto + archivos, dirigida a varias cuentas. Cada cuenta
 * elegida se vuelve un PostTarget al publicar/programar.
 */
class Post extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_social_posts';

    protected $fillable = ['content', 'link', 'account_ids', 'scheduled_at'];

    protected $jsonable = ['account_ids'];

    public $attributes = ['status' => 'draft'];

    protected $dates = ['scheduled_at', 'published_at'];

    public $rules = [
        'content' => 'nullable|max:63000',
        'link'    => 'nullable|url|max:1024',
    ];

    public $customMessages = [
        'link.url' => 'El enlace debe ser una URL completa (https://…).',
    ];

    public $attachMany = [
        'media' => [\System\Models\File::class, 'public' => true, 'order' => 'sort_order'],
    ];

    public $hasMany = [
        'targets' => [PostTarget::class, 'key' => 'post_id'],
    ];

    public const STATUSES = [
        'draft'      => 'Borrador',
        'scheduled'  => 'Programado',
        'publishing' => 'Publicando',
        'published'  => 'Publicado',
        'partial'    => 'Publicado en parte',
        'failed'     => 'Falló',
        'cancelled'  => 'Cancelado',
    ];

    public function beforeCreate(): void
    {
        $this->tenant_id ??= CurrentTenant::id();
        $this->created_by ??= \BackendAuth::getUser()?->id;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'cancelled'], true);
    }

    public function isCancellable(): bool
    {
        return $this->status === 'scheduled'
            && !$this->targets()->whereNotIn('status', ['queued', 'cancelled'])->exists();
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getExcerptAttribute(): string
    {
        return mb_strimwidth(trim((string) $this->content), 0, 90, '…');
    }

    public function getNetworksSummaryAttribute(): string
    {
        $ids = (array) $this->account_ids;
        if (!$ids) {
            return '—';
        }

        return Account::whereIn('id', $ids)->get()->map->networkLabel()->unique()->implode(', ');
    }

    /** Opciones del formulario: solo cuentas activas del tenant (falla cerrado). */
    public function getAccountIdsOptions(): array
    {
        $tenantId = $this->tenant_id ?: CurrentTenant::id();

        return Account::forTenant($tenantId)->active()->orderBy('network')->orderBy('name')->get()
            ->mapWithKeys(fn ($a) => [$a->id => $a->displayName()])
            ->all();
    }

    /** Estado del post según sus destinos. */
    public function refreshStatus(): void
    {
        $statuses = $this->targets()->pluck('status')->all();
        if (!$statuses) {
            return;
        }

        $open = array_intersect($statuses, ['queued', 'publishing', 'processing']);
        $ok = count(array_keys($statuses, 'published', true));
        $bad = count(array_keys($statuses, 'failed', true));

        if ($open) {
            $status = count($open) === count($statuses) && !array_intersect($statuses, ['publishing', 'processing'])
                ? 'scheduled' : 'publishing';
        } elseif ($ok && !$bad) {
            $status = 'published';
        } elseif ($ok) {
            $status = 'partial';
        } elseif ($bad) {
            $status = 'failed';
        } else {
            $status = 'cancelled';
        }

        $this->status = $status;
        if ($ok && !$this->published_at) {
            $this->published_at = now();
        }
        $this->saveQuietly();
    }
}
