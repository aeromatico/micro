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
    public function ensureUser(Customer $customer, ?string $resetUrl = null): User
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
            try {
                $user->setUrlForPasswordReset($resetUrl);
                $user->sendConfirmRegistrationNotification();
            } catch (\Throwable $e) {
                \Log::warning('[office] No se pudo enviar la invitación de cuenta: ' . $e->getMessage());
            }
        }

        return $user;
    }
}
