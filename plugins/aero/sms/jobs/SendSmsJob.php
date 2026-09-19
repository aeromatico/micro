<?php namespace Aero\Sms\Jobs;

use Aero\Sms\Classes\Billing;
use Aero\Sms\Classes\Drivers\SmsDriverException;
use Aero\Sms\Classes\Sms;
use Aero\Sms\Models\Message;
use Aero\Sms\Models\OptOut;
use Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(public int $messageId)
    {
    }

    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(): void
    {
        $message = Message::find($this->messageId);

        // Cancelado, bloqueado o ya procesado mientras esperaba en la cola.
        if (!$message || $message->status !== 'queued') {
            return;
        }

        // Una baja pudo registrarse entre el encolado y el envío.
        if (OptOut::blocks($message->to)) {
            $message->status = 'blocked';
            $message->error_message = 'El número está en la lista de bajas.';
            $message->save();
            Billing::refund($message, 'Número dado de baja');
            $message->batch?->refreshStatus();

            return;
        }

        $driver = Sms::driver();
        $message->status = 'sending';
        $message->driver = $driver->code();
        $message->save();

        try {
            $result = $driver->send($message);
        }
        catch (SmsDriverException $e) {
            if ($e->retryable && $this->attempts() < $this->tries) {
                $message->status = 'queued';
                $message->saveQuietly();
                $this->release($this->backoff()[$this->attempts() - 1] ?? 120);

                return;
            }

            $message->status = 'failed';
            $message->error_code = $e->providerCode;
            $message->error_message = mb_substr($e->getMessage(), 0, 250);
            $message->save();
            Billing::refund($message, 'Falló el envío: ' . $e->getMessage());
            Event::fire('aero.sms.messageStatusChanged', [$message]);
            $message->batch?->refreshStatus();

            return;
        }

        $message->provider_message_id = $result->providerMessageId;
        $message->sent_at = now();
        $message->status = 'sent';
        $message->save();

        if ($result->status === 'delivered') {
            Sms::applyProviderStatus($message, 'delivered');
        }
        else {
            Event::fire('aero.sms.messageStatusChanged', [$message]);
        }

        $message->batch?->refreshStatus();
    }
}
