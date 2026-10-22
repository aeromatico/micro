<?php namespace Aero\Realestate\Console;

use Aero\Realestate\Classes\AgreementService;
use Aero\Realestate\Classes\AnticreticoService;
use Aero\Realestate\Classes\DealService;
use Aero\Realestate\Classes\LeadService;
use Aero\Realestate\Classes\Notifier;
use Aero\Realestate\Models\Agent;
use Aero\Realestate\Models\Agreement;
use Aero\Realestate\Models\AgreementEvent;
use Aero\Realestate\Models\AgreementTemplate;
use Aero\Realestate\Models\Change;
use Aero\Realestate\Models\Client;
use Aero\Realestate\Models\Commission;
use Aero\Realestate\Models\CommissionRule;
use Aero\Realestate\Models\Contract;
use Aero\Realestate\Models\ContractDocument;
use Aero\Realestate\Models\ContractEvent;
use Aero\Realestate\Models\Document;
use Aero\Realestate\Models\Feature;
use Aero\Realestate\Models\Interaction;
use Aero\Realestate\Models\Lead;
use Aero\Realestate\Models\Location;
use Aero\Realestate\Models\Offer;
use Aero\Realestate\Models\Owner;
use Aero\Realestate\Models\Payment;
use Aero\Realestate\Models\Property;
use Aero\Realestate\Models\RealestateSettings;
use Aero\Realestate\Models\SavedSearch;
use Aero\Realestate\Models\Visit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * realestate:demo {tenant} [--fresh] [--public] — datos de demostración de una inmobiliaria en UN tenant, pensados
 * para probar cada pantalla y cada regla: propiedades de todo tipo y estado, anticréticos en todas sus situaciones,
 * contratos de alquiler/arras, documentos, oportunidades, visitas, cierres y comisiones repartidos en ~6 meses.
 *
 * SEGURO: sin teléfonos (NULL), correos @example.test, todos los avisos de Configuración APAGADOS y el portal
 * sin publicar (salvo --public). Todo lo creado se marca ([demo], código DEMO-…) para que --fresh borre SOLO eso.
 */
class DemoCommand extends Command
{
    protected $signature = 'realestate:demo {tenant : id del tenant} {--fresh : borra antes lo marcado como demo de ESE tenant} {--public : publica el portal /inmuebles}';

    protected $description = 'Carga una inmobiliaria de demostración con escenarios variados (propiedades, anticréticos, CRM, contratos, documentos).';

    protected int $tid;
    protected array $agents = [];
    protected array $owners = [];
    protected array $clients = [];
    protected array $props = [];
    protected array $offers = [];
    protected array $loc = [];
    protected int $seq = 0;

    public function handle(): int
    {
        $this->tid = (int) $this->argument('tenant');
        if (!class_exists(\Aero\Sites\Models\Tenant::class) || !\Aero\Sites\Models\Tenant::find($this->tid)) {
            $this->error('Tenant no encontrado.');

            return self::FAILURE;
        }

        $has = Property::withTrashed()->where('tenant_id', $this->tid)->where('code', 'like', 'DEMO-%')->exists();
        if ($has && !$this->option('fresh')) {
            $this->error('Ese tenant ya tiene datos demo. Usa --fresh para reemplazarlos (solo los marcados como demo).');

            return self::FAILURE;
        }

        // Nada de avisos reales: ni al sembrar ni por el cron (los interruptores quedan apagados abajo).
        Notifier::$muted = true;

        DB::transaction(function () {
            $this->option('fresh') && $this->wipe();
            $this->settings();
            $this->people();
            $this->locations();
            $this->properties();
            $this->anticretico();
            $this->crm();
            $this->agreements();
            $this->documents();
            $this->portalExtras();
        });

        $this->summary();

        return self::SUCCESS;
    }

    // ───────────────────────── utilidades ─────────────────────────

    protected function ago(int $days, int $hour = 10)
    {
        return now()->subDays($days)->setTime($hour, 0);
    }

    protected function backdate(string $table, int $id, array $cols): void
    {
        DB::table($table)->where('id', $id)->update($cols);
    }

    protected function demoImage(string $label, int $seed): string
    {
        $w = 800;
        $h = 600;
        $img = imagecreatetruecolor($w, $h);
        $palette = [[52, 152, 219], [46, 204, 113], [231, 76, 60], [155, 89, 182], [241, 196, 15], [26, 188, 156], [230, 126, 34]];
        [$r, $g, $b] = $palette[$seed % count($palette)];
        for ($y = 0; $y < $h; $y++) {
            $k = $y / $h;
            imagefilledrectangle($img, 0, $y, $w, $y, imagecolorallocate($img, (int) ($r * (1 - $k * .45)), (int) ($g * (1 - $k * .45)), (int) ($b * (1 - $k * .45))));
        }
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefilledrectangle($img, 250, 250, 550, 520, imagecolorallocatealpha($img, 255, 255, 255, 90)); // «casa» esquemática
        imagefilledpolygon($img, [220, 255, 400, 140, 580, 255], imagecolorallocatealpha($img, 255, 255, 255, 70));
        imagestring($img, 5, 24, 24, strtoupper(substr(iconv('UTF-8', 'ASCII//TRANSLIT', $label), 0, 60)), $white);
        imagestring($img, 3, 24, $h - 28, 'IMAGEN DE DEMOSTRACION', $white);
        ob_start();
        imagejpeg($img, null, 78);
        imagedestroy($img);

        return (string) ob_get_clean();
    }

    protected function privateFile(string $title): \System\Models\File
    {
        $html = '<html><body style="font-family:DejaVu Sans"><h2>' . e($title) . '</h2><p>Documento de demostración. No es un documento real.</p></body></html>';
        $f = new \System\Models\File();
        $f->is_public = false; // antes de fromData: queda en la carpeta protegida
        if (class_exists(\Dompdf\Dompdf::class)) {
            $pdf = new \Dompdf\Dompdf();
            $pdf->loadHtml($html, 'UTF-8');
            $pdf->render();
            $f->fromData($pdf->output(), 'demo-' . ++$this->seq . '.pdf');
        } else {
            $f->fromData('Documento de demostración: ' . $title, 'demo-' . ++$this->seq . '.txt');
        }

        return $f;
    }

    // ───────────────────────── borrado (solo lo marcado demo) ─────────────────────────

    protected function wipe(): void
    {
        $t = $this->tid;
        $props = Property::withTrashed()->where('tenant_id', $t)->where('code', 'like', 'DEMO-%')->pluck('id')->all();
        $owners = Owner::withTrashed()->where('tenant_id', $t)->where('notes', '[demo]')->pluck('id')->all();
        $clients = Client::withTrashed()->where('tenant_id', $t)->where('notes', '[demo]')->pluck('id')->all();
        $agents = Agent::withTrashed()->where('tenant_id', $t)->where('notes', '[demo]')->pluck('id')->all();

        $contracts = Contract::withTrashed()->whereIn('property_id', $props)->pluck('id')->all();
        DB::table('aero_realestate_payments')->whereIn('contract_id', $contracts)->delete();
        DB::table('aero_realestate_contract_documents')->whereIn('contract_id', $contracts)->delete();
        DB::table('aero_realestate_contract_events')->whereIn('contract_id', $contracts)->delete();
        Contract::withTrashed()->whereIn('id', $contracts)->forceDelete();

        $agreements = Agreement::withTrashed()->where('tenant_id', $t)->where(fn ($q) => $q->whereIn('property_id', $props)->orWhere('title', 'like', 'DEMO%'))->pluck('id')->all();
        Document::withTrashed()->where('tenant_id', $t)->where(fn ($q) => $q->where('notes', '[demo]')->orWhereIn('agreement_id', $agreements))->get()->each->forceDelete();
        DB::table('aero_realestate_agreement_events')->whereIn('agreement_id', $agreements)->delete();
        Agreement::withTrashed()->whereIn('id', $agreements)->get()->each->forceDelete();
        AgreementTemplate::withTrashed()->where('tenant_id', $t)->where('name', 'like', '%(demo)')->forceDelete();

        $leads = Lead::withTrashed()->where('tenant_id', $t)->where(fn ($q) => $q->whereIn('client_id', $clients)->orWhereIn('property_id', $props))->pluck('id')->all();
        DB::table('aero_realestate_commissions')->where('tenant_id', $t)->where(fn ($q) => $q->whereIn('lead_id', $leads)->orWhereIn('property_id', $props))->delete();
        DB::table('aero_realestate_interactions')->where('tenant_id', $t)->where(fn ($q) => $q->whereIn('lead_id', $leads)->orWhereIn('client_id', $clients))->delete();
        Visit::withTrashed()->where('tenant_id', $t)->where(fn ($q) => $q->whereIn('property_id', $props)->orWhereIn('client_id', $clients))->forceDelete();
        Lead::withTrashed()->whereIn('id', $leads)->forceDelete();

        DB::table('aero_realestate_favorites')->whereIn('property_id', $props)->delete();
        DB::table('aero_realestate_changes')->whereIn('property_id', $props)->delete();
        SavedSearch::where('tenant_id', $t)->where('name', 'like', 'Demo ·%')->delete();
        Feature::where('tenant_id', $t)->where('name', 'like', 'Demo ·%')->delete();

        DB::table('aero_realestate_feature_property')->whereIn('property_id', $props)->delete();
        Offer::withTrashed()->whereIn('property_id', $props)->forceDelete();
        Property::withTrashed()->whereIn('id', $props)->get()->each(function ($p) {
            $p->photos->each->delete();
            $p->plans->each->delete();
            $p->forceDelete();
        });
        Client::withTrashed()->whereIn('id', $clients)->forceDelete();
        Owner::withTrashed()->whereIn('id', $owners)->forceDelete();
        Agent::withTrashed()->whereIn('id', $agents)->forceDelete();
        \Cache::forget('re-facets:' . $t);
        $this->line('Demo anterior eliminado.');
    }

    // ───────────────────────── configuración, personas, ubicaciones ─────────────────────────

    protected function settings(): void
    {
        $s = RealestateSettings::firstOrNew(['tenant_id' => $this->tid]);
        $s->forceFill([
            'business_name' => 'Inmobiliaria Demo', 'portal_title' => 'Inmuebles Demo', 'portal_description' => 'Casas, departamentos, terrenos y oficinas en venta, alquiler y anticrético. Datos de demostración.',
            'visit_minutes' => 45, 'reminder_hours' => 24, 'followup_days' => 3, 'visit_start_time' => '09:00', 'visit_end_time' => '18:00',
            'visit_days' => [1, 2, 3, 4, 5, 6], 'visit_notice_hours' => 4, 'visit_advance_days' => 30,
            // sin avisos reales a nadie
            'notify_clients' => false, 'notify_agents' => false, 'notify_admins' => false,
            'public_enabled' => (bool) $this->option('public'),
        ])->save();

        foreach ([['sale', 'percent', 3, null, 50], ['rent', 'monthly_rents', 1, null, 50], ['anticretico', 'percent', 2, null, 40]] as [$m, $b, $v, $a, $share]) {
            CommissionRule::firstOrCreate(['tenant_id' => $this->tid, 'modality' => $m, 'agent_id' => $a], ['basis' => $b, 'value' => $v, 'agent_share_percent' => $share, 'is_active' => true]);
        }
    }

    protected function people(): void
    {
        foreach ([['Carlos Vaca', 'LIC-0101', true], ['María Quiroga', 'LIC-0202', true], ['Jorge Mendoza', 'LIC-0303', false]] as [$n, $lic, $active]) {
            $this->agents[] = Agent::create(['tenant_id' => $this->tid, 'name' => $n, 'license' => $lic, 'is_active' => $active, 'notes' => '[demo]', 'bio' => 'Agente de demostración.']);
        }
        // María Quiroga cobra más en ventas (la regla del agente gana a la general)
        CommissionRule::firstOrCreate(['tenant_id' => $this->tid, 'modality' => 'sale', 'agent_id' => $this->agents[1]->id], ['basis' => 'percent', 'value' => 4, 'agent_share_percent' => 60, 'is_active' => true]);

        foreach ([['Ana Rojas Mamani', '4567890 LP', 'casada', 'Av. Arce 2345, La Paz'], ['Roberto Suárez Ribera', '3344556 SC', 'soltero', 'Calle Libertad 120, Santa Cruz'],
                  ['Gladys Camacho Ortiz', '6677889 CB', 'viuda', 'Av. Ballivián 88, Cochabamba'], ['Inversiones Andes S.R.L.', 'NIT 1020304050', null, 'Av. San Martín 400, Santa Cruz'],
                  ['Héctor Flores Aliaga', '2233445 SC', 'divorciado', 'Barrio Equipetrol, Santa Cruz']] as [$n, $doc, $civil, $addr]) {
            $this->owners[] = Owner::create(['tenant_id' => $this->tid, 'name' => $n, 'document' => $doc, 'civil_status' => $civil, 'nationality' => 'boliviana', 'address' => $addr, 'email' => null, 'notes' => '[demo]']);
        }

        $clients = [
            ['Luis Pérez Calle', ['sale'], 80000, 200000, 'USD', 0, 'portal'], ['Valeria Montaño', ['rent'], 2000, 5000, 'BOB', 1, 'whatsapp'], ['Diego Salvatierra', ['anticretico'], 20000, 40000, 'USD', 0, 'referido'],
            ['Paola Terrazas', ['sale', 'anticretico'], 150000, 350000, 'USD', 1, 'redes'], ['Marcelo Choque', ['rent'], 300, 800, 'USD', 0, 'portal'], ['Fernanda Lijerón', ['anticretico'], 100000, 200000, 'BOB', 1, 'portal'],
            ['Óscar Villarroel', ['sale'], 30000, 120000, 'USD', 0, 'referido'], ['Camila Antelo', ['rent', 'anticretico'], 2500, 4500, 'BOB', 1, 'whatsapp'], ['Rodrigo Paz', ['sale'], 200000, 400000, 'USD', 0, 'redes'],
            ['Silvia Guzmán', ['anticretico'], 15000, 30000, 'USD', 1, 'portal'], ['Mauricio Arce', ['rent'], 1000, 2000, 'USD', 0, 'otro'], ['Daniela Rivero', ['sale', 'rent'], 50000, 150000, 'USD', 1, 'portal'],
        ];
        foreach ($clients as $i => [$n, $int, $min, $max, $cur, $ag, $src]) {
            $this->clients[] = Client::create([
                'tenant_id' => $this->tid, 'name' => $n, 'email' => 'demo.cliente' . ($i + 1) . '@example.test', 'document' => (2000000 + $i * 111111) . ' SC', 'civil_status' => $i % 3 ? 'soltero/a' : 'casado/a',
                'nationality' => 'boliviana', 'address' => 'Dirección demo ' . ($i + 1), 'interests' => $int, 'budget_min' => $min, 'budget_max' => $max, 'currency' => $cur,
                'agent_id' => $this->agents[$ag]->id, 'source' => $src, 'notes' => '[demo]',
            ]);
        }
    }

    protected function locations(): void
    {
        $zones = [
            'Santa Cruz de la Sierra' => ['Equipetrol' => [-17.7620, -63.1950], 'Urbarí' => [-17.7700, -63.1990], 'Sirari' => [-17.7570, -63.1890], 'Las Palmas' => [-17.7880, -63.1850], 'Plan 3000' => [-17.8190, -63.1330]],
            'La Paz' => ['Sopocachi' => [-16.5120, -68.1280], 'Calacoto' => [-16.5350, -68.0830], 'San Miguel' => [-16.5330, -68.0820], 'Miraflores' => [-16.5040, -68.1170]],
            'Cochabamba' => ['Cala Cala' => [-17.3680, -66.1620], 'Queru Queru' => [-17.3790, -66.1560], 'Sarco' => [-17.3880, -66.1470]],
        ];
        foreach ($zones as $city => $list) {
            $c = Location::whereNull('tenant_id')->where('type', 'city')->where('name', $city)->first();
            if (!$c) {
                continue;
            }
            foreach ($list as $name => [$lat, $lng]) {
                $this->loc[$name] = Location::firstOrCreate(['tenant_id' => $this->tid, 'parent_id' => $c->id, 'type' => 'zone', 'name' => $name], ['lat' => $lat, 'lng' => $lng]);
            }
        }
        foreach (['Demo · Pozo de agua', 'Demo · Vista a la montaña'] as $f) {
            Feature::firstOrCreate(['tenant_id' => $this->tid, 'name' => $f], ['category' => 'exterior']);
        }
    }

    // ───────────────────────── propiedades ─────────────────────────

    protected function featureIds(array $names): array
    {
        return Feature::where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $this->tid))->whereIn('name', $names)->pluck('id')->all();
    }

    protected function prop(string $key, string $title, string $type, string $zone, string $addr, array $d, array $offers, array $o = []): Property
    {
        $n = count($this->props) + 1;
        $loc = $this->loc[$zone] ?? null;
        $p = Property::create([
            'tenant_id' => $this->tid, 'code' => 'DEMO-' . str_pad((string) $n, 3, '0', STR_PAD_LEFT), 'title' => $title, 'type' => $type, 'location_id' => $loc?->id,
            'address' => $addr, 'lat' => $loc ? $loc->lat + (($n % 7) - 3) * 0.0011 : null, 'lng' => $loc ? $loc->lng + (($n % 5) - 2) * 0.0013 : null,
            'land_area' => $d['land'] ?? null, 'built_area' => $d['built'] ?? null, 'bedrooms' => $d['bed'] ?? null, 'bathrooms' => $d['bath'] ?? null, 'garages' => $d['gar'] ?? null,
            'floors' => $d['floors'] ?? null, 'age_years' => $d['age'] ?? null, 'description' => ($d['desc'] ?? 'Propiedad de demostración con buena ubicación y todos los servicios.') . "\n\nDatos de demostración: no es una propiedad real.",
            'video_url' => $d['video'] ?? null, 'is_featured' => $o['featured'] ?? false, 'is_published' => $o['published'] ?? true, 'status' => $o['status'] ?? 'available',
            'owner_id' => $this->owners[$n % count($this->owners)]->id, 'agent_id' => $this->agents[$o['agent'] ?? ($n % 2)]->id,
        ]);
        $pub = $this->ago($o['days'] ?? (5 + $n * 6));
        $this->backdate('aero_realestate_properties', $p->id, [
            'published_at' => ($o['published'] ?? true) ? $pub : null, 'created_at' => $pub->copy()->subDays(2), 'updated_at' => $pub, 'views_count' => $o['views'] ?? (30 + (($n * 53) % 540)),
        ]);
        if (!empty($d['features'])) {
            $p->features()->sync($this->featureIds($d['features']));
        }
        foreach ($offers as [$modality, $amount, $cur, $term, $deposit]) {
            $this->offers[$key][$modality] = Offer::create(['property_id' => $p->id, 'modality' => $modality, 'amount' => $amount, 'currency' => $cur, 'term_months' => $term, 'deposit' => $deposit,
                'conditions' => $modality === 'anticretico' ? 'Capital restituible íntegro al vencer el plazo. Renovable de común acuerdo.' : null]);
        }
        if (($o['photos'] ?? 3) > 0) {
            for ($i = 0; $i < ($o['photos'] ?? 3); $i++) {
                $f = new \System\Models\File();
                $f->fromData($this->demoImage($title . ' - foto ' . ($i + 1), $n + $i), 'demo-' . $n . '-' . ($i + 1) . '.jpg');
                $p->photos()->add($f);
            }
        }

        return $this->props[$key] = $p->fresh();
    }

    protected function properties(): void
    {
        $F = ['Piscina', 'Jardín', 'Terraza', 'Ascensor', 'Seguridad 24 horas', 'Estacionamiento de visitas', 'Aire acondicionado', 'Cocina equipada', 'Gimnasio', 'Condominio cerrado'];
        // — catálogo variado (disponibles, con una o varias modalidades) —
        $this->prop('casa_equi', 'Casa moderna en Equipetrol', 'house', 'Equipetrol', 'Calle 7 Este 220', ['land' => 450, 'built' => 320, 'bed' => 4, 'bath' => 4, 'gar' => 2, 'age' => 3, 'floors' => 2, 'features' => ['Piscina', 'Jardín', 'Seguridad 24 horas', 'Aire acondicionado'], 'video' => 'https://youtu.be/dQw4w9WgXcQ'], [['sale', 380000, 'USD', null, null]], ['featured' => true, 'views' => 540]);
        $this->prop('depto_sky', 'Departamento Torre Sky, Equipetrol', 'apartment', 'Equipetrol', 'Av. San Martín 1500', ['built' => 120, 'bed' => 3, 'bath' => 2, 'gar' => 1, 'age' => 2, 'floors' => 14, 'features' => ['Ascensor', 'Gimnasio', 'Piscina', 'Seguridad 24 horas']], [['sale', 165000, 'USD', null, null], ['anticretico', 35000, 'USD', 24, null]], ['featured' => true, 'views' => 410]);
        $this->prop('depto_sirari', 'Departamento 2 dormitorios en Sirari', 'apartment', 'Sirari', 'Calle Los Pinos 45', ['built' => 85, 'bed' => 2, 'bath' => 2, 'gar' => 1, 'age' => 6, 'features' => ['Ascensor', 'Terraza']], [['rent', 4200, 'BOB', 12, 4200], ['anticretico', 180000, 'BOB', 24, null]]);
        $this->prop('casa_urbari', 'Casa familiar en Urbarí', 'house', 'Urbarí', 'Calle 4 Oeste 810', ['land' => 300, 'built' => 210, 'bed' => 3, 'bath' => 3, 'gar' => 2, 'age' => 8, 'features' => ['Jardín', 'Cocina equipada']], [['sale', 210000, 'USD', null, null]], ['days' => 140]);
        $this->prop('terreno_p3', 'Terreno en Plan 3000', 'land', 'Plan 3000', 'Zona Mercado 6', ['land' => 400, 'desc' => 'Terreno plano, con agua y luz en la puerta.'], [['sale', 420000, 'BOB', null, null]], ['photos' => 2]);
        $this->prop('local_canoto', 'Local comercial sobre Av. Cañoto', 'shop', 'Equipetrol', 'Av. Cañoto 300', ['built' => 60, 'floors' => 1, 'age' => 10], [['rent', 900, 'USD', 12, 1800], ['sale', 95000, 'USD', null, null]], ['views' => 95]);
        $this->prop('oficina_equi', 'Oficina ejecutiva en Equipetrol', 'office', 'Equipetrol', 'Av. Equipetrol 120', ['built' => 95, 'gar' => 2, 'age' => 4, 'features' => ['Ascensor', 'Aire acondicionado', 'Seguridad 24 horas']], [['rent', 1100, 'USD', 12, 2200]], ['days' => 70]);
        $this->prop('casa_palmas', 'Casa con piscina en Las Palmas', 'house', 'Las Palmas', 'Calle Las Palmas 99', ['land' => 380, 'built' => 260, 'bed' => 4, 'bath' => 3, 'gar' => 2, 'age' => 12, 'features' => ['Piscina', 'Jardín', 'Condominio cerrado']], [['rent', 7500, 'BOB', 12, 7500], ['anticretico', 40000, 'USD', 36, null]]);
        $this->prop('depto_sopo', 'Departamento en Sopocachi', 'apartment', 'Sopocachi', 'Calle 20 de Octubre 100', ['built' => 90, 'bed' => 2, 'bath' => 1, 'age' => 15, 'features' => ['Ascensor']], [['rent', 450, 'USD', 12, 450], ['anticretico', 22000, 'USD', 24, null]]);
        $this->prop('depto_calacoto', 'Departamento de lujo en Calacoto', 'apartment', 'Calacoto', 'Calle 14 de Calacoto 8', ['built' => 180, 'bed' => 3, 'bath' => 3, 'gar' => 2, 'age' => 1, 'floors' => 9, 'features' => ['Ascensor', 'Gimnasio', 'Seguridad 24 horas', 'Terraza']], [['sale', 290000, 'USD', null, null], ['rent', 1800, 'USD', 12, 3600]], ['featured' => true, 'views' => 380]);
        $this->prop('casa_smiguel', 'Casa en San Miguel', 'house', 'San Miguel', 'Calle Montenegro 45', ['land' => 320, 'built' => 240, 'bed' => 4, 'bath' => 3, 'gar' => 2, 'age' => 9, 'features' => ['Jardín', 'Terraza']], [['sale', 330000, 'USD', null, null]], ['days' => 110]);
        $this->prop('depto_miraflores', 'Departamento en Miraflores', 'apartment', 'Miraflores', 'Av. Busch 500', ['built' => 70, 'bed' => 2, 'bath' => 1, 'age' => 20], [['anticretico', 150000, 'BOB', 24, null], ['rent', 3000, 'BOB', 12, 3000]]);
        $this->prop('terreno_cala', 'Terreno residencial en Cala Cala', 'land', 'Cala Cala', 'Calle Los Cedros s/n', ['land' => 600], [['sale', 180000, 'USD', null, null]], ['days' => 150]);
        $this->prop('casa_queru', 'Casa en Queru Queru', 'house', 'Queru Queru', 'Calle Pantaleón Dalence 77', ['land' => 400, 'built' => 260, 'bed' => 4, 'bath' => 4, 'gar' => 3, 'age' => 5, 'features' => ['Piscina', 'Jardín', 'Seguridad 24 horas', 'Demo · Vista a la montaña']], [['sale', 245000, 'USD', null, null], ['anticretico', 50000, 'USD', 36, null]], ['featured' => true]);
        $this->prop('depto_sarco', 'Departamento en Sarco', 'apartment', 'Sarco', 'Calle Ayacucho 12', ['built' => 95, 'bed' => 3, 'bath' => 2, 'gar' => 1, 'age' => 7], [['rent', 3800, 'BOB', 12, 3800]], ['days' => 45]);
        $this->prop('oficina_cbba', 'Oficina en el centro de Cochabamba', 'office', 'Cala Cala', 'Av. Heroínas 600', ['built' => 140, 'floors' => 5, 'age' => 11, 'features' => ['Ascensor']], [['rent', 1300, 'USD', 12, 2600], ['anticretico', 60000, 'USD', 24, null]], ['photos' => 1]);
        // — casos especiales —
        $this->prop('sin_publicar', 'Casa en preparación (sin publicar)', 'house', 'Urbarí', 'Calle sin nombre 1', ['land' => 200, 'built' => 150, 'bed' => 3, 'bath' => 2], [['sale', 140000, 'USD', null, null]], ['published' => false, 'photos' => 0, 'views' => 0]);
        $this->prop('inactiva', 'Departamento retirado del mercado', 'apartment', 'Sopocachi', 'Calle Belisario Salinas 3', ['built' => 60, 'bed' => 1, 'bath' => 1], [['rent', 350, 'USD', 12, 350]], ['status' => 'inactive', 'days' => 160]);
        $this->prop('sin_fotos', 'Departamento sin fotografías', 'apartment', 'Sirari', 'Calle Beni 9', ['built' => 55, 'bed' => 1, 'bath' => 1], [['rent', 2800, 'BOB', 6, 2800]], ['photos' => 0, 'days' => 8]);
        // — propiedades que se usan en los anticréticos —
        foreach ([['ac_far', 'Departamento Torre Norte (anticrético vigente)', 'Equipetrol', 30000, 'USD'], ['ac_exp', 'Casa en Sirari (anticrético por vencer)', 'Sirari', 25000, 'USD'], ['ac_over', 'Departamento en Urbarí (anticrético vencido)', 'Urbarí', 150000, 'BOB'],
                  ['ac_part', 'Departamento en Cala Cala (devolución parcial)', 'Cala Cala', 20000, 'USD'], ['ac_disp', 'Casa en Sopocachi (en disputa)', 'Sopocachi', 35000, 'USD'], ['ac_fin', 'Departamento en Calacoto II (finalizado)', 'Calacoto', 18000, 'USD'],
                  ['ac_res', 'Departamento en Las Palmas (reservado)', 'Las Palmas', 28000, 'USD'], ['ac_draft', 'Casa en San Miguel II (borrador)', 'San Miguel', 22000, 'USD'], ['ac_canc', 'Departamento en Miraflores II (cancelado)', 'Miraflores', 120000, 'BOB']] as [$k, $t, $z, $cap, $cur]) {
            $this->prop($k, $t, 'apartment', $z, 'Dirección de demostración', ['built' => 80 + strlen($k), 'bed' => 2, 'bath' => 2, 'age' => 4], [['anticretico', $cap, $cur, 24, null]], ['photos' => 1, 'agent' => strlen($k) % 2, 'days' => 100 + strlen($k) * 3]);
        }
        $this->line('Propiedades: ' . count($this->props));
    }

    // ───────────────────────── anticréticos en todas sus situaciones ─────────────────────────

    protected function contract(string $key, int $clientIdx, int $term = 24): Contract
    {
        $p = $this->props[$key];
        $offer = $this->offers[$key]['anticretico'];

        return Contract::create([
            'property_id' => $p->id, 'offer_id' => $offer->id, 'client_id' => $this->clients[$clientIdx]->id, 'capital' => $offer->amount, 'currency' => $offer->currency,
            'term_months' => $term, 'conditions' => 'Capital restituible al vencer. Uso exclusivo como vivienda.', 'return_conditions' => 'Devolución en una sola cuota contra la entrega del inmueble.', 'notes' => '[demo]',
        ]);
    }

    protected function pay(Contract $c, string $type, float $amount, $date, string $method = 'transfer'): void
    {
        $p = Payment::create(['contract_id' => $c->id, 'type' => $type, 'amount' => $amount, 'paid_on' => $date->toDateString(), 'method' => $method, 'reference' => strtoupper(substr($type, 0, 3)) . '-' . random_int(1000, 9999), 'notes' => '[demo]']);
        $this->backdate('aero_realestate_payments', $p->id, ['created_at' => $date, 'updated_at' => $date]);
    }

    /** Pone vigente un contrato con fecha de INICIO elegida (el servicio calcula el fin). */
    protected function activate(Contract $c, $start): Contract
    {
        $svc = app(AnticreticoService::class);
        $svc->activate($c->fresh(), $start->toDateString());
        $this->backdate('aero_realestate_contracts', $c->id, ['activated_at' => $start, 'created_at' => $start->copy()->subDays(5)]);

        return $c->fresh();
    }

    protected function docs(Contract $c, array $verified, array $pending = []): void
    {
        foreach ($verified as $type) {
            ContractDocument::create(['contract_id' => $c->id, 'type' => $type, 'number' => 'DEMO-' . random_int(100, 999), 'issued_on' => now()->subMonths(2)->toDateString(), 'status' => 'verified', 'notes' => '[demo]']);
        }
        foreach ($pending as $type) {
            ContractDocument::create(['contract_id' => $c->id, 'type' => $type, 'status' => 'pending', 'notes' => '[demo]']);
        }
    }

    protected function anticretico(): void
    {
        $svc = app(AnticreticoService::class);

        // 1. Vigente, lejos del vencimiento, documentos al día
        $c = $this->contract('ac_far', 2);
        $this->pay($c, 'delivery', 30000, $this->ago(180));
        $c = $this->activate($c, $this->ago(180));
        $this->docs($c, ['public_deed', 'property_registry']);
        $this->contracts['far'] = $c;

        // 2. Por vencer en 25 días, inscripción en Derechos Reales pendiente
        $c = $this->contract('ac_exp', 9, 12);
        $end = now()->addDays(25);
        $start = $end->copy()->subMonths(12)->startOfDay();
        $this->pay($c, 'delivery', 15000, $start, 'cash');
        $this->pay($c, 'delivery', 10000, $start->copy()->addDays(3));
        $c = $this->activate($c, $start);
        $this->docs($c, ['public_deed'], ['property_registry']);
        $this->contracts['exp'] = $c;

        // 3. Vencido hace 10 días (BOB)
        $c = $this->contract('ac_over', 5);
        $start = now()->subDays(10)->subMonths(24)->startOfDay();
        $this->pay($c, 'delivery', 150000, $start);
        $c = $this->activate($c, $start);
        $this->docs($c, ['public_deed', 'property_registry']);
        $this->contracts['over'] = $c;

        // 4. Renovado una vez + devolución parcial
        $c = $this->contract('ac_part', 3, 12);
        $start = now()->subMonths(10)->startOfDay();
        $this->pay($c, 'delivery', 20000, $start);
        $c = $this->activate($c, $start);
        $svc->renew($c->fresh(), 12, null, 'Renovación por acuerdo de las partes.');
        $this->backdate('aero_realestate_contract_events', ContractEvent::where('contract_id', $c->id)->where('type', 'renewed')->value('id'), ['created_at' => $this->ago(20)]);
        $this->pay($c, 'return', 8000, $this->ago(15), 'check');
        $this->docs($c, ['public_deed', 'property_registry']);
        $this->contracts['part'] = $c->fresh();

        // 5. En disputa
        $c = $this->contract('ac_disp', 7);
        $start = now()->subMonths(22)->startOfDay();
        $this->pay($c, 'delivery', 35000, $start);
        $c = $this->activate($c, $start);
        $this->pay($c, 'return', 10000, $this->ago(30));
        $svc->markDispute($c->fresh(), 'El propietario descuenta reparaciones que el anticresista no reconoce.');
        $this->docs($c, ['public_deed'], ['property_registry']);
        $this->contracts['disp'] = $c->fresh();

        // 6. Finalizado con devolución completa
        $c = $this->contract('ac_fin', 4, 12);
        $start = now()->subMonths(14)->startOfDay();
        $this->pay($c, 'delivery', 18000, $start);
        $c = $this->activate($c, $start);
        $this->pay($c, 'return', 18000, $this->ago(55));
        $svc->finish($c->fresh());
        $this->backdate('aero_realestate_contracts', $c->id, ['finished_at' => $this->ago(55)]);
        $this->docs($c, ['public_deed', 'property_registry']);
        $this->contracts['fin'] = $c->fresh();

        // 7. Reservado (entrega parcial: aún no puede activarse)
        $c = $this->contract('ac_res', 11);
        $this->pay($c, 'delivery', 10000, $this->ago(6));
        $svc->reserve($c->fresh());
        $this->contracts['res'] = $c->fresh();

        // 8. Borrador
        $this->contracts['draft'] = $this->contract('ac_draft', 1);

        // 9. Cancelado
        $c = $this->contract('ac_canc', 6);
        $svc->reserve($c->fresh());
        $svc->cancel($c->fresh(), 'El cliente consiguió otro inmueble.');
        $this->contracts['canc'] = $c->fresh();

        $this->line('Anticréticos: ' . count($this->contracts));
    }

    protected array $contracts = [];

    // ───────────────────────── CRM: oportunidades, visitas, cierres y comisiones ─────────────────────────

    protected function lead(string $propKey, int $clientIdx, string $modality, string $stage, int $daysAgo, ?int $agentIdx = null, array $o = []): Lead
    {
        $offer = $this->offers[$propKey][$modality] ?? null;
        $lead = Lead::create(['tenant_id' => $this->tid, 'client_id' => $this->clients[$clientIdx]->id, 'property_id' => $this->props[$propKey]->id, 'offer_id' => $offer?->id,
            'agent_id' => $agentIdx !== null ? $this->agents[$agentIdx]->id : null, 'source' => $this->clients[$clientIdx]->source, 'notes' => '[demo]']);
        $when = $this->ago($daysAgo);
        $this->backdate('aero_realestate_leads', $lead->id, ['created_at' => $when, 'updated_at' => $when]);
        DB::table('aero_realestate_interactions')->where('lead_id', $lead->id)->update(['created_at' => $when]);

        $ls = app(LeadService::class);
        $notes = ['Llamó interesado, pidió más fotos.', 'Se enviaron fotos y ubicación por WhatsApp.', 'Preguntó por forma de pago y plazo.', 'Quiere ver la propiedad con su familia.'];
        if ($stage !== 'new') {
            $ls->logContact($lead->fresh(), ['call', 'whatsapp', 'email'][$lead->id % 3], $notes[$lead->id % 4]);
            $this->backdate('aero_realestate_leads', $lead->id, ['last_contact_at' => $when->copy()->addDays(1)]);
            DB::table('aero_realestate_interactions')->where('lead_id', $lead->id)->where('type', '!=', 'note')->orWhere(fn ($q) => $q->where('lead_id', $lead->id)->where('body', 'like', '%Llamó%'))->update(['created_at' => $when->copy()->addDays(1)]);
        }
        if (in_array($stage, ['visit_scheduled', 'negotiation'], true)) {
            $ls->moveTo($lead->fresh(), $stage, $stage === 'negotiation' ? 'Ofertó un 8% menos; el propietario evalúa.' : null);
        }
        if ($stage === 'closed_lost') {
            $ls->moveTo($lead->fresh(), 'closed_lost', $o['lost'] ?? 'Compró otra propiedad.');
            $this->backdate('aero_realestate_leads', $lead->id, ['closed_at' => $when->copy()->addDays(6)]);
        }
        // seguimiento: vencido (días negativos) o próximo
        if (array_key_exists('follow', $o) && in_array($stage, Lead::OPEN, true)) {
            $this->backdate('aero_realestate_leads', $lead->id, ['next_follow_up_at' => now()->addDays($o['follow'])->setTime(9, 0), 'follow_up_alerted_at' => null]);
        }

        return $lead->fresh();
    }

    protected function visit(string $propKey, int $clientIdx, ?Lead $lead, $start, string $status, ?int $agentIdx = null, string $source = 'panel', ?string $reason = null): Visit
    {
        $p = $this->props[$propKey];
        $v = new Visit(['property_id' => $p->id, 'client_id' => $this->clients[$clientIdx]->id, 'lead_id' => $lead?->id, 'starts_at' => $start, 'notes' => '[demo]', 'source' => $source]);
        $v->tenant_id = $this->tid;
        $v->agent_id = $agentIdx !== null ? $this->agents[$agentIdx]->id : $p->agent_id;
        $v->status = $status;
        $v->cancel_reason = $reason;
        $v->save();
        $this->backdate('aero_realestate_visits', $v->id, ['created_at' => $start->copy()->subDays(3), 'updated_at' => $start->copy()->subDays(1), 'reminder_sent_at' => $status === 'done' ? $start->copy()->subDay() : null]);

        return $v;
    }

    protected function close(Lead $lead, string $propKey, string $modality, int $daysAgo, ?float $amount = null, string $commStatus = 'pending'): void
    {
        $deal = app(DealService::class);
        $deal->close($lead->fresh(), $this->offers[$propKey][$modality], $amount);
        $when = $this->ago($daysAgo);
        $this->backdate('aero_realestate_leads', $lead->id, ['closed_at' => $when, 'updated_at' => $when]);
        DB::table('aero_realestate_interactions')->where('lead_id', $lead->id)->where('type', 'deal')->update(['created_at' => $when]);
        $c = Commission::where('lead_id', $lead->id)->first();
        if ($c) {
            DB::table('aero_realestate_commissions')->where('id', $c->id)->update(['created_at' => $when, 'updated_at' => $when, 'status' => $commStatus, 'paid_on' => $commStatus === 'paid' ? $when->copy()->addDays(10)->toDateString() : null]);
        }
        if ($modality !== 'anticretico') {
            DB::table('aero_realestate_properties')->where('id', $this->props[$propKey]->id)->update(['updated_at' => $when]);
        }
    }

    protected function crm(): void
    {
        // — cierres repartidos en los últimos meses (alimentan comisiones, conversión y productividad) —
        $this->close($this->lead('terreno_cala', 8, 'sale', 'negotiation', 150, 0), 'terreno_cala', 'sale', 128, 175000, 'paid');
        $this->close($this->lead('casa_smiguel', 0, 'sale', 'negotiation', 120, 1), 'casa_smiguel', 'sale', 95, 318000, 'paid');
        $this->close($this->lead('casa_urbari', 6, 'sale', 'negotiation', 80, 1), 'casa_urbari', 'sale', 58, 205000, 'approved');
        $this->close($this->lead('depto_sarco', 10, 'rent', 'contacted', 55, 0), 'depto_sarco', 'rent', 35, null, 'paid');
        $this->close($this->lead('oficina_equi', 7, 'rent', 'negotiation', 40, 1), 'oficina_equi', 'rent', 12, null, 'pending');
        // la oferta de ac_far ya la cerró su contrato vigente: se reabre un instante para registrar el cierre comercial
        $acOffer = $this->offers['ac_far']['anticretico'];
        $acOffer->forceFill(['status' => 'available'])->saveQuietly();
        $this->close($this->lead('ac_far', 2, 'anticretico', 'negotiation', 200, 0), 'ac_far', 'anticretico', 180, null, 'paid');
        $acOffer->forceFill(['status' => 'closed'])->saveQuietly();

        // — oportunidades abiertas en todas las etapas —
        $open = [
            ['casa_equi', 3, 'sale', 'new', 2, 0, ['follow' => 1]], ['casa_equi', 8, 'sale', 'contacted', 9, 0, ['follow' => -2]], ['depto_sky', 0, 'sale', 'visit_scheduled', 12, 1, ['follow' => 3]],
            ['depto_sky', 2, 'anticretico', 'negotiation', 20, 0, ['follow' => -5]], ['depto_sirari', 1, 'rent', 'contacted', 6, 1, ['follow' => 0]], ['depto_sirari', 5, 'anticretico', 'new', 1, null, []],
            ['casa_palmas', 11, 'rent', 'visit_scheduled', 14, 1, ['follow' => 2]], ['depto_sopo', 9, 'anticretico', 'negotiation', 25, 0, ['follow' => -1]], ['depto_calacoto', 3, 'sale', 'contacted', 17, 1, ['follow' => 4]],
            ['depto_calacoto', 4, 'rent', 'new', 3, 0, []], ['casa_queru', 8, 'sale', 'visit_scheduled', 30, 1, ['follow' => -9]], ['casa_queru', 3, 'anticretico', 'contacted', 11, 0, ['follow' => 5]],
            ['local_canoto', 10, 'rent', 'negotiation', 16, 1, ['follow' => 1]], ['oficina_cbba', 7, 'anticretico', 'new', 4, null, []], ['depto_miraflores', 5, 'anticretico', 'contacted', 8, 1, ['follow' => -3]],
            ['terreno_p3', 6, 'sale', 'new', 5, 0, []], ['sin_fotos', 1, 'rent', 'contacted', 7, 0, ['follow' => 6]], ['casa_equi', 11, 'sale', 'new', 0, 1, ['follow' => 2]],
        ];
        $leads = [];
        foreach ($open as $i => [$pk, $ci, $mod, $stage, $days, $ag, $o]) {
            $leads[$i] = $this->lead($pk, $ci, $mod, $stage, $days, $ag, $o);
        }
        // — perdidas con motivos distintos —
        $this->lead('depto_sky', 6, 'sale', 'closed_lost', 70, 0, ['lost' => 'El precio superó su presupuesto.']);
        $this->lead('casa_palmas', 1, 'rent', 'closed_lost', 45, 1, ['lost' => 'No obtuvo el garante.']);
        $this->lead('depto_calacoto', 8, 'sale', 'closed_lost', 100, 0, ['lost' => 'Desistió de comprar este año.']);
        $this->lead('casa_queru', 11, 'sale', 'closed_lost', 33, 1, ['lost' => 'Compró otra propiedad.']);

        // — visitas en todos los estados y semanas —
        $v = [];
        $v[] = $this->visit('casa_equi', 8, $leads[1], $this->ago(9)->setTime(10, 0), 'done', 0);
        $v[] = $this->visit('depto_sky', 0, $leads[2], $this->ago(3)->setTime(15, 0), 'done', 1);
        $v[] = $this->visit('casa_queru', 8, $leads[10], $this->ago(20)->setTime(11, 0), 'done', 1);
        $v[] = $this->visit('depto_sopo', 9, $leads[7], $this->ago(14)->setTime(16, 0), 'done', 0);
        $v[] = $this->visit('casa_palmas', 11, $leads[6], now()->addDays(2)->setTime(10, 0), 'confirmed', 1);
        $v[] = $this->visit('depto_sky', 0, $leads[2], now()->addDays(3)->setTime(15, 0), 'confirmed', 1);
        $v[] = $this->visit('casa_equi', 3, $leads[0], now()->addDays(4)->setTime(9, 0), 'requested', 0, 'portal');
        $v[] = $this->visit('depto_sirari', 1, $leads[4], now()->addDays(1)->setTime(11, 0), 'requested', 1, 'portal');
        $v[] = $this->visit('depto_calacoto', 3, $leads[8], now()->addDays(5)->setTime(17, 0), 'rescheduled', 1);
        $v[] = $this->visit('local_canoto', 10, $leads[12], now()->addDays(6)->setTime(10, 0), 'confirmed', 1);
        $v[] = $this->visit('casa_queru', 3, $leads[11], $this->ago(7)->setTime(9, 0), 'cancelled', 1, 'panel', 'El cliente tuvo un imprevisto.');
        $v[] = $this->visit('casa_equi', 11, $leads[17], now()->setTime(18, 0)->addMinutes(0), 'confirmed', 0); // hoy a la tarde
        for ($i = 0; $i < 6; $i++) { // historial de visitas pasadas (semanas anteriores)
            $v[] = $this->visit(['casa_equi', 'depto_sky', 'depto_calacoto', 'casa_urbari', 'casa_smiguel', 'terreno_cala'][$i], [8, 0, 3, 6, 0, 8][$i], null, $this->ago(30 + $i * 12)->setTime(10 + $i % 5, 0), $i % 4 === 3 ? 'cancelled' : 'done', $i % 2, 'panel', $i % 4 === 3 ? 'No asistió.' : null);
        }

        $this->line('Oportunidades: ' . Lead::where('tenant_id', $this->tid)->where('notes', '[demo]')->count() . ' · Visitas: ' . count($v));
    }

    // ───────────────────────── contratos legales ─────────────────────────

    protected function agreements(): void
    {
        $svc = app(AgreementService::class);
        $base = fn (string $type) => AgreementTemplate::whereNull('tenant_id')->where('type', $type)->first();
        $mine = [];
        foreach (['rent' => true, 'arras' => true, 'anticretico' => false] as $type => $reviewed) {
            $t = $base($type)?->duplicate($this->tid);
            if (!$t) {
                continue;
            }
            $t->name = $base($type)->name . ' (demo)';
            $t->saveQuietly();
            $reviewed && $t->markReviewed('Estudio Jurídico Vargas & Asociados (demo)', 'Revisión de demostración: no es una revisión real.');
            $mine[$type] = $t->fresh();
        }
        if (count($mine) < 3) {
            $this->warn('Faltan los modelos base de contratos: se omiten los contratos legales.');

            return;
        }

        $mk = function (string $type, string $propKey, int $clientIdx, array $o = []) use ($svc, $mine) {
            $p = $this->props[$propKey];

            return $svc->generate($o + ['type' => $type, 'template_id' => $mine[$type]->id, 'property_id' => $p->id, 'client_id' => $this->clients[$clientIdx]->id,
                'owner_id' => $p->owner_id, 'title' => 'DEMO — ' . \Aero\Realestate\Models\AgreementTemplate::$types[$type] . ' · ' . $p->title, 'variables' => ['folio_real' => '2.01.0.99.' . random_int(100000, 999999), 'place' => $p->city]]);
        };

        // 1. Alquiler firmado, vigente, lejos del vencimiento
        $a = $mk('rent', 'depto_sopo', 10, ['amount' => 450, 'currency' => 'USD', 'deposit' => 450, 'term_months' => 12, 'start_date' => now()->subMonths(8)->toDateString()]);
        $svc->approve($a->fresh());
        $svc->sign($a->fresh(), now()->subMonths(8)->toDateString());
        // 2. Alquiler firmado por vencer en ~30 días (alertas de alquiler)
        $a = $mk('rent', 'depto_sarco', 10, ['amount' => 3800, 'currency' => 'BOB', 'deposit' => 3800, 'term_months' => 12, 'start_date' => now()->addDays(30)->subMonths(12)->toDateString()]);
        $svc->approve($a->fresh());
        $svc->sign($a->fresh(), now()->addDays(30)->subMonths(12)->toDateString());
        // 3. Alquiler renovado
        $a = $mk('rent', 'oficina_equi', 7, ['amount' => 1100, 'currency' => 'USD', 'deposit' => 2200, 'term_months' => 12, 'start_date' => now()->subMonths(11)->toDateString()]);
        $svc->approve($a->fresh());
        $svc->sign($a->fresh(), now()->subMonths(11)->toDateString());
        $svc->renew($a->fresh(), 12, 1200, 'Renovación con ajuste de canon.');
        // 4. Alquiler en borrador (plantilla revisada) y 5. en revisión
        $mk('rent', 'casa_palmas', 11, ['amount' => 7500, 'currency' => 'BOB', 'deposit' => 7500, 'term_months' => 12, 'start_date' => now()->addDays(20)->toDateString()]);
        $svc->submitForReview($mk('rent', 'local_canoto', 10, ['amount' => 900, 'currency' => 'USD', 'deposit' => 1800, 'term_months' => 24, 'start_date' => now()->addDays(40)->toDateString()]));
        // 6. Arras aprobadas (esperan firma)
        $a = $mk('arras', 'casa_queru', 8, ['amount' => 12000, 'total_price' => 245000, 'currency' => 'USD', 'end_date' => now()->addDays(45)->toDateString(), 'variables' => ['folio_real' => '2.01.0.99.445566', 'extra_clauses' => 'Las partes acuerdan que el saldo se pagará mediante crédito hipotecario.']]);
        $svc->approve($a->fresh());
        // 7. Arras firmadas
        $a = $mk('arras', 'casa_equi', 3, ['amount' => 19000, 'total_price' => 380000, 'currency' => 'USD', 'end_date' => now()->addDays(20)->toDateString()]);
        $svc->approve($a->fresh());
        $svc->sign($a->fresh(), $this->ago(4)->toDateString());
        // 8. Alquiler finalizado
        $a = $mk('rent', 'sin_fotos', 1, ['amount' => 2800, 'currency' => 'BOB', 'deposit' => 2800, 'term_months' => 6, 'start_date' => now()->subMonths(8)->toDateString()]);
        $svc->approve($a->fresh());
        $svc->sign($a->fresh(), now()->subMonths(8)->toDateString());
        $svc->finish($a->fresh());
        // 9. Anticrético con plantilla SIN revisión legal: queda en borrador con marca de agua y no se puede aprobar
        $ac = $mk('anticretico', 'ac_far', 2, ['amount' => 30000, 'currency' => 'USD', 'term_months' => 24, 'start_date' => $this->ago(180)->toDateString(), 'contract_id' => $this->contracts['far']->id]);
        // 10. Cancelado
        $svc->cancel($mk('rent', 'depto_sirari', 4, ['amount' => 4200, 'currency' => 'BOB', 'term_months' => 12]), 'El cliente desistió antes de firmar.');

        // fechas de creación escalonadas
        foreach (Agreement::where('tenant_id', $this->tid)->where('title', 'like', 'DEMO%')->orderBy('id')->get() as $i => $ag) {
            $when = $this->ago(100 - $i * 9);
            $this->backdate('aero_realestate_agreements', $ag->id, ['created_at' => $when, 'updated_at' => $when]);
            DB::table('aero_realestate_agreement_events')->where('agreement_id', $ag->id)->orderBy('id')->limit(1)->update(['created_at' => $when]);
        }
        $this->line('Contratos legales: ' . Agreement::where('tenant_id', $this->tid)->where('title', 'like', 'DEMO%')->count());
    }

    // ───────────────────────── archivo documental ─────────────────────────

    protected function documents(): void
    {
        $rows = [
            ['id_card', 'Cédula de Ana Rojas', ['owner' => 0], 'verified', null, 20], ['title_deed', 'Folio real — Casa Equipetrol', ['prop' => 'casa_equi'], 'verified', null, 40],
            ['title_deed', 'Folio real — Depto Torre Sky', ['prop' => 'depto_sky'], 'pending', null, 5], ['tax', 'IPBI 2026 — Casa Urbarí', ['prop' => 'casa_urbari'], 'pending', null, 6],
            ['id_card', 'Cédula de Luis Pérez', ['client' => 0], 'pending', null, 3], ['power_of_attorney', 'Poder notariado de Gladys Camacho', ['owner' => 2], 'verified', now()->addDays(20), 60],
            ['utility', 'Factura de luz — Depto Sopocachi', ['prop' => 'depto_sopo'], 'rejected', null, 12], ['plan', 'Planos aprobados — Casa Queru Queru', ['prop' => 'casa_queru'], 'verified', null, 30],
            ['power_of_attorney', 'Poder vencido — Inversiones Andes', ['owner' => 3], 'verified', now()->subDays(15), 200], ['id_card', 'Cédula de Diego Salvatierra', ['client' => 2], 'verified', null, 90],
            ['title_deed', 'Folio real — Terreno Cala Cala', ['prop' => 'terreno_cala'], 'verified', null, 150], ['other', 'Certificado de no adeudo', ['owner' => 1], 'pending', now()->addDays(300), 1],
        ];
        foreach ($rows as [$type, $name, $subject, $status, $expires, $days]) {
            $d = new Document(['type' => $type, 'name' => $name, 'number' => 'DEMO-' . random_int(1000, 9999), 'issued_on' => $this->ago($days + 30)->toDateString(), 'expires_on' => $expires?->toDateString(), 'notes' => '[demo]']);
            isset($subject['owner']) && $d->owner_id = $this->owners[$subject['owner']]->id;
            isset($subject['client']) && $d->client_id = $this->clients[$subject['client']]->id;
            isset($subject['prop']) && $d->property_id = $this->props[$subject['prop']]->id;
            $d->status = 'pending';
            $d->file = $this->privateFile($name);
            $d->save();
            $this->backdate('aero_realestate_documents', $d->id, ['status' => $status, 'verified_at' => $status === 'pending' ? null : $this->ago($days - 1), 'created_at' => $this->ago($days), 'updated_at' => $this->ago($days)]);
        }
        // un documento del contrato firmado
        $a = Agreement::where('tenant_id', $this->tid)->where('status', 'signed')->where('title', 'like', 'DEMO%')->first();
        if ($a) {
            $d = new Document(['type' => 'contract', 'name' => 'Contrato firmado y escaneado ' . $a->code, 'notes' => '[demo]']);
            $d->agreement_id = $a->id;
            $d->file = $this->privateFile('Contrato ' . $a->code);
            $d->save();
        }
        $this->line('Documentos: ' . Document::where('tenant_id', $this->tid)->where('notes', '[demo]')->count());
    }

    // ───────────────────────── portal: cambios de precio y búsquedas guardadas ─────────────────────────

    protected function portalExtras(): void
    {
        // Cambios de precio y de estado ya «avisados» (para que el cron no escriba a nadie).
        $this->offers['casa_equi']['sale']->update(['amount' => 365000]);
        $this->offers['depto_calacoto']['sale']->update(['amount' => 280000]);
        $this->props['depto_sky']->update(['status' => 'reserved']);
        $this->props['depto_sky']->update(['status' => 'available']);
        Change::whereIn('property_id', collect($this->props)->pluck('id'))->update(['notified_at' => now()]);

        // Búsquedas guardadas INACTIVAS y sin contacto real: se ven en base de datos pero no avisan a nadie.
        foreach ([['Demo · Casas en Santa Cruz hasta USD 400.000', ['type' => 'house', 'city' => 'Santa Cruz de la Sierra', 'price_max' => 400000, 'currency' => 'USD']],
                  ['Demo · Anticrético 2 dorm. hasta USD 40.000', ['modality' => 'anticretico', 'capital_max' => 40000, 'currency' => 'USD', 'bedrooms' => 2]]] as [$name, $criteria]) {
            SavedSearch::create(['tenant_id' => $this->tid, 'name' => $name, 'criteria' => $criteria, 'email' => 'demo.alerta@example.test', 'is_active' => false]);
        }
        \Cache::forget('re-facets:' . $this->tid);
    }

    protected function summary(): void
    {
        $t = $this->tid;
        $this->table(['Qué', 'Cantidad'], [
            ['Agentes / propietarios / clientes', Agent::where('tenant_id', $t)->where('notes', '[demo]')->count() . ' / ' . Owner::where('tenant_id', $t)->where('notes', '[demo]')->count() . ' / ' . Client::where('tenant_id', $t)->where('notes', '[demo]')->count()],
            ['Propiedades (publicadas)', Property::where('tenant_id', $t)->where('code', 'like', 'DEMO-%')->count() . ' (' . Property::where('tenant_id', $t)->where('code', 'like', 'DEMO-%')->where('is_published', true)->count() . ')'],
            ['Ofertas', Offer::whereIn('property_id', Property::where('tenant_id', $t)->where('code', 'like', 'DEMO-%')->pluck('id'))->count()],
            ['Anticréticos (vigentes)', Contract::where('tenant_id', $t)->where('notes', '[demo]')->count() . ' (' . Contract::where('tenant_id', $t)->where('notes', '[demo]')->where('status', 'active')->count() . ')'],
            ['Oportunidades / visitas', Lead::where('tenant_id', $t)->where('notes', '[demo]')->count() . ' / ' . Visit::where('tenant_id', $t)->where('notes', '[demo]')->count()],
            ['Comisiones', Commission::where('tenant_id', $t)->count()],
            ['Contratos legales / documentos', Agreement::where('tenant_id', $t)->where('title', 'like', 'DEMO%')->count() . ' / ' . Document::where('tenant_id', $t)->where('notes', '[demo]')->count()],
        ]);
        $this->info('Listo. Avisos apagados, portal ' . ($this->option('public') ? 'PUBLICADO' : 'sin publicar (usa --public o Configuración → Portal)') . '.');
    }
}
