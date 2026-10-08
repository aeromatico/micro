<?php namespace Aero\Sites\Jobs;

use Aero\Sites\Models\PlatformLead;
use Queue;

/**
 * Dispara 'sites.platform_lead.created' en Aero.Notify hacia los
 * superadministradores (audience superadmin — no hay tenant al que avisar).
 * Mismo patrón que DispatchContactNotification, con class_exists() porque
 * Aero.Sites no requiere Aero.Notify.
 */
class DispatchPlatformLeadNotification
{
    public function fire($job, $data): void
    {
        $lead = PlatformLead::find($data['lead_id']);

        if (!$lead) {
            $job->delete();
            return;
        }

        if (class_exists(\Aero\Notify\Classes\Notify::class)) {
            \Aero\Notify\Classes\Notify::fire('sites.platform_lead.created', [
                'plan'              => $lead->plan,
                'name'              => $lead->name,
                'email'             => $lead->email,
                'phone'             => $lead->phone,
                'message'           => $lead->message,
                'verification_url'  => $lead->verification_url,
            ]);

            $lead->markNotified();
        }

        $job->delete();
    }

    public static function dispatch(PlatformLead $lead): void
    {
        Queue::push(static::class, ['lead_id' => $lead->id]);
    }
}
