<?php namespace Aero\Social\Models;

use Model;

/** La publicación de un post en UNA cuenta: estado, enlace, error y cobro. */
class PostTarget extends Model
{
    public $table = 'aero_social_post_targets';

    protected $guarded = ['id'];

    protected $jsonable = ['state'];

    protected $dates = ['run_at', 'published_at', 'refunded_at'];

    public $belongsTo = [
        'post'    => [Post::class, 'key' => 'post_id'],
        'account' => [Account::class, 'key' => 'account_id'],
    ];

    public const STATUSES = [
        'queued'     => 'En cola',
        'publishing' => 'Publicando',
        'processing' => 'Procesando',
        'published'  => 'Publicado',
        'failed'     => 'Falló',
        'cancelled'  => 'Cancelado',
    ];

    /** Toma el destino de forma atómica: solo un worker lo publica. */
    public static function claim(int $id, string $from): bool
    {
        return (bool) static::where('id', $id)->where('status', $from)
            ->update(['status' => 'publishing', 'updated_at' => now()]);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
