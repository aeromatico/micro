<?php namespace Aero\Office\Classes;

use Aero\Office\Models\Customer;
use Illuminate\Support\Str;
use RainLab\User\Models\User;

/** Vincula al cliente con un usuario de RainLab.User (opcional: se puede reservar sin cuenta). */
class CustomerUsers
{
    /** true si la última llamada a ensureUser() creó el usuario (no solo lo vinculó). */
    public bool $created = false;

    /**
     * Devuelve el usuario del cliente, creándolo si no existe. Con $resetUrl, un usuario
     * NUEVO recibe la invitación de RainLab para crear su contraseña (nunca se inicia sesión
     * por él: quien reservó no demostró ser dueño del correo).
     */
    public function ensureUser(Customer $customer, ?string $resetUrl = null, ?\Aero\Office\Models\Booking $booking = null): User
    {
        $this->created = false;

        if ($customer->user_id && ($user = User::find($customer->user_id))) {
            return $user;
        }
        if (!$customer->email) {
            throw new OfficeException('El cliente necesita un correo para crear su usuario.');
        }

        $user = User::where('email', $customer->email)->where('is_guest', false)->first();
        if ($user && Customer::where('tenant_id', $customer->tenant_id)->where('user_id', $user->id)->where('id', '!=', $customer->id)->exists()) {
            throw new OfficeException('Ese usuario ya está vinculado a otro cliente del negocio.');
        }

        if (!$user) {
            $password = Str::password(24);
            $parts = preg_split('/\s+/', trim($customer->name), 2);
            $user = new User();
            $user->first_name = $parts[0];
            $user->last_name = $parts[1] ?? null;
            $user->email = $customer->email;
            $user->username = $customer->email;
            $user->password = $password;
            $user->password_confirmation = $password;
            $user->activated_at = now();
            $user->save();
            $this->created = true;
        }

        $customer->user_id = $user->id;
        $customer->save();

        if ($this->created && $resetUrl) {
            $this->sendAccountEmail($customer, $user, $resetUrl, $booking);
        }

        return $user;
    }

    /**
     * Correo al email de la reserva con el usuario y el enlace para crear la contraseña. Va por el gateway de Notify
     * (plantilla en español, con el nombre del negocio); solo si Notify no está se usa la invitación genérica de RainLab.
     */
    protected function sendAccountEmail(Customer $customer, User $user, string $resetUrl, ?\Aero\Office\Models\Booking $booking): void
    {
        if (Notifier::$muted) {
            return;
        }

        // 1) Por el gateway de Notify (plantilla en español con el nombre del negocio).
        try {
            if (Notifier::available()) {
                $token = \Illuminate\Support\Facades\Password::createToken($user);
                $url = $resetUrl . (str_contains($resetUrl, '?') ? '&' : '?') . http_build_query(['reset' => $token, 'email' => $user->email, 'new' => true]);
                if (Notifier::accountCreated($customer, $url, (int) \Config::get('auth.passwords.users.expire'), $booking)) {
                    return;
                }
                \Log::error('[office] El correo de cuenta no salió por Notify (cliente ' . $customer->id . '); se usa el respaldo directo.');
            }
        } catch (\Throwable $e) {
            \Log::error('[office] Falló el correo de cuenta por Notify (cliente ' . $customer->id . '): ' . $e->getMessage());
        }

        // 2) Respaldo: nunca dejar al cliente sin correo. Invitación de RainLab con el mismo enlace.
        try {
            $user->setUrlForPasswordReset($resetUrl);
            $user->sendConfirmRegistrationNotification();
        } catch (\Throwable $e) {
            \Log::error('[office] No se pudo enviar ningún correo de cuenta al cliente ' . $customer->id . ': ' . $e->getMessage());
        }
    }
}
