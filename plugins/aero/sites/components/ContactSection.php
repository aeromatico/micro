<?php namespace Aero\Sites\Components;

use Aero\Sites\Jobs\DispatchContactNotification;
use Aero\Sites\Models\ContactConfig;
use Aero\Sites\Models\ContactSubmission;
use Aero\Sites\Models\Tenant;
use Cms\Classes\ComponentBase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

class ContactSection extends ComponentBase
{
    public ?ContactConfig $contactConfig = null;
    public ?Tenant $tenant = null;
    public bool $submitted = false;
    public string $successMessage = '';

    public function componentDetails(): array
    {
        return [
            'name'        => 'Sites Contacto',
            'description' => 'Muestra la información de contacto y el formulario del tenant.',
        ];
    }

    public function onRun(): void
    {
        $host = request()->getHost();
        $this->tenant = Tenant::resolveFromDomain($host);
        if (!$this->tenant) return;

        $this->contactConfig = ContactConfig::where('tenant_id', $this->tenant->id)->first();
    }

    public function onSend(): array
    {
        $host = request()->getHost();
        $this->tenant = Tenant::resolveFromDomain($host);

        if (!$this->tenant) {
            return ['#contact-response' => '<p class="text-red-400">Error: tenant no encontrado.</p>'];
        }

        $this->contactConfig = ContactConfig::where('tenant_id', $this->tenant->id)->first();

        if (!$this->contactConfig?->form_enabled) {
            return ['#contact-response' => '<p class="text-red-400">El formulario no está disponible.</p>'];
        }

        // Rate limiting
        $key = 'contact:' . request()->ip() . ':' . $this->tenant->id;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            return ['#contact-response' => "<p class=\"text-yellow-400\">Demasiados intentos. Intenta en {$seconds}s.</p>"];
        }
        RateLimiter::hit($key, 3600);

        // Validate
        $data = post();
        $validator = Validator::make($data, [
            'name'    => 'required|min:2|max:100',
            'email'   => 'required|email',
            'phone'   => 'nullable|max:30',
            'message' => 'required|min:5|max:2000',
        ], [
            'name.required'    => 'El nombre es obligatorio.',
            'email.required'   => 'El email es obligatorio.',
            'email.email'      => 'El email no es válido.',
            'message.required' => 'El mensaje es obligatorio.',
            'message.min'      => 'El mensaje es muy corto.',
        ]);

        if ($validator->fails()) {
            $error = $validator->errors()->first();
            return ['#contact-response' => "<p class=\"text-red-400\">{$error}</p>"];
        }

        // Save and dispatch
        $submission = ContactSubmission::create([
            'tenant_id' => $this->tenant->id,
            'name'      => $data['name'],
            'email'     => $data['email'],
            'phone'     => $data['phone'] ?? null,
            'message'   => $data['message'],
            'metadata'  => [
                'ip'      => request()->ip(),
                'ua'      => request()->userAgent(),
                'referer' => request()->header('referer'),
            ],
            'status' => 'pending',
        ]);

        DispatchContactNotification::dispatch($submission);

        $msg = $this->contactConfig->success_message ?: '¡Mensaje recibido! Te responderemos pronto.';

        return [
            '#contact-response' => "<p class=\"text-green-400 font-medium\">{$msg}</p>",
            '#contact-form'     => '',
        ];
    }
}
