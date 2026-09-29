<?php namespace Aero\Docs\Models;

use Model;

/**
 * Guía interactiva (HTML autocontenido) de un formulario del backend.
 * `html` es lo que ve el público; `pending_html` es la propuesta de la IA a
 * la espera de aprobación, y nunca pisa lo publicado.
 */
class Guide extends Model
{
    use \Aero\Docs\Traits\InDocsScope;
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_docs_guides';

    public $fillable = [
        'tenant_id', 'is_global', 'slug', 'title', 'plugin', 'form_ref', 'article_id', 'html',
        'pending_html', 'pending_at', 'source_hash', 'status', 'published_at', 'notes', 'sort_order',
    ];

    public $rules = [
        'title' => 'required',
        'slug'  => 'required|alpha_dash',
    ];

    protected $casts = [
        'is_global' => 'boolean',
    ];

    protected $dates = ['published_at', 'pending_at', 'reviewed_at'];

    public $belongsTo = [
        'article' => [Article::class, 'key' => 'article_id'],
    ];

    public function getStatusOptions(): array
    {
        return ['draft' => 'Borrador', 'published' => 'Publicada'];
    }

    public function getArticleIdOptions(): array
    {
        return Article::platform()->orderBy('title')->pluck('title', 'id')->all();
    }

    public function beforeSave(): void
    {
        if ($this->isDirty('html')) {
            $this->html_hash = $this->html ? sha1($this->html) : null;
        }
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')->whereNotNull('html')
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public function scopePendingReview($query)
    {
        return $query->whereNotNull('pending_html');
    }

    public function getHasPendingAttribute(): bool
    {
        return !empty($this->pending_html);
    }

    public function getUrlAttribute(): string
    {
        return url('guias/' . $this->slug);
    }

    /** URL del HTML para el iframe; el hash rompe la caché cuando el contenido cambia. */
    public function getEmbedUrlAttribute(): string
    {
        return url('guias/' . $this->slug . '/embed') . '?v=' . substr((string) $this->html_hash, 0, 12);
    }

    /** HTML que se muestra en la vista previa del backend: la propuesta si existe, si no lo publicado. */
    public function getPreviewHtmlAttribute(): ?string
    {
        return $this->pending_html ?: $this->html;
    }

    /** Promueve la propuesta a publicada. */
    public function approve(?int $userId = null): void
    {
        $html = $this->pending_html ?: $this->html;
        if (!$html) {
            throw new \ApplicationException('La guía no tiene HTML para publicar.');
        }

        $this->html         = $html;
        $this->pending_html = null;
        $this->pending_at   = null;
        $this->status       = 'published';
        $this->published_at = $this->published_at ?: now();
        $this->reviewed_by  = $userId;
        $this->reviewed_at  = now();
        $this->save();
    }

    /** Descarta la propuesta; lo publicado (si lo hay) queda intacto. */
    public function reject(?int $userId = null, ?string $reason = null): void
    {
        $this->pending_html = null;
        $this->pending_at   = null;
        $this->reviewed_by  = $userId;
        $this->reviewed_at  = now();
        if ($reason) {
            $this->notes = $reason;
        }
        $this->save();
    }
}
