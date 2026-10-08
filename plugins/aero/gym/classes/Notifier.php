<?php namespace Aero\Gym\Classes;

use Aero\Gym\Models\Member;

/** WhatsApp vía Aero.Hello, integración blanda: sin Hello no pasa nada. */
class Notifier
{
    public static function available(): bool
    {
        return class_exists(\Aero\Hello\Classes\Hello::class);
    }

    public static function whatsapp(Member $member, string $body, ?string $mediaUrl = null): bool
    {
        $phone = preg_replace('/\D+/', '', (string) $member->phone);
        if (!self::available() || !$phone) {
            return false;
        }

        try {
            $options = ['platform' => 'whatsapp', 'tenant_id' => $member->tenant_id, 'name' => $member->name];
            if ($mediaUrl) {
                $options['media_url'] = $mediaUrl;
            }
            \Aero\Hello\Classes\Hello::send($phone, $body, $options);

            return true;
        } catch (\Throwable $e) {
            \Log::warning('[gym] WhatsApp no enviado: ' . $e->getMessage());

            return false;
        }
    }
}
