<?php namespace App;

use Aero\Hello\Classes\Notifications\ZernioChannelDriver;
use Aero\Hello\Models\Message;
use Event;
use Log;
use RainLab\User\Models\User as RainLabUser;
use System\Classes\AppBase;

/**
 * Provider is an application level plugin, all registration methods are supported.
 */
class Provider extends AppBase
{
    /**
     * Flow ID for the "test_rainlab_signup" WhatsApp Flow — creates a
     * RainLab.User account from the flow's submitted fields. Test-only glue,
     * kept here (not inside aero/hello) since the plugin is meant to stay
     * independent of rainlab/user.
     */
    protected const TEST_SIGNUP_FLOW_ID = '1207235862482844';

    /**
     * register method, called when the app is first registered.
     *
     * @return void
     */
    public function register()
    {
        parent::register();
    }

    /**
     * boot method, called right before the request route.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        Event::listen('aero.hello.messageReceived', function (Message $message, array $payload) {
            $this->handleTestSignupFlowResponse($message, $payload);
        });
    }

    protected function handleTestSignupFlowResponse(Message $message, array $payload): void
    {
        $metadata = $payload['metadata'] ?? [];

        if (($metadata['interactiveType'] ?? null) !== 'nfm_reply') {
            return;
        }

        $flowToken = $metadata['flowResponseData']['flow_token'] ?? '';
        if (!str_starts_with($flowToken, self::TEST_SIGNUP_FLOW_ID . ':')) {
            return;
        }

        $fields = $metadata['flowResponseData'] ?? [];
        $conversation = $message->conversation;
        $account = $conversation->account;
        $contact = $message->contact;
        $driver = app(ZernioChannelDriver::class);

        try {
            $user = RainLabUser::create([
                'first_name'            => $fields['first_name'] ?? 'Test',
                'email'                 => $fields['email'] ?? '',
                'username'              => $fields['username'] ?? '',
                'password'              => $fields['password'] ?? '',
                'password_confirmation' => $fields['password'] ?? '',
            ]);
            $user->markEmailAsVerified();
            $contact->update(['rainlab_user_id' => $user->id]);

            $driver->sendMessage($account, '', [
                'conversation_id' => $conversation->zernio_conversation_id,
                'body'            => "✅ Cuenta creada: usuario \"{$user->username}\" ({$user->email}). Esto fue solo una prueba del flow de WhatsApp.",
            ]);
        } catch (\Throwable $e) {
            Log::error('app.Provider: test signup flow failed to create RainLab user', [
                'error'  => $e->getMessage(),
                'fields' => array_diff_key($fields, ['password' => true]),
            ]);

            $driver->sendMessage($account, '', [
                'conversation_id' => $conversation->zernio_conversation_id,
                'body'            => "❌ No se pudo crear la cuenta: {$e->getMessage()}",
            ]);
        }
    }
}
