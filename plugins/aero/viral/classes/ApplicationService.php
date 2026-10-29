<?php namespace Aero\Viral\Classes;

use Aero\Viral\Models\Application;
use Aero\Viral\Models\Sample;
use Auth;
use Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use October\Rain\Exception\ApplicationException;
use October\Rain\Exception\ValidationException;
use RainLab\User\Models\User;

/** Alta de la postulación: crea o reconoce al usuario de RainLab.User y guarda perfil y muestras. */
class ApplicationService
{
    public const MAX_LINKS = 5;

    /** @return Application postulación creada, o la que la persona ya tenía en curso */
    public function submit(array $input, ?string $ip = null): Application
    {
        $key = 'viral-apply:' . ($ip ?: 'x');
        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw new ApplicationException('Demasiados intentos. Prueba de nuevo en unos minutos.');
        }
        RateLimiter::hit($key, 600);

        // Trampa para bots: un humano nunca ve este campo.
        if (!empty($input['website'])) {
            throw new ApplicationException('No pudimos procesar tu solicitud.');
        }

        $current = Auth::user();
        $data = $this->validate($input, (bool) $current);

        return DB::transaction(function () use ($data, $current) {
            $user = $current ?: $this->resolveUser($data);

            $existing = Application::where('user_id', $user->id)->where('status', '!=', 'rejected')->latest('id')->first();
            if ($existing) {
                return $existing;
            }

            $app = Application::create([
                'user_id' => $user->id,
                'track' => $data['track'],
                'level' => $data['level'],
                'tags' => $data['tags'],
                'description' => $data['description'] ?? null,
                'phone' => $data['phone'],
                'department' => $data['department'],
                'city' => $data['city'],
                'offer_to_clients' => $data['offer'],
                'terms_accepted_at' => now(),
                'adult_confirmed_at' => now(),
            ]);

            foreach ($data['links'] as $url) {
                Sample::create([
                    'application_id' => $app->id, 'kind' => 'link',
                    'url' => $url, 'platform' => Sample::detectPlatform($url), 'status' => 'ready',
                ]);
            }

            $app = $app->fresh();
            Event::fire('aero.viral.applicationSubmitted', [$app]);

            return $app;
        });
    }

    protected function validate(array $in, bool $loggedIn): array
    {
        $in['phone'] = Catalog::normalizePhone($in['phone'] ?? null);
        $in['links'] = collect((array) ($in['links'] ?? []))
            ->map(fn ($u) => trim((string) $u))->filter()->map([Catalog::class, 'normalizeUrl'])->unique()->values()->all();
        $in['tags'] = array_values(array_unique(array_filter((array) ($in['tags'] ?? []), 'is_string')));
        $in['offer'] = filter_var($in['offer'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $rules = [
            'track' => 'required|in:shorts,live,edit',
            'level' => 'required|integer|between:1,4',
            'links' => 'required|array|min:1|max:' . self::MAX_LINKS,
            'links.*' => ['required', 'url', 'max:500', 'regex:#^https?://([\w-]+\.)+[a-z]{2,}(/\S*)?$#i'],
            'tags' => 'array|max:30',
            'tags.*' => 'string|max:60',
            'description' => 'nullable|string|max:600',
            'phone' => 'required',
            'department' => 'required|in:' . implode(',', array_keys(Catalog::DEPARTMENTS)),
            'city' => 'required|string|min:2|max:96',
            'terms' => 'accepted',
            'adult' => 'accepted',
        ];
        if (!$loggedIn) {
            $rules += ['name' => 'required|string|min:2|max:120', 'email' => 'required|email|max:255', 'password' => 'required|string|min:8|max:200'];
        }

        $v = Validator::make($in, $rules, [
            'required' => 'Este dato es obligatorio.',
            'links.required' => 'Pega al menos un link.',
            'links.min' => 'Pega al menos un link.',
            'links.*.url' => 'Eso no parece un link.',
            'links.*.regex' => 'Eso no parece un link.',
            'phone.required' => 'Escribe un celular boliviano de 8 dígitos (empieza en 6 o 7).',
            'department.in' => 'Elige un departamento.',
            'email' => 'Revisa tu correo.',
            'password.min' => 'Mínimo 8 caracteres.',
            'terms.accepted' => 'Debes aceptar los términos y la privacidad.',
            'adult.accepted' => 'Debes confirmar que eres mayor de edad.',
        ]);
        if ($v->fails()) {
            throw new ValidationException($v);
        }

        // Solo los tags del camino y de la lista (descarta basura enviada a mano).
        $allowed = array_merge(...array_values(Catalog::TAGS));
        $in['tags'] = array_values(array_intersect($in['tags'], $allowed));

        return $in;
    }

    protected function resolveUser(array $d): User
    {
        $email = mb_strtolower(trim($d['email']));
        $exists = User::where('email', $email)->where('is_guest', false)->exists();

        if ($exists) {
            // Cuenta existente: solo se vincula quien demuestra que es suya.
            if (!Auth::attempt(['email' => $email, 'password' => $d['password']], true)) {
                throw new ValidationException(['password' => 'Ese correo ya tiene una cuenta. Escribe su contraseña para continuar.']);
            }

            return Auth::user();
        }

        $parts = preg_split('/\s+/', trim($d['name']), 2);
        $user = new User();
        $user->first_name = $parts[0];
        $user->last_name = $parts[1] ?? null;
        $user->email = $email;
        $user->username = $email;
        $user->password = $d['password'];
        $user->password_confirmation = $d['password'];
        $user->activated_at = now();
        $user->save();

        Auth::login($user, true);

        return $user;
    }

    /** Resumen seguro para la pantalla de estado (nada interno). */
    public static function summary(?Application $app): ?array
    {
        if (!$app) {
            return null;
        }

        $kyc = $app->kyc_status;
        $decided = in_array($app->status, ['approved', 'rejected'], true);

        return [
            'status' => $app->status,
            'status_label' => $app->status_label,
            'kyc_status' => $kyc,
            'track' => $app->track,
            'notes' => $app->status === 'changes_requested' ? $app->review_notes : null,
            'stages' => [
                ['label' => 'Muestra recibida', 'done' => true],
                ['label' => 'Cuenta creada', 'done' => true],
                ['label' => 'Verificación de identidad', 'done' => $kyc === 'verified', 'state' => $kyc],
                ['label' => 'Revisión por nuestro equipo', 'done' => $decided, 'state' => $app->status],
                ['label' => $app->status === 'rejected' ? 'Resultado: no continúa por ahora' : 'Resultado: te contactamos', 'done' => $decided],
            ],
        ];
    }
}
