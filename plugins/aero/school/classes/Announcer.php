<?php namespace Aero\School\Classes;

use Aero\School\Models\Announcement;
use Aero\School\Models\Course;
use Aero\School\Models\Delivery;
use Aero\School\Models\Guardian;
use Aero\School\Models\Student;

/** Comunicados y circulares por WhatsApp a los apoderados. */
class Announcer
{
    /** Apoderados únicos (por teléfono) de los alumnos inscritos en la audiencia. */
    public function recipients(Announcement $a)
    {
        $courseIds = match ($a->audience) {
            'course' => [$a->course_id],
            'level'  => Course::where('tenant_id', $a->tenant_id)->where('level', $a->level)->pluck('id')->all(),
            default  => null,
        };

        $studentIds = Student::where('tenant_id', $a->tenant_id)->where('status', 'active')
            ->when($courseIds !== null, fn ($q) => $q->whereIn('course_id', $courseIds ?: [0]))->pluck('id');

        return Guardian::where('tenant_id', $a->tenant_id)
            ->whereHas('students', fn ($q) => $q->whereIn('aero_school_students.id', $studentIds))
            ->get()
            ->filter(fn (Guardian $g) => preg_replace('/\D+/', '', (string) $g->phone))
            ->unique(fn (Guardian $g) => preg_replace('/\D+/', '', (string) $g->phone))
            ->values();
    }

    public function compose(Announcement $a, ?Guardian $g = null): string
    {
        $body = "📢 *{$a->title}*\n\n" . trim($a->body);

        return $body . "\n\n— " . Notifier::schoolName($a->tenant_id);
    }

    /** Envía a todos los destinatarios que aún no recibieron este comunicado (reintentable). */
    public function send(Announcement $a): array
    {
        $a->update(['status' => 'sending']);
        $already = Delivery::where('announcement_id', $a->id)->where('status', 'sent')->pluck('guardian_id')->all();
        $recipients = $this->recipients($a);
        $ok = count($already);
        $fail = 0;

        foreach ($recipients as $g) {
            if (in_array($g->id, $already, true)) {
                continue;
            }
            $sent = Notifier::whatsapp($a->tenant_id, $g->phone, $this->compose($a, $g), $g->name, $a->attachment_url);
            Delivery::create([
                'announcement_id' => $a->id, 'guardian_id' => $g->id, 'phone' => $g->phone,
                'status' => $sent ? 'sent' : 'failed', 'error' => $sent ? null : 'No se pudo enviar', 'created_at' => now(),
            ]);
            $sent ? $ok++ : $fail++;
        }

        $a->update([
            'status' => 'sent', 'sent_at' => $a->sent_at ?: now(),
            'total' => $recipients->count(), 'delivered' => $ok, 'failed' => $fail,
        ]);
        \Event::fire('aero.school.announcementSent', [$a]);

        return ['total' => $recipients->count(), 'delivered' => $ok, 'failed' => $fail];
    }
}
