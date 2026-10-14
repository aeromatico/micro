<?php namespace Aero\Community\Controllers;

use Aero\Community\Classes\CurrentTenant;
use Aero\Community\Models\Comment;
use Aero\Community\Models\Post;
use Aero\Community\Models\Vote;
use ApplicationException;
use Backend;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;
use Illuminate\Support\Facades\Storage;

class Posts extends Controller
{
    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.community.use', 'aero.community.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Community', 'community', $this->activeMenuItem());
    }

    protected function activeMenuItem(): string
    {
        if (request('mine')) {
            return 'mine';
        }
        if (request('pending')) {
            return 'pending';
        }

        return request('type') === 'idea' ? 'ideas' : 'feed';
    }

    /** Lo público de la comunidad y lo privado de mi tenant; el equipo, todo. */
    public function listExtendQuery($query): void
    {
        $query->visibleToCurrent();

        if (request('mine')) {
            $userId = BackendAuth::getUser()?->id;
            $tenantId = CurrentTenant::id();
            $query->where(function ($q) use ($userId, $tenantId) {
                $q->where('user_id', $userId);
                if ($tenantId) {
                    $q->orWhere('tenant_id', $tenantId);
                }
            });
        } elseif (request('pending') && CurrentTenant::isAdmin()) {
            $query->awaitingResponse()->orderBy('sla_due_at');
        } elseif (request('type') === 'idea') {
            $query->where('type', 'idea')->where('visibility', 'public');
        } elseif (!CurrentTenant::isAdmin()) {
            $query->where('visibility', 'public')->whereIn('type', ['discussion', 'question']);
        }
    }

    public function formExtendQuery($query): void
    {
        $query->visibleToCurrent();
    }

    public function formExtendFields($form): void
    {
        if (!CurrentTenant::isAdmin()) {
            foreach (['status', 'official_response', 'is_pinned', 'is_locked'] as $name) {
                $form->removeField($name);
            }
            if ($field = $form->getField('type')) {
                $field->options = [
                    'discussion' => 'Conversación',
                    'question'   => 'Pregunta',
                    'idea'       => 'Sugerencia para la plataforma',
                ];
            }
        }
    }

    public function formBeforeCreate($model): void
    {
        $model->tenant_id = CurrentTenant::id();
        $model->user_id = BackendAuth::getUser()?->id;
        $model->visibility = 'public';
        $model->status = 'open';

        if (!CurrentTenant::isAdmin() && !in_array($model->type, ['discussion', 'question', 'idea'], true)) {
            throw new ApplicationException('Tipo no permitido.');
        }
    }

    /** Solo el equipo edita una publicación (estado y respuesta oficial). */
    public function formBeforeUpdate($model): void
    {
        $this->assertAdmin();
    }

    public function formBeforeSave($model): void
    {
        if ($model->exists) {
            $this->assertAdmin();
        }
    }

    protected function assertAdmin(): void
    {
        if (!CurrentTenant::isAdmin()) {
            throw new ApplicationException('Solo el equipo puede modificar una publicación.');
        }
    }

    protected function visiblePost(): Post
    {
        $post = Post::visibleToCurrent()->find((int) post('id'));
        if (!$post) {
            throw new ApplicationException('Publicación no disponible.');
        }

        return $post;
    }

    public function onVote()
    {
        $post = $this->visiblePost();
        $user = BackendAuth::getUser();

        $vote = Vote::where('post_id', $post->id)->where('user_id', $user->id)->first();
        if ($vote) {
            $vote->delete();
        } else {
            Vote::create(['post_id' => $post->id, 'user_id' => $user->id, 'tenant_id' => CurrentTenant::id()]);
        }

        $post->votes_count = Vote::where('post_id', $post->id)->count();
        $post->saveQuietly();

        return Backend::redirect('aero/community/posts/update/' . $post->id);
    }

    public function onComment()
    {
        $post = $this->visiblePost();
        if ($post->is_locked && !CurrentTenant::isAdmin()) {
            throw new ApplicationException('Esta conversación está cerrada.');
        }

        $comment = new Comment([
            'post_id'     => $post->id,
            'tenant_id'   => CurrentTenant::id(),
            'user_id'     => BackendAuth::getUser()?->id,
            'body'        => trim((string) post('body')),
            'is_official' => CurrentTenant::isAdmin(),
        ]);
        $comment->save();

        \Flash::success('Comentario publicado.');

        return Backend::redirect('aero/community/posts/update/' . $post->id);
    }

    /** Captura adjunta a un reporte: solo quien puede ver la publicación. */
    public function screenshot($id = null)
    {
        $post = Post::visibleToCurrent()->find((int) $id);
        if (!$post || !$post->screenshot || !Storage::disk('local')->exists($post->screenshot)) {
            abort(404);
        }

        return response()->file(Storage::disk('local')->path($post->screenshot), ['Content-Type' => 'image/png']);
    }
}
