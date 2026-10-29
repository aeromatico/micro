<?php namespace Aero\Viral\Console;

use Aero\Viral\Classes\ViralStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Diagnóstico del almacenamiento de Viral. Corre como `www`:
 *   sudo -u www /www/server/php/84/bin/php artisan viral:s3-check
 * Prueba credenciales, escritura/lectura/borrado, URLs firmadas (PUT y GET), subida multipart,
 * CORS y bloqueo de acceso público. Nunca imprime secretos.
 */
class S3Check extends Command
{
    protected $name = 'viral:s3-check';
    protected $description = 'Comprueba que el bucket de Viral (muestras y KYC) está bien configurado.';

    protected int $fails = 0;
    protected int $warns = 0;

    public function handle(): int
    {
        $c = config('filesystems.disks.' . ViralStorage::DISK, []);

        $this->line('Configuración');
        foreach (['key' => 'VIRAL_S3_KEY (o AWS_ACCESS_KEY_ID)', 'secret' => 'VIRAL_S3_SECRET (o AWS_SECRET_ACCESS_KEY)', 'bucket' => 'VIRAL_S3_BUCKET (o AWS_BUCKET)'] as $k => $label) {
            empty($c[$k]) ? $this->bad("Falta {$label} en .env") : $this->good($label);
        }
        $this->good('Región: ' . ($c['region'] ?? '?') . ' · endpoint: ' . ($c['endpoint'] ?: 'AWS S3') . ' · prefijo: ' . ($c['root'] ?: '(ninguno)'));
        if (!class_exists(\Aws\S3\S3Client::class)) {
            $this->bad('Falta el SDK: composer install (aws/aws-sdk-php).');
        }
        if ($this->fails) {
            return $this->finish();
        }

        $client = ViralStorage::client();
        $bucket = ViralStorage::bucket();
        $path = 'selfcheck/' . bin2hex(random_bytes(6)) . '.txt';
        $key = ViralStorage::fullKey($path);
        $body = 'viral-check-' . now()->toIso8601String();

        $this->line("\nAcceso al bucket");
        try {
            $client->headBucket(['Bucket' => $bucket]);
            $this->good("Bucket «{$bucket}» accesible");
        } catch (\Throwable $e) {
            $this->bad('No se puede acceder al bucket: ' . $this->short($e));

            return $this->finish();
        }

        try {
            ViralStorage::disk()->put($path, $body);
            $this->good('Escritura');
            ViralStorage::disk()->get($path) === $body ? $this->good('Lectura') : $this->bad('La lectura no coincide con lo escrito');
        } catch (\Throwable $e) {
            $this->bad('Escritura/lectura: ' . $this->short($e) . ' (revisa la política IAM)');
        }

        $this->line("\nURLs firmadas");
        try {
            $get = (string) $client->createPresignedRequest($client->getCommand('GetObject', ['Bucket' => $bucket, 'Key' => $key]), '+2 minutes')->getUri();
            Http::connectTimeout(5)->timeout(25)->get($get)->body() === $body ? $this->good('GET firmado') : $this->bad('GET firmado no devolvió el contenido');

            $putKey = ViralStorage::fullKey('selfcheck/put-' . bin2hex(random_bytes(4)) . '.txt');
            $put = (string) $client->createPresignedRequest($client->getCommand('PutObject', ['Bucket' => $bucket, 'Key' => $putKey]), '+2 minutes')->getUri();
            $r = Http::connectTimeout(5)->timeout(25)->withBody('ok', 'text/plain')->put($put);
            $r->successful() ? $this->good('PUT firmado (subida directa del navegador)') : $this->bad('PUT firmado: HTTP ' . $r->status());
            $client->deleteObject(['Bucket' => $bucket, 'Key' => $putKey]);
        } catch (\Throwable $e) {
            $this->bad('URLs firmadas: ' . $this->short($e));
        }

        $this->line("\nSubida por partes (videos de hasta 100 MB)");
        try {
            $mp = $client->createMultipartUpload(['Bucket' => $bucket, 'Key' => ViralStorage::fullKey('selfcheck/mp-' . bin2hex(random_bytes(4)) . '.bin')]);
            $uploadId = $mp['UploadId'];
            $partUrl = (string) $client->createPresignedRequest($client->getCommand('UploadPart', [
                'Bucket' => $bucket, 'Key' => $mp['Key'] ?? ViralStorage::fullKey('selfcheck/mp.bin'), 'UploadId' => $uploadId, 'PartNumber' => 1,
            ]), '+2 minutes')->getUri();
            $this->good('Multipart iniciado y URL de parte firmada');
            $client->abortMultipartUpload(['Bucket' => $bucket, 'Key' => $mp['Key'] ?? ViralStorage::fullKey('selfcheck/mp.bin'), 'UploadId' => $uploadId]);
            $this->good('Multipart cancelado (sin residuos)');
        } catch (\Throwable $e) {
            $this->bad('Multipart: ' . $this->short($e) . ' (necesita s3:AbortMultipartUpload y s3:ListMultipartUploadParts)');
        }

        $this->line("\nCORS y seguridad");
        $origin = 'https://viral.market.com.bo';
        try {
            $rules = $client->getBucketCors(['Bucket' => $bucket])['CORSRules'] ?? [];
            $hit = collect($rules)->first(fn ($r) => in_array($origin, $r['AllowedOrigins'] ?? [], true) || in_array('*', $r['AllowedOrigins'] ?? [], true));
            if (!$hit) {
                $this->bad("CORS no permite {$origin}. Aplica plugins/aero/viral/docs/s3/cors.json");
            } else {
                $methods = $hit['AllowedMethods'] ?? [];
                $missing = array_diff(['PUT', 'GET'], $methods);
                $missing ? $this->bad('CORS sin métodos: ' . implode(', ', $missing)) : $this->good('CORS permite PUT y GET desde ' . $origin);
                in_array('ETag', $hit['ExposeHeaders'] ?? [], true) ? $this->good('CORS expone ETag (necesario para multipart)') : $this->bad('CORS debe exponer el header ETag');
            }
        } catch (\Throwable $e) {
            $this->note('No pude leer el CORS (' . $this->short($e) . '). Verifícalo a mano con docs/s3/cors.json');
        }
        try {
            $cfg = $client->getPublicAccessBlock(['Bucket' => $bucket])['PublicAccessBlockConfiguration'] ?? [];
            count(array_filter($cfg)) === 4 ? $this->good('Acceso público bloqueado') : $this->bad('Activa "Block all public access" en el bucket');
        } catch (\Throwable $e) {
            $this->note('No pude leer el bloqueo de acceso público (normal en R2/MinIO). Confirma que el bucket sea privado.');
        }

        try {
            ViralStorage::disk()->delete($path);
            $this->good('Limpieza de objetos de prueba');
        } catch (\Throwable $e) {
            $this->note('No pude borrar el objeto de prueba ' . $key);
        }

        return $this->finish();
    }

    protected function finish(): int
    {
        $this->newLine();
        if ($this->fails) {
            $this->error("✗ {$this->fails} problema(s). Corrígelos y vuelve a correr el comando.");

            return 1;
        }
        $this->info('✓ Todo listo' . ($this->warns ? " ({$this->warns} aviso(s) a revisar a mano)" : '') . '.');

        return 0;
    }

    protected function good(string $m): void { $this->line("  <info>✓</info> {$m}"); }
    protected function bad(string $m): void { $this->fails++; $this->line("  <error>✗ {$m}</error>"); }
    protected function note(string $m): void { $this->warns++; $this->line("  <comment>! {$m}</comment>"); }
    protected function short(\Throwable $e): string { return mb_substr(preg_replace('/\s+/', ' ', $e->getMessage()), 0, 220); }
}
