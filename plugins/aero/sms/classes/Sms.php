<?php namespace Aero\Sms\Classes;

use Aero\Sms\Classes\Drivers\SimulatedDriver;
use Aero\Sms\Classes\Drivers\SmsDriverInterface;
use Aero\Sms\Classes\Drivers\TwilioDriver;
use Aero\Sms\Jobs\SendSmsJob;
use Aero\Sms\Models\Batch;
use Aero\Sms\Models\Message;
use Aero\Sms\Models\OptOut;
use Aero\Sms\Models\Settings;
use Aero\Sms\Models\Template;
use Carbon\Carbon;
use DB;
use Event;
use InvalidArgumentException;
use Str;

/**
 * Punto de entrada del plugin. La API REST y el backend pasan por aquí, así el
 * cobro, la baja y la atribución se aplican siempre igual.
 *
 * $consumer = ['tenant_id' => ?int, 'api_key_id' => ?int, 'label' => ?string]
 * identifica a quién se atribuye (y cobra) el envío.
 */
class Sms
{
    public static function driver(): SmsDriverInterface
    {
        return match (Settings::driverCode()) {
            'twilio' => new TwilioDriver(),
            default  => new SimulatedDriver(),
        };
    }

    /**
     * @throws InvalidArgumentException datos inválidos
     * @throws \Aero\Credits\Classes\Exceptions\InsufficientCreditsException
     */
    public static function send(array $data, array $consumer, ?Batch $batch = null): Message
    {
        $message = DB::transaction(fn () => static::create($data, $consumer, $batch));

        static::dispatch($message);

        return $message;
    }

    /**
     * Crea un lote entero en una transacción: si el saldo no alcanza para
     * todos, no se crea ni se cobra nada.
     *
     * $data: name, body|template, recipients[] (string o ['to','vars']), scheduled_at
     */
    public static function sendBatch(array $data, array $consumer): Batch
    {
        $recipients = $data['recipients'] ?? [];

        if (!$recipients || count($recipients) > Settings::maxBatchSize()) {
            throw new InvalidArgumentException('recipients debe tener entre 1 y ' . Settings::maxBatchSize() . ' destinatarios.');
        }

        $scheduledAt = static::parseSchedule($data['scheduled_at'] ?? null);

        [$batch, $messages] = DB::transaction(function () use ($data, $consumer, $recipients, $scheduledAt) {
            $batch = Batch::create([
                'uuid'         => (string) Str::uuid(),
                'name'         => $data['name'] ?? null,
                'tenant_id'    => $consumer['tenant_id'] ?? null,
                'api_key_id'   => $consumer['api_key_id'] ?? null,
                'consumer'     => $consumer['label'] ?? null,
                'status'       => $scheduledAt ? 'scheduled' : 'running',
                'total'        => count($recipients),
                'scheduled_at' => $scheduledAt,
            ]);

            $messages = [];

            foreach ($recipients as $i => $recipient) {
                $recipient = is_array($recipient) ? $recipient : ['to' => $recipient];

                try {
                    $messages[] = static::create([
                        'to'           => $recipient['to'] ?? null,
                        'body'         => $data['body'] ?? null,
                        'template'     => $data['template'] ?? null,
                        'vars'         => $recipient['vars'] ?? [],
                        'reference'    => $recipient['reference'] ?? ($data['reference'] ?? null),
                        'scheduled_at' => $scheduledAt,
                    ], $consumer, $batch);
                }
                catch (InvalidArgumentException $e) {
                    throw new InvalidArgumentException("Destinatario #" . ($i + 1) . ': ' . $e->getMessage());
                }
            }

            $batch->credits_charged = collect($messages)->sum('credits_charged');
            $batch->saveQuietly();

            return [$batch, $messages];
        });

        $rate = Settings::maxPerMinute();
        $base = $scheduledAt && $scheduledAt->isFuture() ? now()->diffInSeconds($scheduledAt, true) : 0;

        foreach ($messages as $i => $message) {
            if ($message->status === 'queued') {
                SendSmsJob::dispatch($message->id)->delay(now()->addSeconds($base + (int) floor($i * 60 / $rate)));
            }
        }

        $batch->refreshStatus();

        return $batch;
    }

    public static function cancelBatch(Batch $batch): int
    {
        $cancelled = 0;

        Message::where('batch_id', $batch->id)->where('status', 'queued')->get()->each(function (Message $m) use (&$cancelled) {
            $m->status = 'cancelled';
            $m->saveQuietly();
            Billing::refund($m, 'Lote cancelado');
            $cancelled++;
        });

        $batch->status = 'cancelled';
        $batch->completed_at = now();
        $batch->saveQuietly();

        return $cancelled;
    }

    public static function cancel(Message $message): bool
    {
        if ($message->status !== 'queued') {
            return false;
        }

        $message->status = 'cancelled';
        $message->saveQuietly();
        Billing::refund($message, 'Mensaje cancelado');

        return true;
    }

    /** Aplica un estado reportado por el proveedor sin retroceder uno más avanzado. */
    public static function applyProviderStatus(Message $message, string $status, ?string $errorCode = null, ?string $errorMessage = null): void
    {
        $status = match ($status) {
            'accepted', 'scheduled', 'queued', 'sending' => 'sending',
            'sent'                                        => 'sent',
            'delivered'                                   => 'delivered',
            'undelivered'                                 => 'undelivered',
            'failed'                                      => 'failed',
            default                                       => null,
        };

        if (!$status || $message->status === $status) {
            return;
        }

        $rank = ['queued' => 0, 'sending' => 1, 'sent' => 2, 'delivered' => 3, 'failed' => 3, 'undelivered' => 3];

        // Un 'sent' tardío no puede pisar un 'delivered'; un estado final sí lo pisa todo.
        if (($rank[$status] ?? 0) < ($rank[$message->status] ?? 0)) {
            return;
        }

        $message->status = $status;

        if ($status === 'delivered') {
            $message->delivered_at = now();
        }

        if (in_array($status, ['failed', 'undelivered'], true)) {
            $message->error_code = $errorCode;
            $message->error_message = $errorMessage ?: 'El proveedor no pudo entregarlo.';
        }

        $message->save();

        if (in_array($status, ['failed', 'undelivered'], true)) {
            Billing::refund($message, "SMS no entregado ({$status})");
        }

        Event::fire('aero.sms.messageStatusChanged', [$message]);

        $message->batch?->refreshStatus();
    }

    public static function dispatch(Message $message): void
    {
        if ($message->status !== 'queued') {
            return;
        }

        $job = SendSmsJob::dispatch($message->id);

        if ($message->scheduled_at && $message->scheduled_at->isFuture()) {
            $job->delay($message->scheduled_at);
        }
    }

    /**
     * Valida, resuelve plantilla, cuenta segmentos, aplica bajas y cobra.
     * Debe llamarse dentro de una transacción.
     */
    protected static function create(array $data, array $consumer, ?Batch $batch): Message
    {
        $to = PhoneNumber::normalize($data['to'] ?? null);

        if (!$to) {
            throw new InvalidArgumentException("Número de destino inválido: '" . ($data['to'] ?? '') . "'.");
        }

        $body = static::resolveBody($data, $consumer['tenant_id'] ?? null);
        $count = Segments::count($body);

        if ($count['segments'] > 10) {
            throw new InvalidArgumentException('El mensaje excede 10 segmentos.');
        }

        $blocked = OptOut::blocks($to);

        $message = new Message([
            'uuid'         => (string) Str::uuid(),
            'batch_id'     => $batch?->id,
            'tenant_id'    => $consumer['tenant_id'] ?? null,
            'api_key_id'   => $consumer['api_key_id'] ?? null,
            'consumer'     => $consumer['label'] ?? null,
            'reference'    => $data['reference'] ?? null,
            'to'           => $to,
            'body'         => $body,
            'segments'     => $count['segments'],
            'encoding'     => $count['encoding'],
            'status'       => $blocked ? 'blocked' : 'queued',
            'scheduled_at' => static::parseSchedule($data['scheduled_at'] ?? null),
        ]);

        if ($blocked) {
            $message->error_message = 'El número está en la lista de bajas.';
        }
        else {
            Billing::charge($message);
        }

        $message->save();

        return $message;
    }

    protected static function resolveBody(array $data, ?int $tenantId = null): string
    {
        if (!empty($data['template'])) {
            $template = Template::findForTenant($data['template'], $tenantId);

            if (!$template) {
                throw new InvalidArgumentException("La plantilla '{$data['template']}' no existe o está inactiva.");
            }

            $body = Template::render($template->body, (array) ($data['vars'] ?? []));
        }
        else {
            $body = (string) ($data['body'] ?? '');
        }

        $body = trim($body);

        if ($body === '') {
            throw new InvalidArgumentException('El mensaje está vacío (body o template).');
        }

        return $body;
    }

    protected static function parseSchedule($value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        }
        catch (\Throwable) {
            throw new InvalidArgumentException('scheduled_at no es una fecha válida (usa ISO 8601).');
        }
    }
}
