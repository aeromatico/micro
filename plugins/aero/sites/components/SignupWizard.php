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
        $domainRenewalMarkupPercent = Settings::getDomainRenewalMarkupPercent();
        $usdToBobRate = Settings::getUsdToBobRate();

        $requestedPlan = (string) $this->param('plan');
        $initialPlan = SignupPlans::exists($requestedPlan) ? $requestedPlan : (array_key_first($plans) ?? '');

        $this->page['niches'] = $niches;
        $this->page['plans'] = $plans;
        $this->page['signupEnabled'] = $signupEnabled;

        // JSON pre-escapado (comillas/apóstrofes/HTML) para poder inyectarlo
        // directo dentro de un atributo x-data="..." sin romper el HTML.
        $this->page['signupConfigJson'] = json_encode([
            'niches'                     => $niches,
            'initialPlan'                => $initialPlan,
            'plans'                      => $plans,
            'signupEnabled'              => $signupEnabled,
            'domainRegistrationPrice'    => $domainRegistrationPrice,
            'domainRenewalMarkupPercent' => $domainRenewalMarkupPercent,
            'usdToBobRate'               => $usdToBobRate,
            'initialPromo'               => strtoupper(trim((string) request()->query('promo', ''))),
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
    // Código de invitación/cupón — validación sin consumir
    // -------------------------------------------------------------------

    public function onCheckPromo(): array
    {
        $key = 'signup-promo:' . request()->ip();
        if (RateLimiter::tooManyAttempts($key, 20)) {
            return ['valid' => false, 'message' => 'Demasiados intentos. Espera un momento.'];
        }
        RateLimiter::hit($key, 300);

        $code = trim((string) post('promo_code', ''));
        $redemption = null;

        if ($code !== '') {
            if (class_exists(\Aero\Credits\Classes\Invitations::class)) {
                $redemption = \Aero\Credits\Classes\Invitations::preview($code);
            }
            if (!$redemption && class_exists(\Aero\Credits\Classes\Coupons::class)) {
                $redemption = \Aero\Credits\Classes\Coupons::preview($code);
            }
        }

        if (!$redemption) {
            return ['valid' => false, 'message' => 'Ese código no es válido o ya venció.'];
        }

        $plan = $redemption['plan'];
        $duration = \Aero\Credits\Classes\Grants::periodLabel($redemption['period_unit'], $redemption['period_count']);

        return [
            'valid'      => true,
            'plan_label' => $plan->name,
            'is_pro'     => (bool) $plan->is_pro,
            'duration'   => $duration,
            'message'    => "¡Código válido! {$duration} gratis" . ($plan->is_pro ? ' con todas las funciones del plan PRO' : " del plan {$plan->name}"),
        ];
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
        $period = (string) post('period', 'monthly');

        $formatError = $this->validateHandleFormat($handle);
        if ($formatError) {
            return ['success' => false, 'message' => $formatError];
        }

        // Dominio propio solo en el plan Pro, de dos formas:
        // - 'register': lo elige de la búsqueda en vivo contra la API de
        //   clouds.com.bo (ver signup.js searchDomains()) — aquí solo se
        //   valida formato, no se vuelve a consultar la API (el registro
        //   real es un paso manual del equipo después del pago, una
        //   segunda verificación no evita nada). Cobra
        //   Settings::getDomainRegistrationPrice().
        // - 'existing': ya lo tiene y solo nos avisa cuál es, para que el
        //   equipo lo apunte a la plataforma después — gratis, sin
        //   disponibilidad que verificar (es suyo).
        $domainMode = post('domain_mode', '');
        $domain = trim((string) post('domain', ''));

        if (!in_array($domainMode, ['', 'register', 'existing'], true)) {
            return ['success' => false, 'message' => 'Opción de dominio no válida.'];
        }

        $domainError = $domainMode !== '' ? $this->validateDomainFormat($domain, $plan) : null;
        if ($domainError) {
            return ['success' => false, 'message' => $domainError];
        }

        if (!array_key_exists($niche, app(NicheManager::class)->options())) {
            return ['success' => false, 'message' => 'Elige un rubro válido.'];
        }

        if (!SignupPlans::exists($plan)) {
            return ['success' => false, 'message' => 'Elige un plan válido.'];
        }

        $planData = SignupPlans::find($plan);
        if (!isset($planData['periods'][$period])) {
            return ['success' => false, 'message' => 'Ese plan no ofrece el periodo elegido.'];
        }

        $isTrial = $period === 'trial';

        // Código de cupón/invitación de Aero.Credits (opcional): si es válido,
        // el alta se activa gratis con el plan/periodo que trae el código, sin
        // pasar por el QR de cobro — soft dependency, mismo patrón que
        // SignupPlans::creditBadges().
        $promoCode = trim((string) post('promo_code', ''));
        $redemption = null;
        if ($promoCode !== '') {
            if (class_exists(\Aero\Credits\Classes\Invitations::class)) {
                $redemption = \Aero\Credits\Classes\Invitations::preview($promoCode);
            }
            if (!$redemption && class_exists(\Aero\Credits\Classes\Coupons::class)) {
                $redemption = \Aero\Credits\Classes\Coupons::preview($promoCode);
            }
            if (!$redemption) {
                return ['success' => false, 'message' => 'Ese código no es válido o ya venció.'];
            }
        }

        if (($isTrial || $redemption) && $domainMode === 'register') {
            return ['success' => false, 'message' => 'El registro de dominio no está disponible en el alta gratis.'];
        }

        $bankAccount = Settings::getSignupBankAccount();
        if (!$isTrial && !$redemption && (!$bankAccount || !class_exists(\Aero\Pay\Classes\QrIssuer::class))) {
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

        // Un dominio existente es gratis (ya es del cliente, no hay nada
        // que registrar) — solo 'register' suma el cargo.
        $domainPrice = $domainMode === 'register' ? Settings::getDomainRegistrationPrice() : 0.0;
        $planPrice = (float) $planData['periods'][$period];
        $amount = $planPrice + $domainPrice;

        try {
            $tenant = Tenant::create([
                'name'                 => Str::title(str_replace(['-', '_'], ' ', $handle)),
                'handle'               => $handle,
                'root_domain_id'       => $rootDomain->id,
                'niche_type'           => $niche,
                'plan_id'              => $planData['id'],
                'plan_price'           => $planPrice,
                'billing_period'       => $period,
                'signup_domain'        => $domainMode !== '' ? $domain : null,
                'signup_domain_source' => $domainMode !== '' ? $domainMode : null,
                'status'               => 'pending_payment',
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Carrera: alguien más tomó el mismo handle entre el chequeo y el create.
            return ['success' => false, 'message' => "\"{$handle}\" ya está en uso, prueba otro nombre"];
        }

        if ($isTrial || $redemption) {
            return $this->activateFree($tenant, $planData, $rootDomain, $niche, $domainMode, $domain, $redemption);
        }

        $description = "Alta Market — {$handle} (plan {$planData['label']})";
        if ($domainMode === 'register') {
            $description .= " + dominio {$domain}";
        } elseif ($domainMode === 'existing') {
            $description .= " (dominio propio: {$domain})";
        }

        $qrCode = app(\Aero\Pay\Classes\QrIssuer::class)->issue(
            bankAccount: $bankAccount,
            amount: $amount,
            currency: 'BOB',
            description: $description,
            origin: 'sites',
            tenantId: $tenant->id,
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
            'period'       => $period,
            'amount'       => $amount,
            'own_domain'   => $domainMode !== '' ? $domain : null,
            'due_date'     => $qrCode->due_date?->toFormattedDateString(),
            // Momento exacto (no solo la fecha de due_date, que es de día
            // completo) en que Console\ReleaseExpiredSignups va a anular
            // este QR y liberar el handle — el temporizador de signup.js
            // cuenta regresivo hasta aquí, no hasta due_date.
            'expires_at'   => $tenant->created_at->addMinutes(Settings::getSignupPaymentTtlMinutes())->toIso8601String(),
            'qr_image'     => $qrCode->qr_image ? 'data:image/png;base64,' . $qrCode->qr_image : null,
        ];
    }

    /**
     * Prueba gratis (sin código) o canje de cupón/invitación de Aero.Credits
     * (con código): en ambos casos sin QR ni pago, aprovisiona el sitio y lo
     * activa al instante (el paso 2 del front salta directo a crear el
     * administrador). La prueba normal vence sola: aero.sites:expire-trials.
     * Un canje con código pisa el plan/periodo con el del regalo
     * (Aero\Credits\Classes\Grants::apply(), llamado desde
     * Invitations::consume()/Coupons::consume()).
     */
    protected function activateFree(Tenant $tenant, array $planData, RootDomain $rootDomain, string $niche, string $domainMode, string $domain, ?array $redemption = null): array
    {
        $tenant->signup_payment_reference = (string) Str::uuid();
        $tenant->save();

        try {
            app(\Aero\Sites\Classes\TenantProvisioner::class)->provisionSite($tenant);
            $tenant->status = 'active';

            if (!$redemption) {
                $tenant->plan_expires_at = now()->addDays((int) $planData['trial_days']);
            }

            $tenant->save();

            if ($redemption) {
                if ($redemption['type'] === 'invitation') {
                    \Aero\Credits\Classes\Invitations::consume($redemption['model'], $tenant);
                } else {
                    \Aero\Credits\Classes\Coupons::consume($redemption['model'], $tenant);
                }
                $tenant->refresh();
            }
        } catch (\Exception $e) {
            \Log::error("Aero\\Sites: fallo al aprovisionar el alta gratis del tenant {$tenant->id}: " . $e->getMessage());
            $tenant->purge();

            return ['success' => false, 'message' => 'No pudimos crear tu sitio. Intenta de nuevo en unos minutos.'];
        }

        return [
            'success'     => true,
            'trial'       => !$redemption,
            'free'        => true,
            'tenant_id'   => $tenant->id,
            'reference'   => $tenant->signup_payment_reference,
            'domain'      => $tenant->handle . '.' . $rootDomain->domain,
            'niche_label' => app(NicheManager::class)->options()[$niche] ?? $niche,
            'plan_label'  => $redemption ? $redemption['plan']->name : $planData['label'],
            'period'      => $redemption ? 'promo' : 'trial',
            'amount'      => 0,
            'own_domain'  => $domainMode !== '' ? $domain : null,
            'trial_ends_at' => $tenant->plan_expires_at->toFormattedDateString(),
            'expires_at'  => null,
            'qr_image'    => null,
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
     * Formato solamente — cuando se elige 'register', la disponibilidad ya
     * se validó en el navegador contra la API de clouds.com.bo (ver
     * docblock de onCreateSignup()); cuando es 'existing', no hay
     * disponibilidad que validar, es del cliente.
     */
    protected function validateDomainFormat(string $domain, string $plan): ?string
    {
        if (!(SignupPlans::find($plan)['is_pro'] ?? false)) {
            return 'El dominio propio solo está disponible en el plan Pro.';
        }

        if ($domain === '') {
            return 'Escribe el dominio.';
        }

        if (!preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.[a-z]{2,24}$/i', $domain)) {
            return 'El dominio no tiene un formato válido.';
        }

        return null;
    }
}
