<?php namespace Aero\Producer\Classes;

use Aero\Producer\Models\Order;
use Illuminate\Support\Str;
use Media\Classes\MediaLibrary;
use RuntimeException;

/**
 * Produce un pedido de presentador: voz (ElevenLabs o la del proveedor), imagen
 * animada con el movimiento del estilo y descarga del MP4 a Medios. El avance
 * se guarda en el pedido, así que cada paso se puede reintentar.
 */
class Production
{
    public static function canProduce(Order $order): bool
    {
        return $order->style?->kind === 'presenter' && in_array($order->status, ['queued', 'failed'], true);
    }

    /** Envía el pedido al proveedor. No cobra nada al tenant. */
    public static function submit(Order $order): void
    {
        if (!self::canProduce($order)) {
            throw new RuntimeException('Este pedido no se puede producir ahora.');
        }
        if ($problems = $order->problems()) {
            throw new RuntimeException(implode(' ', $problems));
        }

        $heygen = HeyGen::platform() ?? throw new RuntimeException('Falta la llave de HeyGen en Llaves de proveedores.');
        $style = $order->style;
        $voice = $order->voice;

        $body = [
            'type'         => 'image',
            'image'        => ['type' => 'asset_id', 'asset_id' => $heygen->upload($order->image->getLocalPath(), $order->image->content_type ?: 'image/png')],
            'aspect_ratio' => $order->aspect_ratio,
            'resolution'   => $order->resolution,
            'title'        => 'producer-' . $order->id,
        ];

        if ($style->motion_prompt) {
            $body['motion_prompt'] = $style->motion_prompt;
            $body['expressiveness'] = 'medium';
        }

        if ($voice->provider === 'elevenlabs') {
            $eleven = ElevenLabs::platform() ?? throw new RuntimeException('Falta la llave de ElevenLabs en Llaves de proveedores.');
            $tmp = tempnam(sys_get_temp_dir(), 'prod') . '.mp3';
            file_put_contents($tmp, $eleven->speak($voice->voice_id, trim((string) $order->script)));
            try {
                $body['audio_asset_id'] = $heygen->upload($tmp, 'audio/mpeg');
            } finally {
                @unlink($tmp);
            }
        } else {
            $body['script'] = trim((string) $order->script);
            $body['voice_id'] = $voice->voice_id;
        }

        $order->forceFill(['provider_ref' => $heygen->createVideo($body), 'status' => 'producing', 'error_message' => null])->save();
    }

    /** true = terminó (listo o fallido); false = seguir esperando. */
    public static function poll(Order $order): bool
    {
        if (!$order->provider_ref) {
            throw new RuntimeException('El pedido no tiene video en producción.');
        }

        $heygen = HeyGen::platform() ?? throw new RuntimeException('Falta la llave de HeyGen en Llaves de proveedores.');
        $video = $heygen->video($order->provider_ref);

        switch ($video['status'] ?? '') {
            case 'completed':
                $path = 'producer/' . ($order->tenant_id ?: 'plataforma') . '/' . Str::uuid() . '.mp4';
                MediaLibrary::instance()->put($path, $heygen->download($video['video_url']));
                $order->forceFill([
                    'status' => 'ready', 'output_url' => MediaLibrary::url($path), 'produced_at' => now(), 'error_message' => null,
                ])->save();
                \Event::fire('aero.producer.orderReady', [$order]);

                return true;
            case 'failed':
                self::fail($order, (string) ($video['failure_message'] ?? $video['error']['message'] ?? 'El proveedor no pudo producir el video.'), true);

                return true;
        }

        return false;
    }

    /** Con `$clearRef` el proveedor ya dio el video por perdido: el siguiente intento lo envía de nuevo. */
    public static function fail(Order $order, string $message, bool $clearRef = false): void
    {
        $order->forceFill(['status' => 'failed', 'error_message' => mb_substr($message, 0, 400)]);
        $clearRef && $order->provider_ref = null;
        $order->save();
        Billing::refund($order, 'Pedido de video no producido: ' . mb_substr($message, 0, 120));
        \Event::fire('aero.producer.orderFailed', [$order]);
    }
}
