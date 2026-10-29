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

    public static function forFrontend(): array
    {
        return [
            'tracks' => [
                ['k' => 'shorts', 'kicker' => 'Formato corto', 'title' => 'Shorts', 'sub' => 'Creo, actúo o modelo en video vertical'],
                ['k' => 'live', 'kicker' => 'En vivo', 'title' => 'Live', 'sub' => 'Vendo en transmisiones en vivo'],
                ['k' => 'edit', 'kicker' => 'Posproducción', 'title' => 'Edición', 'sub' => 'Edito, animo o produzco video para marcas'],
            ],
            'levels' => [
                ['n' => 1, 'nick' => 'Rookie', 'title' => 'Tengo cualidades', 'sub' => 'Quiero probar'],
                ['n' => 2, 'nick' => 'En racha', 'title' => 'Tengo algo de experiencia', 'sub' => 'Estoy listo'],
                ['n' => 3, 'nick' => 'Pro', 'title' => 'Tengo mucha experiencia', 'sub' => 'Estoy listo'],
                ['n' => 4, 'nick' => 'Leyenda', 'title' => 'Soy ampliamente reconocido', 'sub' => 'Mi trabajo habla solo'],
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
