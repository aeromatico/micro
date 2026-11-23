<?php namespace Aero\Social\Classes;

use Aero\Social\Jobs\PublishTarget;
use Aero\Social\Models\Account;
use Aero\Social\Models\Post;
use Aero\Social\Models\PostTarget;
use ApplicationException;
use Cache;
use DB;
use Event;
use Log;
use Throwable;

/**
 * Ciclo de vida de una publicación:
 *   schedule()  valida, crea un destino por cuenta y cobra (todo o nada)
 *   dispatchDue() (cron cada minuto) encola los destinos vencidos
 *   run()       publica un destino (o continúa uno en proceso)
 *   cancel()/retry()/sweep()
 */
class Publisher
{
    /** Minutos sin confirmación tras los que un destino «publicando» se da por fallido. */
    public const STUCK_MINUTES = 15;

    /** Revisiones máximas de un contenedor en proceso (≈ 10 min con 10 s). */
    public const MAX_CHECKS = 60;

    /** @return Account[] cuentas elegidas que siguen activas y son del tenant del post. */
    public static function accounts(Post $post): array
    {
        $ids = array_map('intval', (array) $post->account_ids);
        if (!$ids || !$post->tenant_id) {
            return [];
        }

        return Account::forTenant($post->tenant_id)->active()->whereIn('id', $ids)->get()->all();
    }

    /** Errores que impiden publicar (vacío = se puede). */
    public static function validate(Post $post): array
    {
        if (!$post->tenant_id) {
            return ['Tu usuario no pertenece a ninguna cuenta: no se puede publicar.'];
        }

        $accounts = self::accounts($post);
        if (!$accounts) {
            return ['Elige al menos una cuenta conectada en «Publicar en».'];
        }

        $media = Media::of($post);
        $errors = [];
        foreach (collect($accounts)->pluck('network')->unique() as $network) {
            $errors = array_merge($errors, Networks::driver($network)->validate($post, $media));
        }

        if ($post->scheduled_at && $post->scheduled_at->lt(now()->subMinutes(5))) {
            $errors[] = 'La fecha programada ya pasó. Déjala vacía para publicar ahora.';
        }

        return array_values(array_unique($errors));
    }

    /**
     * Publica ya o programa. Cobra cada destino; si falta saldo no se crea nada.
     *
     * @throws ApplicationException
     */
    public static function schedule(Post $post): void
    {
        if (!$post->isEditable()) {
            throw new ApplicationException('Esta publicación ya fue enviada.');
        }
        if ($errors = self::validate($post)) {
            throw new ApplicationException(implode("\n", $errors));
        }

        $runAt = $post->scheduled_at && $post->scheduled_at->isFuture() ? $post->scheduled_at : now();

        try {
            DB::transaction(function () use ($post, $runAt) {
                // Reintento tras cancelar: los destinos viejos ya están reembolsados.
                $post->targets()->where('status', 'cancelled')->delete();

                foreach (self::accounts($post) as $account) {
                    $target = PostTarget::create([
                        'tenant_id'  => $post->tenant_id,
                        'post_id'    => $post->id,
                        'account_id' => $account->id,
                        'network'    => $account->network,
                        'status'     => 'queued',
                        'run_at'     => $runAt,
                    ]);
                    Billing::charge($target);
                }

                $post->status = 'scheduled';
                $post->published_at = null;
                $post->saveQuietly();
            });
        } catch (\Aero\Credits\Classes\Exceptions\InsufficientCreditsException $e) {
            throw new ApplicationException('No te alcanzan los créditos para esta publicación. ' . $e->getMessage());
        }

        if ($runAt->lte(now())) {
            self::dispatchDue($post->id);
        }
    }

    /** Encola los destinos que ya toca publicar (de un post o de todos). */
    public static function dispatchDue(?int $postId = null): int
    {
        $query = PostTarget::where('status', 'queued')->where('run_at', '<=', now());
        if ($postId) {
            $query->where('post_id', $postId);
        }

        $count = 0;
        foreach ($query->limit(200)->pluck('id') as $id) {
            // No re-encolar mientras el job anterior sigue esperando en la cola.
            if (Cache::add("aero.social.dispatched.{$id}", 1, now()->addMinutes(5))) {
                PublishTarget::dispatch($id);
                $count++;
            }
        }

        return $count;
    }

    /** Publica (status queued) o continúa (status processing) un destino. */
    public static function run(int $targetId): void
    {
        $current = PostTarget::find($targetId);
        if (!$current || !in_array($current->status, ['queued', 'processing'], true)) {
            return;
        }
        if (!PostTarget::claim($targetId, $current->status)) {
            return; // otro worker lo tomó
        }

        $target = PostTarget::with(['post', 'account'])->find($targetId);
        $post = $target->post;
        $account = $target->account;

        try {
            if (!$post) {
                throw new SocialException('La publicación ya no existe.');
            }
            if (!$account || !$account->isActive()) {
                throw new SocialException('La cuenta ya no está conectada. Reconéctala en «Conexiones».');
            }

            $driver = Networks::driver($target->network);
            $result = $current->status === 'processing'
                ? $driver->check($target, $post, $account)
                : $driver->publish($target, $post, $account, Media::of($post));

            if ($result['status'] === 'processing') {
                if ($target->checks >= self::MAX_CHECKS) {
                    throw new SocialException(Networks::label($target->network) . ' tardó demasiado en procesar el archivo.');
                }
                $target->update(['status' => 'processing', 'state' => $result['state'], 'checks' => $target->checks + 1]);
                PublishTarget::dispatch($target->id)->delay(now()->addSeconds($result['delay'] ?? 10));
            } else {
                $target->update([
                    'status'       => 'published',
                    'external_id'  => $result['external_id'] ?? null,
                    'permalink'    => $result['permalink'] ?? null,
                    'error'        => null,
                    'published_at' => now(),
                ]);
                Event::dispatch('aero.social.postPublished', [$target]);
            }
        } catch (Throwable $e) {
            if ($e instanceof SocialException && $e->tokenInvalid && $account) {
                $account->markExpired($e->getMessage());
                Event::dispatch('aero.social.accountExpired', [$account]);
            }
            if (!$e instanceof SocialException) {
                Log::error('aero.social: fallo publicando destino ' . $targetId, ['exception' => $e]);
            }
            self::fail($target, $e instanceof SocialException ? $e->getMessage() : 'Error inesperado al publicar. Se puede reintentar.');
        }

        $post?->refreshStatus();
    }

    public static function fail(PostTarget $target, string $message): void
    {
        $target->update(['status' => 'failed', 'error' => mb_substr($message, 0, 2000)]);
        Billing::refund($target, 'Publicación fallida en ' . Networks::label($target->network));
        Event::dispatch('aero.social.postFailed', [$target]);
    }

    /** Cancela lo que aún no salió y devuelve los créditos. */
    public static function cancel(Post $post): int
    {
        $count = 0;
        foreach ($post->targets()->where('status', 'queued')->get() as $target) {
            if (PostTarget::where('id', $target->id)->where('status', 'queued')->update(['status' => 'cancelled'])) {
                Billing::refund($target->fresh(), 'Publicación cancelada');
                $count++;
            }
        }

        $post->refreshStatus();
        if ($post->status === 'cancelled') {
            $post->status = 'draft';
            $post->saveQuietly();
        }

        return $count;
    }

    /** Reintenta un destino fallido: vuelve a cobrar y lo encola ya. */
    public static function retry(PostTarget $target): void
    {
        if ($target->status !== 'failed') {
            throw new ApplicationException('Solo se reintentan publicaciones fallidas.');
        }
        if (!$target->account || !$target->account->isActive()) {
            throw new ApplicationException('Reconecta la cuenta en «Conexiones» antes de reintentar.');
        }

        try {
            DB::transaction(function () use ($target) {
                $target->update([
                    'status' => 'queued', 'run_at' => now(), 'error' => null, 'state' => null, 'checks' => 0,
                    'credits_charged' => 0, 'credit_transaction_id' => null, 'refunded_at' => null,
                ]);
                Billing::charge($target);
            });
        } catch (\Aero\Credits\Classes\Exceptions\InsufficientCreditsException $e) {
            throw new ApplicationException('No te alcanzan los créditos para reintentar. ' . $e->getMessage());
        }

        Cache::forget("aero.social.dispatched.{$target->id}");
        $target->post?->refreshStatus();
        self::dispatchDue($target->post_id);
    }

    /** Destinos colgados (worker caído a mitad de camino) → fallidos y reembolsados. */
    public static function sweep(): int
    {
        $stuck = PostTarget::where('status', 'publishing')
            ->where('updated_at', '<', now()->subMinutes(self::STUCK_MINUTES))
            ->get();

        foreach ($stuck as $target) {
            self::fail($target, 'No se pudo confirmar la publicación. Revisa la red antes de reintentar para no duplicarla.');
            $target->post?->refreshStatus();
        }

        // Contenedores en proceso cuyo job se perdió.
        foreach (PostTarget::where('status', 'processing')->where('updated_at', '<', now()->subMinutes(2))->pluck('id') as $id) {
            if (Cache::add("aero.social.dispatched.{$id}", 1, now()->addMinutes(2))) {
                PublishTarget::dispatch($id);
            }
        }

        return $stuck->count();
    }

    /** Verificación diaria: marca como vencidas las cuentas cuyo token ya no sirve. */
    public static function checkAccounts(): int
    {
        $expired = 0;
        foreach (Account::active()->whereIn('network', ['facebook', 'instagram'])->get() as $account) {
            try {
                MetaClient::get($account->external_id, ['fields' => 'id'], $account->access_token);
                $account->update(['checked_at' => now(), 'last_error' => null]);
            } catch (SocialException $e) {
                if ($e->tokenInvalid) {
                    $account->markExpired($e->getMessage());
                    Event::dispatch('aero.social.accountExpired', [$account]);
                    $expired++;
                }
            }
        }

        // Cuentas publicitarias cuyo token de 60 días ya venció.
        foreach (\Aero\Social\Models\AdAccount::active()->whereNotNull('token_expires_at')->where('token_expires_at', '<', now())->get() as $ad) {
            $ad->markExpired('La conexión con Meta Ads venció (dura unos 60 días). Vuelve a conectarla.');
            Event::dispatch('aero.social.adAccountExpired', [$ad]);
            $expired++;
        }

        return $expired;
    }
}
