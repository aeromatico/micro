<?php namespace Aero\Church\Classes;

use Aero\Church\Models\Announcement;
use Aero\Church\Models\ChurchSettings;
use Aero\Church\Models\Member;

/**
 * Felicitaciones de cumpleaños por WhatsApp. Apagado por defecto por iglesia. Idempotente por día:
 * deja un comunicado «Cumpleaños AAAA-MM-DD» (historial). Los menores no reciben felicitación (ni a su tutor).
 */
class Birthdays
{
    public const DEFAULT_TEMPLATE = '¡Feliz cumpleaños, {nombre}! 🎂 Que Dios te bendiga en este nuevo año de vida. Tu familia de la iglesia.';

    public function run(?\Carbon\Carbon $day = null): int
    {
        $day = $day ?: now();
        $sent = 0;

        ChurchSettings::where('birthday_enabled', true)->get()->each(function (ChurchSettings $s) use ($day, &$sent) {
            $title = 'Cumpleaños ' . $day->toDateString();
            if (Announcement::where('tenant_id', $s->tenant_id)->where('kind', 'birthday')->where('title', $title)->exists()) {
                return;
            }

            $ids = Member::forTenant((int) $s->tenant_id)->whereIn('status', ['active', 'new'])
                ->where('birth_date', '<=', $day->copy()->subYears(18)->toDateString())->whereMonth('birth_date', $day->month)->whereDay('birth_date', $day->day)->pluck('id')->all();
            if (!$ids) {
                return;
            }

            $svc = app(AnnouncementService::class);
            $recip = app(Recipients::class);
            $a = Announcement::create([
                'tenant_id' => $s->tenant_id, 'title' => $title, 'kind' => 'birthday', 'body' => $s->birthday_template ?: self::DEFAULT_TEMPLATE,
                'audience' => ['member_ids' => $ids],
            ]);
            $a->forceFill(['status' => 'sending'])->saveQuietly();
            $svc->deliver($a);
            $sent += $a->fresh()->sent_count;
        });

        return $sent;
    }
}
