<?php namespace Aero\Notify\Console;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

/**
 * Busca envíos transaccionales que esquivan el gateway (Mail::send/html/raw/to
 * y Hello::send/sendToContact) fuera de los sitios permitidos. Sale con código
 * 1 si encuentra alguno, para poder usarlo en CI o en un hook de pre-commit.
 */
class AuditDirectSends extends Command
{
    protected $signature = 'notify:audit';

    protected $description = 'Detecta envíos directos que no pasan por Aero.Notify.';

    /** Patrones de envío directo. */
    protected const PATTERNS = '/(Mail::(send|raw|html|to|queue|mailer)\b|Hello::(send|sendToContact)\b|Sms::send\b)/';

    /**
     * Sitios legítimos: transportes del propio gateway, mensajería conversacional
     * (un agente/bot escribe a mano, no es transaccional) y el producto SMS.
     */
    protected const ALLOWED = [
        'aero/notify/', 'aero/hello/', 'aero/wapi/', 'aero/sms/', 'aero/chat/',
        'aero/chatbots/', 'aero/crm/controllers/Contacts.php',
    ];

    public function handle(): int
    {
        $files = (new Finder())->files()->in(plugins_path('aero'))->name('*.php')
            ->notPath('updates')->notPath('vendor')->notPath('node_modules');

        $hits = [];

        foreach ($files as $file) {
            $rel = 'aero/' . str_replace('\\', '/', $file->getRelativePathname());

            foreach (static::ALLOWED as $allowed) {
                if (str_starts_with($rel, $allowed)) {
                    continue 2;
                }
            }

            foreach (file($file->getRealPath()) as $n => $line) {
                if (preg_match(static::PATTERNS, $line) && !preg_match('#^\s*(//|\*|/\*)#', $line)) {
                    $hits[] = [$rel, $n + 1, trim($line)];
                }
            }
        }

        if (!$hits) {
            $this->info('Ningún envío directo fuera del gateway.');
            return 0;
        }

        $this->table(['Archivo', 'Línea', 'Código'], $hits);
        $this->error(count($hits) . ' envío(s) directo(s) fuera de Aero.Notify.');

        return 1;
    }
}
