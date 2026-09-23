<?php namespace Aero\Sites\Components;

use Aero\Sites\Jobs\DispatchPlatformLeadNotification;
use Aero\Sites\Models\PlatformLead;
use Cms\Classes\ComponentBase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

/**
 * Formulario de "Trial" / "Gran Empresa" del landing (themes/master),
 * modal disparado desde la sección de precios. No pertenece a un tenant:
 * el lead es del negocio de la plataforma, no de un micrositio.
 */
class PlatformLeadForm extends ComponentBase
{
    /** Dominios de correo gratuito más comunes — si el email termina en uno
     *  de estos, no se considera corporativo y se exige verification_url. */
    protected const FREE_EMAIL_DOMAINS = [
        'gmail.com', 'googlemail.com', 'hotmail.com', 'hotmail.es', 'outlook.com',
        'outlook.es', 'live.com', 'yahoo.com', 'yahoo.es', 'icloud.com', 'me.com',
        'aol.com', 'protonmail.com', 'proton.me', 'gmx.com', 'mail.com',
    ];

    public function componentDetails(): array
    {
        return [
            'name'        => 'Sites Platform Lead Form',
            'description' => 'Formulario modal de solicitud de Trial / Gran Empresa del landing de la plataforma.',
        ];
    }

    public static function isCorporateEmail(string $email): bool
    {
        $domain = strtolower(trim(explode('@', $email . '@')[1] ?? ''));

        return $domain !== '' && !in_array($domain, static::FREE_EMAIL_DOMAINS, true);
    }

    public function onSend(): array
    {
        $key = 'platform-lead:' . request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return ['#platform-lead-response' => "<p class=\"text-red-400 text-sm\">Demasiados intentos. Intenta en {$seconds}s.</p>"];
        }
        RateLimiter::hit($key, 3600);

        $data = post();
        $email = trim((string) ($data['email'] ?? ''));
        $isCorporate = static::isCorporateEmail($email);

        $validator = Validator::make($data + ['is_corporate_email' => $isCorporate], [
            'plan'              => 'required|in:trial,enterprise',
            'name'              => 'required|min:2|max:100',
            'email'             => 'required|email|max:255',
            'phone'             => 'required|max:30',
            'message'           => 'required|min:5|max:2000',
            'verification_url'  => 'required_if:is_corporate_email,false|nullable|url|max:255',
        ], [
            'name.required'             => 'El nombre es obligatorio.',
            'email.required'            => 'El correo es obligatorio.',
            'email.email'               => 'El correo no es válido.',
            'phone.required'            => 'El celular es obligatorio.',
            'message.required'          => 'Cuéntanos qué quieres resolver.',
            'message.min'               => 'Cuéntanos un poco más.',
            'verification_url.required_if' => 'Agrega el sitio web o red social de tu negocio.',
            'verification_url.url'      => 'Ingresa una URL válida (con http:// o https://).',
        ]);

        if ($validator->fails()) {
            $error = $validator->errors()->first();
            return ['#platform-lead-response' => "<p class=\"text-red-400 text-sm\">{$error}</p>"];
        }

        $lead = PlatformLead::create([
            'plan'               => $data['plan'],
            'name'               => $data['name'],
            'email'              => $email,
            'phone'              => $data['phone'],
            'message'            => $data['message'],
            'is_corporate_email' => $isCorporate,
            'verification_url'   => $data['verification_url'] ?? null,
            'metadata'           => [
                'ip'      => request()->ip(),
                'ua'      => request()->userAgent(),
                'referer' => request()->header('referer'),
            ],
            'status' => 'new',
        ]);

        DispatchPlatformLeadNotification::dispatch($lead);

        return [
            '#platform-lead-response' => '<p class="text-accent text-sm font-semibold">¡Gracias! Recibimos tu solicitud — nuestro equipo la revisará y te contactará pronto.</p>',
            '#platform-lead-fields'   => '',
        ];
    }
}
