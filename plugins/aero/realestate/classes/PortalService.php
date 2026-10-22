<?php namespace Aero\Realestate\Classes;

use Aero\Realestate\Models\Client;
use Aero\Realestate\Models\Interaction;
use Aero\Realestate\Models\Lead;
use Aero\Realestate\Models\Offer;
use Aero\Realestate\Models\Property;
use Aero\Realestate\Models\Visit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Lo que pasa cuando un visitante escribe desde la ficha: cliente, oportunidad, visita opcional y avisos. */
class PortalService
{
    public function __construct(protected VisitService $visits)
    {
    }

    /**
     * @param array $data name, email, phone, message, offer_id, visit_at
     * @return array{0: Client, 1: Lead, 2: ?Visit}
     */
    public function inquire(Property $property, array $data, $user = null): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $phone = preg_replace('/[^\d+]/', '', (string) ($data['phone'] ?? ''));
        $message = mb_substr(trim((string) ($data['message'] ?? '')), 0, 1000);

        if ($name === '' || mb_strlen($name) > 120) {
            throw new RealestateException('Escribe tu nombre.');
        }
        if ($email === '' && $phone === '') {
            throw new RealestateException('Déjanos un correo o un teléfono para responderte.');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RealestateException('El correo no es válido.');
        }

        $offer = !empty($data['offer_id']) ? Offer::where('property_id', $property->id)->where('status', 'available')->find((int) $data['offer_id']) : null;

        return DB::transaction(function () use ($property, $name, $email, $phone, $message, $offer, $data, $user) {
            $client = $this->client($property->tenant_id, $name, $email, $phone, $user);

            $lead = Lead::where('tenant_id', $property->tenant_id)->where('client_id', $client->id)->where('property_id', $property->id)->open()->first();
            if (!$lead) {
                $lead = Lead::create([
                    'tenant_id' => $property->tenant_id, 'client_id' => $client->id, 'property_id' => $property->id,
                    'offer_id' => $offer?->id, 'source' => 'portal',
                ]);
            }
            Interaction::log($client->id, $lead->id, $property->tenant_id, 'note', 'Consulta desde el portal' . ($message !== '' ? ': ' . $message : '.'));

            $visit = null;
            if (!empty($data['visit_at'])) {
                $visit = $this->visits->schedule([
                    'property_id' => $property->id, 'client_id' => $client->id, 'lead_id' => $lead->id,
                    'starts_at' => Carbon::parse($data['visit_at']), 'notes' => $message ?: null,
                ], 'portal');
            }

            \Event::fire('aero.realestate.inquiryReceived', [$lead->refresh(), $visit]);
            Notifier::fireInquiry($lead, $message, $visit);

            return [$client, $lead, $visit];
        });
    }

    /** Reutiliza al cliente por correo o teléfono dentro de la inmobiliaria; nunca cruza tenants. */
    public function client(int $tenantId, string $name, string $email, string $phone, $user = null): Client
    {
        $digits = preg_replace('/\D+/', '', $phone);
        $client = Client::where('tenant_id', $tenantId)->where(function ($w) use ($email, $phone, $digits, $user) {
            $email !== '' && $w->orWhere('email', $email);
            $digits !== '' && $w->orWhere('phone', $phone)->orWhere('phone', $digits);
            $user && $w->orWhere('user_id', $user->id);
        })->first();

        if (!$client) {
            $client = Client::create([
                'tenant_id' => $tenantId, 'name' => $name, 'email' => $email ?: null, 'phone' => $phone ?: null, 'source' => 'portal',
            ]);
        } else {
            // Completa lo que faltaba, sin pisar lo que el equipo ya tiene.
            $client->email = $client->email ?: ($email ?: null);
            $client->phone = $client->phone ?: ($phone ?: null);
            $client->isDirty() && $client->save();
        }

        if ($user && !$client->user_id && !Client::where('tenant_id', $tenantId)->where('user_id', $user->id)->exists()) {
            $client->user_id = $user->id;
            $client->save();
        }

        return $client;
    }
}
