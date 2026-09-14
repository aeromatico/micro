<?php namespace Aero\Sites\Components;

use Aero\Sites\Classes\Niches\NicheManager;
use Aero\Sites\Classes\SignupPlans;
use Aero\Sites\Classes\TenantProvisioner;
use Aero\Sites\Models\RootDomain;
use Aero\Sites\Models\Settings;
use Aero\Sites\Models\Tenant;
use Cms\Classes\ComponentBase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Str;

/**
 * Wizard público de alta en 2 pasos para /alta (tema master):
 *  1) subdominio + rubro + plan -> reserva el Tenant (status=pending_payment)
 *     y emite un QR de cobro contra la cuenta configurada en Settings.
 *  2) el visitante paga el QR; aero/pay confirma vía webhook y dispara
 *     aero.pay.paymentReceived (ver Plugin::bootPaySignupBridge), que
 *     activa el tenant. El front hace polling con onCheckPaymentStatus y,
 *     al ver 'paid', muestra el formulario final que llama onCreateAdmin.
 *
 * No requiere sesión de frontend — es un visitante anónimo pagando para
 * convertirse en tenant.
 */
class SignupWizard extends ComponentBase
{
    protected const RESERVED_HANDLES = [
        'www', 'api', 'admin', 'market', 'mercado', 'soporte', 'support', 'help',
        'ayuda', 'mail', 'ftp', 'ns1', 'ns2', 'blog', 'status', 'cdn', 'app',
        'panel', 'backend', 'root', 'webmail', 'smtp', 'pop', 'imap', 'alta',
        'signup', 'demo', 'test', 'staging', 'dev',
    ];

    public function componentDetails(): array
    {
        return [
            'name'        => 'Sites Alta (wizard)',
            'description' => 'Wizard público de 2 pasos: subdominio + rubro, pago QR y creación del administrador.',
        ];
    }

    public function onRun(): void
    {
        $niches = collect(app(NicheManager::class)->options())
            ->reject(fn ($label, $handle) => $handle === 'generic')
            ->map(fn ($label, $handle) => ['id' => $handle, 'label' => $label])
            ->values()
            ->all();

        $plans = SignupPlans::all();
        $signupEnabled = (bool) Settings::getSignupBankAccount();
        $domainRegistrationPrice = Settings::getDomainRegistrationPrice();

        $requestedPlan = (string) $this->param('plan');
        $initialPlan = SignupPlans::exists($requestedPlan) ? $requestedPlan : 'negocio';

        $this->page['niches'] = $niches;
        $this->page['plans'] = $plans;
        $this->page['signupEnabled'] = $signupEnabled;

        // JSON pre-escapado (comillas/apóstrofes/HTML) para poder inyectarlo
        // directo dentro de un atributo x-data="..." sin romper el HTML.
        $this->page['signupConfigJson'] = json_encode([
            'niches'                  => $niches,
            'initialPlan'             => $initialPlan,
            'plans'                   => $plans,
            'signupEnabled'           => $signupEnabled,
            'domainRegistrationPrice' => $domainRegistrationPrice,
        ], JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_TAG);
    }

    // -------------------------------------------------------------------
    // Paso 1a — chequeo de disponibilidad en vivo
    // -------------------------------------------------------------------

    public function onCheckSubdomain(): array
    {
        $handle = $this->normalizeHandle(post('handle', ''));

        $error = $this->validateHandleFormat($handle);
        if ($error) {
            return ['available' => false, 'message' => $error];
        }

        $key = 'signup-check:' . request()->ip();
        if (RateLimiter::tooManyAttempts($key, 60)) {
            return ['available' => false, 'message' => 'Demasiados intentos, espera un momento.'];
        }
        RateLimiter::hit($key, 60);

        if (Tenant::withTrashed()->where('handle', $handle)->exists()) {
            return ['available' => false, 'message' => "\"{$handle}\" ya está en uso, prueba otro nombre"];
        }

        return ['available' => true, 'message' => "¡Disponible! {$handle}.market.com.bo"];
    }

    // -------------------------------------------------------------------
    // Paso 1 -> 2 — reserva el tenant y emite el QR de cobro
    // -------------------------------------------------------------------

    public function onCreateSignup(): array
    {
        $key = 'signup-create:' . request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return ['success' => false, 'message' => "Demasiados intentos. Intenta en {$seconds}s."];
        }

        $handle = $this->normalizeHandle(post('handle', ''));
        $niche  = post('niche', '');
        $plan   = post('plan', '');

        $formatError = $this->validateHandleFormat($handle);
        if ($formatError) {
            return ['success' => false, 'message' => $formatError];
        }

        // El dominio propio solo lo ofrecemos en el plan Pro — la
        // disponibilidad ya se verificó en vivo desde el navegador contra la
        // API de clouds.com.bo (ver signup.js searchDomains()), acá solo se
        // valida formato: no se vuelve a consultar la API para confirmar
        // (el registro real es un paso manual del equipo después del pago,
        // así que una segunda verificación no evita nada — a lo sumo el
        // dominio elegido ya no está disponible cuando se lo registre a
        // mano, y ahí se contacta al cliente).
        $domain = trim((string) post('domain', ''));
        $domainError = $this->validateDomainFormat($domain, $plan);
        if ($domainError) {
            return ['success' => false, 'message' => $domainError];
        }

        if (!array_key_exists($niche, app(NicheManager::class)->options())) {
            return ['success' => false, 'message' => 'Elige un rubro válido.'];
        }

        if (!SignupPlans::exists($plan)) {
            return ['success' => false, 'message' => 'Elige un plan válido.'];
        }

        $bankAccount = Settings::getSignupBankAccount();
        if (!$bankAccount || !class_exists(\Aero\Pay\Classes\QrIssuer::class)) {
            return ['success' => false, 'message' => 'El cobro no está disponible en este momento. Intenta más tarde.'];
        }

        RateLimiter::hit($key, 3600);

        if (Tenant::withTrashed()->where('handle', $handle)->exists()) {
            return ['success' => false, 'message' => "\"{$handle}\" ya está en uso, prueba otro nombre"];
        }

        $rootDomain = RootDomain::active()->first();
        if (!$rootDomain) {
            return ['success' => false, 'message' => 'No hay un dominio raíz activo configurado.'];
        }

        $planData = SignupPlans::find($plan);
        $domainPrice = $domain !== '' ? Settings::getDomainRegistrationPrice() : 0.0;
        $amount = (float) $planData['price'] + $domainPrice;

        try {
            $tenant = Tenant::create([
                'name'           => Str::title(str_replace(['-', '_'], ' ', $handle)),
                'handle'         => $handle,
                'root_domain_id' => $rootDomain->id,
                'niche_type'     => $niche,
                'plan'           => $plan,
                'plan_price'     => $planData['price'],
                'signup_domain'  => $domain !== '' ? $domain : null,
                'status'         => 'pending_payment',
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Carrera: alguien más tomó el mismo handle entre el chequeo y el create.
            return ['success' => false, 'message' => "\"{$handle}\" ya está en uso, prueba otro nombre"];
        }

        $description = "Alta Market — {$handle} (plan {$planData['label']})";
        if ($domain !== '') {
            $description .= " + dominio {$domain}";
        }

        $qrCode = app(\Aero\Pay\Classes\QrIssuer::class)->issue(
            bankAccount: $bankAccount,
            amount: $amount,
            currency: 'BOB',
            description: $description,
            origin: 'sites',
        );

        $tenant->signup_qr_code_id = $qrCode->id;
        $tenant->signup_payment_reference = $qrCode->internal_reference;
        $tenant->save();

        return [
            'success'      => true,
            'tenant_id'    => $tenant->id,
            'reference'    => $qrCode->internal_reference,
            'domain'       => $tenant->handle . '.' . $rootDomain->domain,
            'niche_label'  => app(NicheManager::class)->options()[$niche] ?? $niche,
            'plan_label'   => $planData['label'],
            'amount'       => $amount,
            'own_domain'   => $domain !== '' ? $domain : null,
            'due_date'     => $qrCode->due_date?->toFormattedDateString(),
            'qr_image'     => $qrCode->qr_image ? 'data:image/png;base64,' . $qrCode->qr_image : null,
        ];
    }

    // -------------------------------------------------------------------
    // Paso 2 — polling de estado de pago
    // -------------------------------------------------------------------

    public function onCheckPaymentStatus(): array
    {
        $tenant = Tenant::find((int) post('tenant_id'));

        if (!$tenant || $tenant->signup_payment_reference !== post('reference')) {
            return ['status' => 'not_found'];
        }

        if ($tenant->status === 'active') {
            return [
                'status'         => 'paid',
                'domain'         => $tenant->primary_domain,
                'admin_created'  => (bool) $tenant->backend_user_id,
            ];
        }

        return ['status' => 'pending'];
    }

    // -------------------------------------------------------------------
    // Paso 2c — crear el usuario administrador, una vez pagado
    // -------------------------------------------------------------------

    public function onCreateAdmin(): array
    {
        $tenant = Tenant::find((int) post('tenant_id'));

        if (!$tenant || $tenant->signup_payment_reference !== post('reference')) {
            return ['success' => false, 'message' => 'No encontramos tu alta. Vuelve a intentar desde el paso 1.'];
        }

        if ($tenant->status !== 'active') {
            return ['success' => false, 'message' => 'Todavía no confirmamos tu pago.'];
        }

        if ($tenant->backend_user_id) {
            return ['success' => false, 'message' => 'Esta cuenta ya fue creada.'];
        }

        $key = 'signup-admin:' . request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return ['success' => false, 'message' => 'Demasiados intentos. Espera un momento.'];
        }
        RateLimiter::hit($key, 600);

        $data = post();
        $validator = Validator::make($data, [
            'name'     => 'required|min:2|max:100',
            'email'    => 'required|email|max:150|unique:backend_users,email',
            'phone'    => 'required|min:6|max:30',
            'password' => 'required|min:8|max:100',
        ], [
            'name.required'     => 'El nombre es obligatorio.',
            'email.required'    => 'El correo es obligatorio.',
            'email.email'       => 'El correo no es válido.',
            'email.unique'      => 'Ya existe una cuenta con ese correo.',
            'phone.required'    => 'El WhatsApp es obligatorio.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min'      => 'La contraseña debe tener al menos 8 caracteres.',
        ]);

        if ($validator->fails()) {
            return ['success' => false, 'message' => $validator->errors()->first()];
        }

        try {
            app(TenantProvisioner::class)->provisionAdmin(
                $tenant,
                $data['name'],
                $data['email'],
                $data['password'],
            );
        } catch (\Exception $e) {
            \Log::error("Aero\\Sites SignupWizard: fallo al crear admin del tenant {$tenant->id}: " . $e->getMessage());
            return ['success' => false, 'message' => 'No se pudo crear la cuenta. Intenta con otro correo.'];
        }

        return [
            'success'        => true,
            'primary_domain' => $tenant->primary_domain,
            'backend_url'    => \Backend::baseUrl(),
        ];
    }

    // -------------------------------------------------------------------

    protected function normalizeHandle(string $raw): string
    {
        return strtolower(trim($raw));
    }

    protected function validateHandleFormat(string $handle): ?string
    {
        if ($handle === '') {
            return 'Escribe un nombre para tu sitio.';
        }
        if (strlen($handle) < 3) {
            return 'Usa al menos 3 caracteres.';
        }
        if (strlen($handle) > 30) {
            return 'Máximo 30 caracteres.';
        }
        if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $handle)) {
            return 'Solo minúsculas, números y guiones (sin empezar o terminar con guión).';
        }
        if (in_array($handle, static::RESERVED_HANDLES, true)) {
            return "\"{$handle}\" está reservado, prueba otro nombre";
        }

        return null;
    }

    /**
     * Formato solamente — la disponibilidad ya se validó en el navegador
     * contra la API de clouds.com.bo (ver docblock de onCreateSignup()).
     */
    protected function validateDomainFormat(string $domain, string $plan): ?string
    {
        if ($domain === '') {
            return null;
        }

        if ($plan !== 'pro') {
            return 'El registro de dominio propio solo está disponible en el plan Pro.';
        }

        if (!preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.[a-z]{2,24}$/i', $domain)) {
            return 'El dominio elegido no tiene un formato válido.';
        }

        return null;
    }
}
