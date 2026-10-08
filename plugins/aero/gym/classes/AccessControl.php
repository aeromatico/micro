<?php namespace Aero\Gym\Classes;

use Aero\Gym\Models\AccessLog;
use Aero\Gym\Models\Booking;
use Aero\Gym\Models\GymSettings;
use Aero\Gym\Models\Member;

/**
 * Valida un QR o carnet. El tenant lo fija quien llama (panel o API futura):
 * un código de otro gimnasio es simplemente "desconocido".
 */
class AccessControl
{
    /** Prueba varios gimnasios (los del usuario) y registra el ingreso en el que reconoce la credencial. */
    public function checkAny(array $tenantIds, string $credential, string $method = 'qr'): array
    {
        $tenantIds = array_values($tenantIds);
        foreach ($tenantIds as $id) {
            $probe = $this->check($id, $credential, $method, false);
            if ($probe['member']) {
                return $this->check($id, $credential, $method) + ['tenant_id' => $id];
            }
        }

        return $this->check($tenantIds[0], $credential, $method) + ['tenant_id' => $tenantIds[0]];
    }

    /**
     * Modo gym_qr: el socio (ya identificado por su sesión) escaneó el QR del
     * gimnasio. El token fija el tenant; el socio debe ser de ese tenant.
     */
    public function checkWithGymQr(Member $member, string $token): array
    {
        $tenantId = app(GymQr::class)->verify($token);
        if (!$tenantId || $tenantId !== (int) $member->tenant_id) {
            return ['granted' => false, 'reason' => 'QR vencido o de otro gimnasio. Escanea el que muestra la pantalla.', 'member' => ['id' => $member->id, 'name' => $member->name],
                'ends_on' => null, 'days_left' => null, 'session_id' => null];
        }
        if (GymSettings::accessMode($tenantId) !== 'gym_qr') {
            return ['granted' => false, 'reason' => 'Este gimnasio no usa el escaneo con el móvil.', 'member' => ['id' => $member->id, 'name' => $member->name],
                'ends_on' => null, 'days_left' => null, 'session_id' => null];
        }

        return $this->check($tenantId, (string) $member->qr_token, 'gym_qr');
    }

    public function check(int $tenantId, string $credential, string $method = 'qr', bool $log = true): array
    {
        $credential = trim($credential);

        $member = Member::where('tenant_id', $tenantId)
            ->where(fn ($q) => $q->where('qr_token', $credential)->orWhere('card_number', $credential)->orWhere('document', $credential))
            ->first();

        $granted = false;
        $reason = null;
        $membership = null;

        if (!GymSettings::isEnabled($tenantId)) {
            $reason = 'Sistema desactivado';
        } elseif (!$member) {
            $reason = 'Credencial desconocida';
        } elseif ($member->status !== 'active') {
            $reason = 'Socio ' . ($member->status === 'suspended' ? 'suspendido' : 'inactivo');
        } else {
            $grace = (int) (GymSettings::forTenant($tenantId)->grace_days ?? 0);
            $membership = $member->currentMembership($grace);
            if ($membership) {
                $granted = true;
            } else {
                $reason = 'Sin membresía vigente';
            }
        }

        $sessionId = null;
        if ($granted) {
            $sessionId = $this->markAttendance($member);
        }

        if ($log) {
            AccessLog::create([
                'tenant_id'  => $tenantId,
                'member_id'  => $member?->id,
                'credential' => mb_substr($credential, 0, 40),
                'method'     => $method,
                'granted'    => $granted,
                'reason'     => $reason,
                'session_id' => $sessionId,
            ]);
        }

        return [
            'granted'    => $granted,
            'reason'     => $reason,
            'member'     => $member ? ['id' => $member->id, 'name' => $member->name] : null,
            'ends_on'    => $membership?->ends_on?->toDateString(),
            'days_left'  => $membership?->days_left,
            'session_id' => $sessionId,
        ];
    }

    /** Si el socio tiene una clase reservada que empieza pronto o está en curso, queda con asistencia. */
    protected function markAttendance(Member $member): ?int
    {
        $booking = Booking::where('member_id', $member->id)->where('status', 'booked')
            ->whereHas('session', fn ($q) => $q->where('status', 'scheduled')
                ->where('starts_at', '<=', now()->addMinutes(30))->where('ends_at', '>=', now()))
            ->orderBy('id')->first();

        if (!$booking) {
            return null;
        }

        app(BookingService::class)->checkIn($booking);

        return $booking->session_id;
    }
}
