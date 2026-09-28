<?php namespace Aero\WpFlash\Classes;

use Symfony\Component\Process\Process;
use Aero\WpFlash\Models\Settings;

/**
 * WordPress vive en este mismo servidor, así que el provisioning corre
 * WP-CLI directo (mismo mecanismo que bin/nuevo-sitio.sh, ya en uso manual
 * en wp.market.com.bo) en vez de montar un puente HTTP/mu-plugin. Los
 * argumentos siempre van como array a Process (nunca interpolados en un
 * string de shell) para que Symfony los escape — evita inyección de shell
 * aunque el slug/handle del tenant venga de datos del usuario.
 */
class WpCli
{
    public function run(array $args): array
    {
        $process = new Process(array_merge(
            [Settings::phpBinary(), '-d', 'error_reporting=0', Settings::wpCliBinary()],
            $args,
            ['--path=' . Settings::wpPath(), '--allow-root']
        ));
        $process->setTimeout(120);
        $process->run();

        return [
            'successful' => $process->isSuccessful(),
            'output'     => trim($process->getOutput()),
            'error'      => trim($process->getErrorOutput()),
        ];
    }

    public function isWooCommerceNetworkActive(): bool
    {
        $result = $this->run(['plugin', 'is-active', 'woocommerce', '--network']);

        return $result['successful'];
    }

    /** Idempotente: si el usuario ya existe (reintento de un provisioning fallido), lo ignora. */
    public function ensureUser(string $username, string $email, string $password): void
    {
        $this->run(['user', 'create', $username, $email, '--role=administrator', "--user_pass={$password}", '--porcelain']);
    }

    public function createSite(string $slug, string $title, string $email): ?int
    {
        $result = $this->run(['site', 'create', "--slug={$slug}", "--title={$title}", "--email={$email}", '--porcelain']);

        return ($result['successful'] && ctype_digit($result['output'])) ? (int) $result['output'] : null;
    }

    public function createApplicationPassword(string $username, string $label, string $siteUrl): ?string
    {
        $result = $this->run(['user', 'application-password', 'create', $username, $label, '--porcelain', "--url={$siteUrl}"]);

        return $result['successful'] ? $result['output'] : null;
    }

    public function updateOption(string $siteUrl, string $option, string $value): bool
    {
        return $this->run(['option', 'update', $option, $value, "--url={$siteUrl}"])['successful'];
    }

    /**
     * "Domain mapping": cambia el dominio con el que WordPress reconoce este
     * blog (tabla wp_blogs, con update_blog_details() para que WP invalide
     * su propia caché — nunca un UPDATE crudo). `$currentUrl` debe ser un URL
     * por el que el sitio SIGA siendo direccionable en este momento (antes de
     * mapear: {slug}.wp.market.com.bo; para revertir: el dominio actual del
     * tenant).
     */
    public function remapDomain(string $currentUrl, string $newDomain): bool
    {
        $code = sprintf(
            'echo update_blog_details(get_current_blog_id(), [%s => %s]) ? "1" : "0";',
            var_export('domain', true),
            var_export($newDomain, true)
        );

        $result = $this->run(['eval', $code, "--url={$currentUrl}"]);

        return $result['successful'] && trim($result['output']) === '1';
    }
}
