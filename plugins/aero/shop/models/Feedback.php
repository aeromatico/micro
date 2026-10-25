<?php namespace Aero\Shop\Models;

use Model;

class Feedback extends Model
{
    public const STATUSES = [
        'internal' => 'Interna (solo para el negocio)',
        'pending'  => 'Pendiente de aprobación',
        'approved' => 'Pública',
        'hidden'   => 'Oculta',
    ];

    public $table = 'aero_shop_feedback';

    public $fillable = ['tenant_id', 'order_id', 'rating', 'comment', 'author_name', 'status'];

    public $belongsTo = [
        'order' => [Order::class],
    ];

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'approved');
    }

    /** Estado con que nace una opinión según la política de la tienda. */
    public static function statusForMode(string $mode): string
    {
        return match ($mode) {
            'public'    => 'approved',
            'moderated' => 'pending',
            default     => 'internal',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStarsAttribute(): string
    {
        return str_repeat('★', (int) $this->rating) . str_repeat('☆', 5 - (int) $this->rating);
    }
}
