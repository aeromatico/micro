<?php namespace Aero\Social\Controllers;

use Aero\Social\Classes\Billing;
use Aero\Social\Classes\CurrentTenant;
use Aero\Social\Classes\Publisher;
use Aero\Social\Models\Account;
use Aero\Social\Models\Post;
use Aero\Social\Models\PostTarget;
use ApplicationException;
use Backend;
use Backend\Classes\Controller;
use BackendMenu;
use Flash;

/** Crear, programar y seguir publicaciones. El tenant solo ve las suyas. */
class Posts extends Controller
{
    use \Aero\Social\Classes\ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.social.use', 'aero.social.superadmin'];

    protected ?Post $savedPost = null;

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Social', 'social', 'posts');
    }

    public function formBeforeCreate($model): void
    {
        if (!CurrentTenant::id()) {
            throw new ApplicationException('Tu usuario no pertenece a ninguna cuenta: no puede crear publicaciones.');
        }
    }

    public function formBeforeSave($model): void
    {
        if ($model->exists && !$model->isEditable()) {
            throw new ApplicationException('Esta publicación ya se envió y no se puede editar.');
        }
    }

    public function formAfterSave($model): void
    {
        $this->savedPost = $model;
    }

    public function formExtendFields($form): void
    {
        $model = $form->model;
        if ($model->exists && !$model->isEditable()) {
            foreach ($form->getFields() as $field) {
                $field->disabled = true;
            }
        }
    }

    /** Datos para el partial de ayuda (cuentas conectadas y tarifas). */
    public function hintVars(): array
    {
        $accounts = Account::forTenant(CurrentTenant::id())->active()->get();

        return [
            'hasAccounts' => $accounts->isNotEmpty(),
            'tariffs'     => Billing::tariffs($accounts->pluck('network')->unique()->all()),
        ];
    }

    public function create_onPublish()
    {
        $this->asExtension('FormController')->create_onSave();
        $post = $this->savedPost;

        try {
            Publisher::schedule($post->fresh());
        } catch (ApplicationException $e) {
            // Queda como borrador para corregir; no se duplica al reintentar.
            Flash::error($e->getMessage());

            return Backend::redirect('aero/social/posts/update/' . $post->id);
        }

        return $this->published($post->fresh());
    }

    public function update_onPublish($recordId = null)
    {
        $this->asExtension('FormController')->update_onSave($recordId);
        $post = $this->formFindModelObject($recordId)->fresh();

        Publisher::schedule($post);

        return $this->published($post->fresh());
    }

    public function update_onCancelSchedule($recordId = null)
    {
        $post = $this->formFindModelObject($recordId);
        $count = Publisher::cancel($post);

        $count ? Flash::success('Programación cancelada y créditos devueltos. Puedes editarla y volver a publicar.')
               : Flash::warning('No había nada pendiente que cancelar.');

        return Backend::redirect('aero/social/posts/update/' . $post->id);
    }

    public function update_onRetryTarget($recordId = null)
    {
        $post = $this->formFindModelObject($recordId);
        $target = PostTarget::where('post_id', $post->id)->findOrFail((int) post('target_id'));

        Publisher::retry($target);
        Flash::success('Reintentando la publicación en ' . $target->account?->networkLabel() . '.');

        return Backend::redirect('aero/social/posts/update/' . $post->id);
    }

    /** Popup «Promocionar» de una publicación ya publicada en Facebook/Instagram. */
    public function update_onBoostForm($recordId = null)
    {
        $this->checkAdsAccess();
        $post = $this->formFindModelObject($recordId);
        $target = PostTarget::where('post_id', $post->id)->findOrFail((int) post('target_id'));

        return $this->makePartial('boost_form', [
            'target'     => $target,
            'adAccounts' => \Aero\Social\Models\AdAccount::forTenant($post->tenant_id)->active()->where('platform', 'meta')->get(),
        ]);
    }

    public function update_onBoost($recordId = null)
    {
        $this->checkAdsAccess();
        $post = $this->formFindModelObject($recordId);
        $target = PostTarget::where('post_id', $post->id)->findOrFail((int) post('target_id'));
        $adAccount = \Aero\Social\Models\AdAccount::forTenant($post->tenant_id)->findOrFail((int) post('ad_account_id'));

        try {
            $campaign = \Aero\Social\Classes\MetaAds::boost($adAccount, $target, [
                'daily_budget' => (float) str_replace(',', '.', (string) post('daily_budget')),
                'days'         => (int) post('days'),
                'country'      => (string) post('country', 'BO'),
                'age_min'      => (int) post('age_min', 18),
                'age_max'      => (int) post('age_max', 65),
            ]);
        } catch (\Aero\Social\Classes\SocialException $e) {
            if ($e->tokenInvalid) {
                $adAccount->markExpired($e->getMessage());
            }
            throw new ApplicationException($e->getMessage());
        }

        Flash::success('Promoción creada. Meta la revisa antes de mostrarla (suele tardar minutos).');

        return Backend::redirect('aero/social/ads?account=' . $campaign->ad_account_id);
    }

    protected function checkAdsAccess(): void
    {
        $user = \BackendAuth::getUser();
        if (!$user || !($user->hasAccess('aero.social.ads') || $user->hasAccess('aero.social.superadmin'))) {
            throw new ApplicationException('No tienes permiso para crear anuncios.');
        }
    }

    public function update_onRefreshTargets($recordId = null)
    {
        $post = $this->formFindModelObject($recordId);

        return ['#socTargets' => $this->makePartial('targets', ['post' => $post])];
    }

    protected function published(Post $post)
    {
        $post->status === 'scheduled' && $post->scheduled_at && $post->scheduled_at->isFuture()
            ? Flash::success('Publicación programada para el ' . $post->scheduled_at->format('d/m/Y H:i') . '.')
            : Flash::success('Publicando… el estado de cada red se actualiza abajo.');

        return Backend::redirect('aero/social/posts/update/' . $post->id);
    }
}
