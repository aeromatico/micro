<?php namespace Aero\Tickets\Classes;

use Aero\Tickets\Models\Settings;
use Aero\Tickets\Models\Template;

/** Avisos editables. Si la plantilla falta o está vacía se usa la de fábrica; una plantilla desactivada no envía (salvo las críticas). */
class Templates
{
    /** @return array{subject:string,html:string}|null null = el administrador la desactivó */
    public static function render(string $code, array $vars): ?array
    {
        $default = TemplateDefaults::all()[$code] ?? null;
        $row = Template::where('code', $code)->first();

        if ($row && !$row->is_active && !in_array($code, TemplateDefaults::CRITICAL, true)) {
            return null;
        }
        $subject = ($row?->subject ?: $default['subject'] ?? $code);
        $body = ($row?->body ?: $default['body'] ?? '');

        $vars += ['support_html' => self::supportHtml()];

        return ['subject' => self::fill($subject, $vars, false), 'html' => self::fill($body, $vars, true)];
    }

    /** {{x}} escapado; {{x_html}} sin escapar (lo arma el sistema, nunca el usuario). */
    public static function fill(string $text, array $vars, bool $html): string
    {
        return preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', function ($m) use ($vars, $html) {
            $k = $m[1];
            $v = (string) ($vars[$k] ?? '');
            if (str_ends_with($k, '_html')) {
                return $html ? $v : strip_tags($v);
            }

            return $html ? e($v) : $v;
        }, $text);
    }

    public static function supportHtml(): string
    {
        $mail = Settings::get('support_email');
        $phone = Settings::get('support_phone');
        if (!$mail && !$phone) {
            return '';
        }

        return '<p style="color:#666;font-size:12px">¿Necesitas ayuda? ' . e(trim(($mail ?: '') . ($mail && $phone ? ' · ' : '') . ($phone ?: ''))) . '</p>';
    }

    public static function send(string $to, ?string $toName, string $code, array $vars): bool
    {
        $r = self::render($code, $vars);
        if (!$r || !$to) {
            return false;
        }
        try {
            \Mail::html($r['html'], fn ($m) => $m->to($to, $toName)->subject($r['subject']));

            return true;
        } catch (\Throwable $e) {
            \Log::warning("aero.tickets: no se pudo enviar la plantilla {$code}: " . $e->getMessage());

            return false;
        }
    }
}
