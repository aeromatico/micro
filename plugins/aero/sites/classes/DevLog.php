<?php namespace Aero\Sites\Classes;

use Cache;
use Lang;
use System\Classes\PluginManager;
use System\Classes\VersionManager;

/**
 * Datos de la página pública /dev: plugins con su versión aplicada y
 * bitácora de mejoras (commits `feat`/`fix`/`perf` del repo). Cacheado
 * porque `git log` sobre ~750 commits no debe correr en cada visita.
 */
class DevLog
{
    const CACHE_KEY = 'aero.sites.devlog';
    const TTL = 3600;

    const TYPES = [
        'feat' => 'Novedad',
        'fix' => 'Corrección',
        'improve' => 'Mejora',
        'perf' => 'Rendimiento',
    ];

    /**
     * Palabras que no se muestran en la bitácora pública (sin distinguir
     * mayúsculas): la entrada que las contenga se omite.
     */
    const HIDDEN_WORDS = ['zernio', 'floogos'];

    /** Descripciones breves y públicas por plugin (slug => texto). */
    const SHORT = [
        'aifields' => 'Asistencia de IA en los campos de formulario.',
        'api' => 'API keys con permisos por área.',
        'chat' => 'Mensajería multiagente en tiempo real.',
        'chatbots' => 'Chatbots de autorespuesta para tu negocio.',
        'community' => 'Foro, sugerencias y soporte rápido.',
        'connector' => 'Conecta APIs, webhooks y modelos de IA.',
        'credits' => 'Créditos prepago para IA y servicios.',
        'crm' => 'Contactos, leads, pipeline y cobranzas.',
        'docs' => 'Documentación propia de cada negocio.',
        'finance' => 'Ingresos, egresos y libros contables.',
        'gym' => 'Socios, membresías y clases de gimnasio.',
        'hello' => 'Centro de mensajes y notificaciones multicanal.',
        'help' => 'Ayuda guiada dentro del panel.',
        'hub' => 'Modelos de IA y APIs de datos bajo una sola cuenta.',
        'invoicing' => 'Facturación electrónica para Bolivia.',
        'livechat' => 'Chat en vivo para tu sitio web.',
        'masterads' => 'Optimización de campañas publicitarias con IA.',
        'mcp' => 'Conecta tu negocio con asistentes de IA.',
        'medical' => 'Historia clínica para consultorios y veterinarias.',
        'notify' => 'Notificaciones automáticas por varios canales.',
        'oauth' => 'Acceso con Google y otros proveedores.',
        'office' => 'Reservas de citas y servicios.',
        'pay' => 'Cobros con QR bancario y otros medios.',
        'pos' => 'Punto de venta para mostrador y mesas.',
        'rgpd' => 'Aviso de cookies y consentimiento.',
        'school' => 'Gestión de colegios: cobranza, asistencia y boletines.',
        'services' => 'Catálogo de servicios de la plataforma.',
        'sheets' => 'Importa y exporta con Google Sheets.',
        'shop' => 'Tienda en línea con pedidos y catálogo.',
        'shopify' => 'Cobro con QR para tiendas Shopify.',
        'shortcuts' => 'Atajos personalizables en el panel.',
        'sites' => 'Sitios web multitenant por rubro.',
        'sms' => 'Envío de mensajes SMS.',
        'telegram' => 'Telegram como canal de atención.',
        'tracking' => 'Seguimiento de flotas, personas y trabajos.',
        'wapi' => 'WhatsApp con sesión propia por QR.',
        'workflows' => 'Automatizaciones por nodos.',
        'workspaces' => 'Agentes de IA contratables para tu equipo.',
        'wpflash' => 'Sitios WordPress/WooCommerce administrados.',
    ];

    public static function data(): array
    {
        $snapshot = self::snapshot();

        return [
            'plugins' => Cache::remember(self::CACHE_KEY, self::TTL, fn () => self::plugins()),
            'log' => self::visible($snapshot['log'] ?? []),
            'stats' => ($snapshot['stats'] ?? []) + ['commits' => 0, 'since' => null, 'updated' => date('Y-m-d')],
        ];
    }

    public static function snapshotPath(): string
    {
        return storage_path('app/devlog.json');
    }

    protected static function snapshot(): array
    {
        $path = self::snapshotPath();
        $json = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return is_array($json) ? $json : [];
    }

    /**
     * Regenera el snapshot desde git. Solo desde CLI: PHP-FPM tiene
     * shell_exec deshabilitada, por eso la web lee el archivo.
     */
    public static function refresh(): array
    {
        $data = ['log' => self::log(), 'stats' => self::stats()];
        file_put_contents(self::snapshotPath(), json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);

        return $data;
    }

    public static function plugins(): array
    {
        $versions = VersionManager::instance();
        $out = [];

        foreach (PluginManager::instance()->getPlugins() as $code => $plugin) {
            if (stripos($code, 'Aero.') !== 0) {
                continue;
            }
            $details = $plugin->pluginDetails();
            $name = (string) ($details['name'] ?? $code);
            if (str_contains($name, '::')) {
                $name = Lang::get($name);
            }
            $description = (string) ($details['description'] ?? '');
            if (str_contains($description, '::')) {
                $description = Lang::get($description);
                $description = str_contains($description, '::') ? '' : $description;
            }
            $slug = strtolower(substr($code, 5));
            $out[] = [
                'slug' => $slug,
                'name' => str_contains($name, '::') ? ucfirst($slug) : $name,
                'version' => $versions->getLatestVersion($code) ?: '1.0.0',
                'description' => self::SHORT[$slug] ?? $description,
            ];
        }

        usort($out, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return $out;
    }

    /**
     * Bitácora: una entrada por cada nota de changelog que los commits
     * añadieron a un `updates/version.yaml` (así un commit que mezcla
     * novedades y correcciones se reparte bien), más los commits
     * feat/fix/perf manuales que no tocan ningún version.yaml.
     */
    public static function log(int $limit = 600): array
    {
        $entries = array_merge(self::versionNotes(), self::manualCommits());

        usort($entries, fn ($a, $b) => [$b['date'], $b['order']] <=> [$a['date'], $a['order']]);

        return array_map(function ($e) {
            unset($e['order']);
            return $e;
        }, array_slice($entries, 0, $limit));
    }

    protected static function versionNotes(): array
    {
        $raw = self::git(['log', '--no-merges', '--date=short', '--format=@@%h|%ad', '-p', '-U0', '--', 'plugins/aero/*/updates/version.yaml']);
        $entries = [];
        $hash = $date = $plugin = $pending = null;
        $order = 0;

        foreach (explode("\n", $raw) as $line) {
            if (preg_match('/^@@([0-9a-f]+)\|(\S+)$/', $line, $m)) {
                [, $hash, $date] = $m;
                $order = 0;
                continue;
            }
            if (preg_match('#^\+\+\+ b/plugins/aero/([^/]+)/updates/version\.yaml#', $line, $m)) {
                $plugin = $m[1];
                $pending = null;
                continue;
            }
            if (!$plugin || !str_starts_with($line, '+') || str_starts_with($line, '+++')) {
                continue;
            }
            $line = substr($line, 1);

            if (preg_match('/^(\d+\.\d+\.\d+):\s*(?:"(.*)")?\s*$/', $line, $m)) {
                if (!empty($m[2])) {
                    $entries[] = self::note($hash, $date, $plugin, $m[2], --$order);
                    $pending = null;
                } else {
                    $pending = $m[1];
                }
                continue;
            }
            if ($pending && preg_match('/^\s+-\s+(?:"(.*)"|(.+?))\s*$/', $line, $m)) {
                $text = ($m[1] ?? '') !== '' ? $m[1] : ($m[2] ?? '');
                if ($text !== '' && !str_ends_with($text, '.php')) {
                    $entries[] = self::note($hash, $date, $plugin, $text, --$order);
                    $pending = null;
                }
            }
        }

        return array_values(array_filter($entries));
    }

    protected static function manualCommits(): array
    {
        $raw = self::git(['log', '--no-merges', '--date=short', '--format=@@%h|%ad|%s', '--name-only', '-n', '3000']);
        $entries = [];
        $order = 0;

        foreach (preg_split('/^@@/m', $raw) as $block) {
            $lines = array_values(array_filter(array_map('trim', explode("\n", $block))));
            if (!$lines) {
                continue;
            }
            [$hash, $date, $subject] = array_pad(explode('|', array_shift($lines), 3), 3, '');
            if (!preg_match('/^(?:feat|fix|perf)(?:\(([^)]*)\))?!?:\s*(.+)$/u', $subject, $m)) {
                continue;
            }
            if (preg_grep('#updates/version\.yaml$#', $lines)) {
                continue;
            }
            $entries[] = self::note($hash, $date, $m[1] ?: '', $m[2], --$order, $subject);
        }

        return array_filter($entries);
    }

    protected static function note(string $hash, string $date, string $scope, string $text, int $order, ?string $subject = null): ?array
    {
        $text = trim(str_replace(['\\"', '\\\\'], ['"', '\\'], $text));
        if ($text === '' || preg_match('/^El rol tenant_admin recibe/u', $text)) {
            return null;
        }

        return [
            'hash' => $hash,
            'date' => $date,
            'type' => $type = self::classify($subject ?? $text),
            'label' => self::TYPES[$type],
            'scope' => $scope,
            'text' => mb_strlen($text) > 170 ? rtrim(mb_substr($text, 0, 167)) . '…' : $text,
            'order' => $order,
        ];
    }

    /** Corrección, rendimiento o mejora si el texto lo dice; todo lo demás es novedad. */
    protected static function classify(string $text): string
    {
        $t = mb_strtolower($text);

        if (preg_match('/^fix(?:\(|:)/u', $t)
            || preg_match('/^(?:corrige|corrig[eé]|arregla|repara|soluciona|resuelve|evita|hotfix|parche)/u', $t)
            || preg_match('/\b(?:ya no|no (?:se )?(?:duplica|pierde|rompe|contesta)|corrige|corregid[oa]s?|arregla|soluciona|bug|fallaba|fallo|se rompía|rompía)\b/u', $t)) {
            return 'fix';
        }
        if (preg_match('/^perf(?:\(|:)|^(?:optimiza|acelera|reduce)|\b(?:más rápid[oa]|rendimiento|cachea)\b/u', $t)) {
            return 'perf';
        }

        if (preg_match('/\b(?:ahora|antes (?:era|se|solo|de)|en vez de|en lugar de|pasa de|mejora[sd]?o?s?)\b/u', $t)) {
            return 'improve';
        }

        return 'feat';
    }

    protected static function visible(array $log): array
    {
        $pattern = '/' . implode('|', array_map(fn ($w) => preg_quote($w, '/'), self::HIDDEN_WORDS)) . '/iu';

        return array_values(array_filter($log, fn ($e) => !preg_match($pattern, $e['scope'] . ' ' . $e['text'])));
    }

    protected static function stats(): array
    {
        $first = trim(self::git(['log', '--reverse', '--date=short', '--format=%ad', '--max-parents=0']));
        $first = strtok($first, "\n") ?: null;

        return [
            'commits' => (int) trim(self::git(['rev-list', '--count', 'HEAD'])),
            'since' => $first,
            'updated' => trim(self::git(['log', '-1', '--date=short', '--format=%ad'])),
        ];
    }

    protected static function git(array $args): string
    {
        $cmd = 'git -c safe.directory=* -C ' . escapeshellarg(base_path())
            . ' ' . implode(' ', array_map('escapeshellarg', $args)) . ' 2>/dev/null';

        return (string) shell_exec($cmd);
    }
}
