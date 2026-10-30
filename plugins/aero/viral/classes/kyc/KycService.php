<?php namespace Aero\Viral\Classes\Kyc;

use Aero\Viral\Models\Application;
use Aero\Viral\Models\KycCheck;
use Illuminate\Support\Facades\Cache;

/** Orquesta la verificación de identidad de una postulación con Didit. */
class KycService
{
    public function __construct(protected ?DiditClient $client = null)
    {
        $this->client ??= new DiditClient;
    }

    public static function isAvailable(): bool
    {
        return DiditClient::isConfigured();
    }

    /** Inicia (o continúa) la verificación y devuelve la URL de Didit. */
    public function start(Application $app, string $returnUrl): string
    {
        if (!self::isAvailable()) {
            throw new KycException('La verificación de identidad aún no está disponible.');
        }
        if ($app->kyc_status === 'verified') {
            throw new KycException('Tu identidad ya está verificada.');
        }

        $session = $this->client->createSession($app->uuid, $returnUrl, ['application' => $app->id]);

        $check = KycCheck::where('application_id', $app->id)->where('driver', 'didit')->latest('id')->first();
        if (!$check || in_array($check->status, ['rejected', 'expired'], true) || ($check->external_ref && $check->external_ref !== $session['session_id'])) {
            $check = new KycCheck(['application_id' => $app->id, 'user_id' => $app->user_id, 'driver' => 'didit']);
        }
        $check->status = 'pending';
        $check->external_ref = $session['session_id'];
        $check->save();

        $app->forceFill(['kyc_status' => 'pending'])->save();

        return $session['url'];
    }

    /**
     * Aplica un webhook ya verificado. Idempotente por event_id. Devuelve false si se ignoró.
     */
    public function handleWebhook(array $p): bool
    {
        $sessionId = (string) ($p['session_id'] ?? '');
        $status = (string) ($p['status'] ?? '');
        $eventId = (string) ($p['event_id'] ?? '');
        if ($sessionId === '' || $status === '' || ($p['webhook_type'] ?? 'status.updated') !== 'status.updated') {
            return false;
        }
        if ($eventId !== '' && !Cache::add('viral:didit:evt:' . $eventId, 1, now()->addDays(2))) {
            return false;   // reintento ya procesado
        }

        $check = KycCheck::where('driver', 'didit')->where('external_ref', $sessionId)->latest('id')->first();
        if (!$check) {
            return false;
        }

        $mapped = DiditWebhook::mapStatus($status);
        if ($check->status === 'verified' && $mapped !== 'verified') {
            return false;   // una verificación aprobada no se degrada por un evento tardío
        }

        if ($mapped === 'verified') {
            $age = $this->storeIdentity($check, $p);
            if ($age !== null && $age < 18) {
                $check->reject(null, 'Menor de 18 años: la plataforma es solo para mayores de edad.');
            } else {
                $check->verify();
            }
        } elseif ($mapped === 'rejected') {
            $check->reject(null, $this->reason($p) ?: 'Didit no pudo verificar la identidad.');
        } else {
            $check->status = $mapped;
            $check->save();
            $check->application?->forceFill(['kyc_status' => $mapped === 'expired' ? 'none' : 'pending'])->save();
        }

        return true;
    }

    /** Guarda lo mínimo del documento (cifrado) para el revisor. No guardamos fotos: quedan en Didit. */
    /** @return int|null edad según el documento, si Didit la entrega (no se guarda la fecha de nacimiento) */
    protected function storeIdentity(KycCheck $check, array $p): ?int
    {
        $decision = (array) ($p['decision'] ?? []);
        $doc = $decision['id_verifications'][0] ?? null;
        if (!$doc) {
            try {
                $decision = $this->client->decision($check->external_ref);
                $doc = $decision['id_verifications'][0] ?? null;
            } catch (\Throwable) {
            }
        }
        if (is_array($doc) && !empty($doc['document_number'])) {
            $check->doc_number = (string) $doc['document_number'];
        }

        return is_array($doc) && isset($doc['age']) && is_numeric($doc['age']) ? (int) $doc['age'] : null;
    }

    protected function reason(array $p): ?string
    {
        $d = (array) ($p['decision'] ?? []);
        foreach ((array) ($d['id_verifications'][0]['warnings'] ?? []) as $w) {
            if (!empty($w['short_description'])) {
                return mb_substr((string) $w['short_description'], 0, 480);
            }
        }

        return null;
    }
}
