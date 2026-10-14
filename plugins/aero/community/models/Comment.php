<?php namespace Aero\Community\Models;

use Event;
use Model;

class Comment extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_community_comments';

    public $fillable = ['post_id', 'tenant_id', 'user_id', 'body', 'is_official'];

    public $rules = ['body' => 'required|max:4000'];

    protected $casts = ['is_official' => 'boolean'];

    public $belongsTo = ['post' => [Post::class, 'key' => 'post_id']];

    public function getAuthorNameAttribute(): string
    {
        if ($this->is_official) {
            return 'Equipo de la plataforma';
        }
        $user = $this->user_id ? \Backend\Models\User::find($this->user_id) : null;

        return $user ? trim($user->first_name . ' ' . $user->last_name) : 'Usuario';
    }

    public function afterCreate(): void
    {
        if ($post = $this->post) {
            $post->comments_count = $post->comments()->count();
            $post->last_activity_at = now();
            $post->saveQuietly();
            Event::fire('aero.community.commentAdded', [$this, $post]);
        }
    }
}
