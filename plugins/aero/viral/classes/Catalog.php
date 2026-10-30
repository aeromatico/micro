<?php namespace Aero\Viral\Classes;

/** Datos fijos del formulario: caminos, niveles, etiquetas y geografía de Bolivia. */
class Catalog
{
    public const TAGS = [
        'shorts' => ['UGC', 'Spokesperson', 'Reseñas técnicas', 'Tutoriales', 'Unboxing', 'Sketches cómicos', 'Tendencias', 'Retos virales', 'Storytelling', 'Hook en 3 s', 'Guion publicitario', 'Actuación', 'Modelo de producto', 'Lifestyle', 'Moda', 'Maquillaje', 'Faceless', 'Voz en off', 'Avatares IA', 'Video con IA', 'Stock + IA', 'Marketing de afiliados'],
        'live' => ['Live shopping', 'TikTok Shop', 'Cierre de venta', 'Manejo de chat', 'Demostración en vivo', 'Improvisación', 'Flash sales', 'Subastas en vivo', 'Lives de +2 horas', 'Co-host', 'Moderación de chat', 'OBS / multicámara', 'Moda en vivo', 'Belleza en vivo', 'Tecnología en vivo', 'Hogar y cocina', 'Suplementos y fitness', 'Joyería y accesorios'],
        'edit' => ['Clipmaker', 'Podcast a vertical', 'Highlights de stream', 'Subtítulos dinámicos', 'Ritmo y cortes', 'Motion graphics', 'Tipografía cinética', 'Infografías animadas', 'Color grading', 'Sonido y mezcla', 'Guion y storyboard', 'Dirección de video vertical', 'Iluminación', 'Anuncios cinematográficos', 'Edición con IA', 'Voces en off IA', 'Edición de replays de live', 'CapCut', 'Premiere Pro', 'After Effects', 'DaVinci Resolve', 'Final Cut', 'Blender / 3D', 'Entrega en 24 h'],
    ];

    public const DEPARTMENTS = [
        'La Paz' => ['La Paz', 'El Alto', 'Viacha', 'Achacachi', 'Copacabana', 'Coroico', 'Caranavi', 'Sorata'],
        'Santa Cruz' => ['Santa Cruz de la Sierra', 'Montero', 'Warnes', 'La Guardia', 'Cotoca', 'Camiri', 'San Ignacio de Velasco', 'Puerto Quijarro'],
        'Cochabamba' => ['Cochabamba', 'Quillacollo', 'Sacaba', 'Sipe Sipe', 'Colcapirhua', 'Tiquipaya', 'Vinto', 'Villa Tunari'],
        'Oruro' => ['Oruro', 'Huanuni', 'Challapata', 'Caracollo'],
        'Potosí' => ['Potosí', 'Uyuni', 'Villazón', 'Tupiza', 'Llallagua'],
        'Chuquisaca' => ['Sucre', 'Monteagudo', 'Camargo', 'Padilla', 'Tarabuco'],
        'Tarija' => ['Tarija', 'Yacuiba', 'Villa Montes', 'Bermejo', 'Entre Ríos'],
        'Beni' => ['Trinidad', 'Riberalta', 'Guayaramerín', 'Santa Ana del Yacuma', 'San Borja'],
        'Pando' => ['Cobija', 'Porvenir', 'Puerto Rico'],
    ];

    /** Modelos de tarifa que el creador puede elegir en Live. */
    const LIVE_MODELS = ['per_hour' => 'Por hora', 'commission_fixed' => 'Comisión fija por venta', 'commission_percent' => 'Comisión % por venta'];
    const SHORTS_FIXED = 3.0;       // Noob: Bs por segundo
    const SHORTS_RANGE = [3.0, 10.0]; // Casual: la tarifa final la fija la revisión dentro de este rango
    const MAX_RATE = 100000;

    /**
     * Tarifa según camino y nivel. Devuelve ['model' => ?, 'amount' => ?float] o ['errors' => [campo => mensaje]].
     * Lo fijo lo decide el servidor; solo se lee lo que la persona puede elegir (Pro/Viral en Shorts, Live).
     */
    public static function resolveRate(string $track, int $level, ?string $model, $amount): array
    {
        $amount = is_numeric($amount) ? round((float) $amount, 2) : null;

        if ($track === 'edit') {
            return ['model' => 'per_project', 'amount' => null];
        }
        if ($track === 'shorts') {
            if ($level === 1) {
                return ['model' => 'per_second', 'amount' => self::SHORTS_FIXED];
            }
            if ($level === 2) {
                return ['model' => 'per_second', 'amount' => null];
            }
            if ($amount === null || $amount <= 0 || $amount > 1000) {
                return ['errors' => ['rate_amount' => 'Escribe tu tarifa en Bs por segundo.']];
            }

            return ['model' => 'per_second', 'amount' => $amount];
        }
        if (!isset(self::LIVE_MODELS[$model])) {
            return ['errors' => ['rate_model' => 'Elige cómo quieres cobrar.']];
        }
        $max = $model === 'commission_percent' ? 100 : self::MAX_RATE;
        if ($amount === null || $amount <= 0 || $amount > $max) {
            return ['errors' => ['rate_amount' => $model === 'commission_percent' ? 'Escribe un porcentaje entre 1 y 100.' : 'Escribe el monto en Bs.']];
        }

        return ['model' => $model, 'amount' => $amount];
    }

    /** Texto legible de la tarifa para el panel y la pantalla de estado. */
    public static function rateLabel(?string $model, $amount, string $track = '', int $level = 0): string
    {
        $n = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
        switch ($model) {
            case 'per_second':
                if ($amount === null) {
                    return 'Bs ' . $n(self::SHORTS_RANGE[0]) . ' a ' . $n(self::SHORTS_RANGE[1]) . ' por segundo (se define al revisar)';
                }
                return 'Bs ' . $n($amount) . ' por segundo';
            case 'per_hour': return 'Bs ' . $n($amount) . ' por hora';
            case 'commission_fixed': return 'Bs ' . $n($amount) . ' por venta';
            case 'commission_percent': return $n($amount) . '% por venta';
            case 'per_project': return 'Tarifa propia por proyecto';
        }

        return '—';
    }

    public static function forFrontend(): array
    {
        return [
            'tracks' => [
                ['k' => 'shorts', 'kicker' => 'Formato corto', 'title' => 'Shorts', 'sub' => 'Creo, actúo o modelo en video vertical'],
                ['k' => 'live', 'kicker' => 'En vivo', 'title' => 'Live', 'sub' => 'Vendo en transmisiones en vivo'],
                ['k' => 'edit', 'kicker' => 'Posproducción', 'title' => 'Edición', 'sub' => 'Edito, animo o produzco video para marcas'],
            ],
            'levels' => [
                ['n' => 1, 'nick' => 'Noob', 'title' => 'Tengo cualidades', 'sub' => 'Quiero probar'],
                ['n' => 2, 'nick' => 'Casual', 'title' => 'Tengo algo de experiencia', 'sub' => 'Estoy listo'],
                ['n' => 3, 'nick' => 'Pro', 'title' => 'Tengo mucha experiencia', 'sub' => 'Estoy listo'],
                ['n' => 4, 'nick' => 'Viral', 'title' => 'Soy ampliamente reconocido', 'sub' => 'Mi trabajo habla solo'],
            ],
            'rates' => [
                'shorts_fixed' => self::SHORTS_FIXED, 'shorts_range' => self::SHORTS_RANGE,
                'live_models' => array_map(fn ($k, $v) => ['k' => $k, 'label' => $v], array_keys(self::LIVE_MODELS), self::LIVE_MODELS),
            ],
            'tags' => self::TAGS,
            'departments' => self::DEPARTMENTS,
        ];
    }

    /** Celular boliviano: 8 dígitos que empiezan en 6 o 7. Devuelve +591XXXXXXXX o null. */
    public static function normalizePhone(?string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw);
        if (str_starts_with($digits, '591') && strlen($digits) === 11) {
            $digits = substr($digits, 3);
        }
        if (str_starts_with($digits, '00591')) {
            $digits = substr($digits, 5);
        }

        return preg_match('/^[67]\d{7}$/', $digits) ? '+591' . $digits : null;
    }

    public static function normalizeUrl(string $url): string
    {
        $url = trim($url);

        return preg_match('#^https?://#i', $url) ? $url : 'https://' . $url;
    }
}
