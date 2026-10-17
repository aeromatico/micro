<?php namespace Aero\Plan\Classes;

use ApplicationException;

/**
 * Definición única de los 5 pasos y de lo que se le pide al dueño en cada uno.
 * La usan la pantalla (para pintar), el servicio (para validar qué se acepta y
 * qué falta antes de enviar a revisión) y los tests.
 *
 * Tipos: text, area, url, select, multi, tasks, date, confirm (los llena el
 * dueño); team y tracker (los llena el equipo de la plataforma).
 */
class Stages
{
    public const COUNT = 5;

    public const RUBROS = [
        'Comercio o tienda',
        'Restaurante o cafetería',
        'Salud (consultorio, veterinaria)',
        'Gimnasio o deportes',
        'Educación',
        'Servicios profesionales',
        'Otro',
    ];

    /** Preguntas clave por rubro (se suman a las genéricas del paso 1). */
    public const RUBRO_QUESTIONS = [
        'Comercio o tienda' => ['¿Cuántos productos manejas y llevas inventario?', '¿Cómo cobras hoy: efectivo, QR o transferencia?', '¿Vendes también por WhatsApp o redes sociales?'],
        'Restaurante o cafetería' => ['¿Atiendes en mesa, para llevar o con delivery?', '¿Cuántos platos tiene tu carta y cambia a diario?', '¿Cómo recibes los pedidos hoy?'],
        'Salud (consultorio, veterinaria)' => ['¿Atiendes personas, animales o ambos?', '¿Cuántos profesionales tienes y con qué horarios?', '¿Cómo agendas citas y guardas los historiales hoy?'],
        'Gimnasio o deportes' => ['¿Cuántos socios activos tienes y qué tipos de membresía?', '¿Ofreces clases con cupo limitado?', '¿Cómo cobras y controlas los vencimientos?'],
        'Educación' => ['¿Cuántos estudiantes y cursos manejas?', '¿Cómo cobras pensiones o matrículas?', '¿Cómo te comunicas con los apoderados?'],
        'Servicios profesionales' => ['¿Qué servicios ofreces y cómo los cotizas?', '¿Cómo agendas reuniones o reservas?', '¿Cómo das seguimiento a clientes potenciales?'],
        'Otro' => ['¿Qué es lo que más repites cada día en tu operación?', '¿Qué información necesitas guardar de cada cliente?'],
    ];

    public const TRACKER_STATES = ['pending' => 'Pendiente', 'doing' => 'En curso', 'done' => 'Listo'];

    public static function all(): array
    {
        return [
            1 => ['name' => 'Conocernos', 'goal' => 'Queremos entender cómo operas hoy: tus clientes, tu equipo y dónde se te va el tiempo.', 'fields' => [
                ['k' => 'negocio', 't' => 'text', 'l' => 'Nombre de tu negocio', 'req' => 1],
                ['k' => 'rubro', 't' => 'select', 'l' => 'Rubro', 'req' => 1, 'o' => self::RUBROS],
                ['k' => 'perfil', 't' => 'select', 'l' => '¿En qué momento estás?', 'req' => 1, 'o' => ['Tengo una oportunidad clara y quiero lanzarla', 'Ya opero y quiero ordenar mi trabajo']],
                ['k' => 'hoy', 't' => 'multi', 'l' => '¿Con qué trabajas hoy?', 'req' => 1, 'o' => ['Hojas de cálculo', 'WhatsApp', 'Cuaderno o papel', 'Otro software', 'Nada todavía']],
                ['k' => 'dolor', 't' => 'area', 'l' => '¿Qué te hace perder más tiempo o dinero hoy?', 'req' => 1],
                ['k' => 'meta', 't' => 'area', 'l' => '¿Qué debería poder hacer tu sistema el día del lanzamiento?', 'req' => 1],
                ['k' => 'muestra', 't' => 'url', 'l' => 'Una muestra de cómo operas hoy', 'h' => 'Enlace a una planilla, un catálogo o una carpeta con fotos. Opcional.'],
                ['k' => 'contacto', 't' => 'text', 'l' => 'Contacto principal y horario preferido', 'req' => 1],
            ]],
            2 => ['name' => 'Diseñar el plan', 'goal' => 'Traducimos tu operación a módulos de Market y acordamos qué entra en la primera versión.', 'fields' => [
                ['k' => 'modulos', 't' => 'multi', 'l' => '¿Qué módulos te interesan?', 'req' => 1, 'o' => ['Sitio web', 'Tienda en línea', 'CRM y clientes', 'Mensajes por WhatsApp', 'Cobros con QR', 'Automatizaciones', 'Agentes de IA', 'Reservas']],
                ['k' => 'usuarios', 't' => 'select', 'l' => '¿Cuántas personas lo usarán?', 'req' => 1, 'o' => ['Solo yo', '2 a 5 personas', '6 a 20 personas', 'Más de 20']],
                ['k' => 'imprescindible', 't' => 'area', 'l' => '¿Qué es imprescindible para el lanzamiento y qué puede esperar?', 'req' => 1],
                ['k' => 'alcance', 't' => 'team', 'l' => 'Alcance propuesto por Clouds', 'ph' => 'Clouds está redactando el alcance a partir de tus respuestas.'],
                ['k' => 'acepto', 't' => 'confirm', 'l' => 'Acepto el alcance propuesto', 'req' => 1, 'needs' => 'alcance'],
            ]],
            3 => ['name' => 'Construir y conectar', 'goal' => 'Configuramos la plataforma y conectamos tus canales. Tú nos pasas el material.', 'fields' => [
                ['k' => 'marca', 't' => 'url', 'l' => 'Logo, colores y textos de tu marca', 'req' => 1, 'h' => 'Enlace a una carpeta compartida.'],
                ['k' => 'catalogo', 't' => 'url', 'l' => 'Catálogo de productos o servicios', 'req' => 1, 'h' => 'Una planilla o carpeta con nombres, precios y fotos.'],
                ['k' => 'canales', 't' => 'text', 'l' => 'Número de WhatsApp y cuenta para cobros con QR', 'req' => 1, 'h' => 'Solo el número y el banco. Nunca nos envíes contraseñas por aquí.'],
                ['k' => 'dominio', 't' => 'text', 'l' => 'Dominio propio, si ya tienes uno'],
                ['k' => 'avance', 't' => 'tracker', 'l' => 'Avance de la construcción', 'items' => ['Plataforma configurada', 'Sitio o tienda armados', 'Canales conectados', 'Desarrollo a medida']],
            ]],
            4 => ['name' => 'Probar con tu equipo', 'goal' => 'Usa el sistema como lo harías a diario. Lo que no funcione lo corregimos juntos.', 'fields' => [
                ['k' => 'pruebas', 't' => 'tasks', 'l' => 'Lista de pruebas', 'req' => 1, 'o' => ['Hice un pedido o reserva de prueba de punta a punta', 'Probé un cobro con QR', 'Una persona de mi equipo usó el panel', 'Revisé los textos y mensajes automáticos']],
                ['k' => 'hallazgos', 't' => 'area', 'l' => 'Lo que no funcionó o cambiarías', 'h' => 'Opcional. Cuéntanos lo que viste.'],
            ]],
            5 => ['name' => 'Lanzar y acompañar', 'goal' => 'Dejamos todo listo para usar, tu equipo conoce y dispone de Market, y te acompañamos al inicio.', 'fields' => [
                ['k' => 'equipo', 't' => 'area', 'l' => 'Personas que usarán Market y qué hará cada una', 'req' => 1],
                ['k' => 'fecha', 't' => 'date', 'l' => 'Fecha en que quieres lanzar', 'req' => 1],
                ['k' => 'listo', 't' => 'tasks', 'l' => 'Antes de abrir', 'req' => 1, 'o' => ['Mi equipo conoce el sistema y tiene su acceso', 'Mis clientes ya pueden contactarme por los canales conectados', 'Sé a quién escribir si necesito ayuda']],
            ]],
        ];
    }

    public static function find(int $number): ?array
    {
        return self::all()[$number] ?? null;
    }

    /** Campos del paso; en el 1 se añaden las preguntas clave del rubro elegido. */
    public static function fields(int $number, array $answers = []): array
    {
        $fields = self::find($number)['fields'] ?? [];

        if ($number !== 1) {
            return $fields;
        }

        $questions = [];
        foreach (self::RUBRO_QUESTIONS[$answers['rubro'] ?? ''] ?? [] as $i => $q) {
            $questions[] = ['k' => 'rq' . $i, 't' => 'area', 'l' => $q, 'req' => 1, 'h' => 'Pregunta clave de tu rubro'];
        }

        $at = 0;
        foreach ($fields as $i => $f) {
            if ($f['k'] === 'rubro') {
                $at = $i + 1;
            }
        }

        return array_merge(array_slice($fields, 0, $at), $questions, array_slice($fields, $at));
    }

    public static function isTeamField(array $f): bool
    {
        return in_array($f['t'], ['team', 'tracker'], true);
    }

    public static function filled(array $f, array $answers): bool
    {
        $v = $answers[$f['k']] ?? null;

        return match ($f['t']) {
            'multi'   => is_array($v) && count($v) > 0,
            'tasks'   => is_array($v) && count($v) === count($f['o']),
            'confirm' => $v === true,
            default   => is_string($v) && trim($v) !== '',
        };
    }

    /** Campos obligatorios que todavía faltan (etiquetas). */
    public static function missing(int $number, array $answers): array
    {
        $out = [];
        foreach (self::fields($number, $answers) as $f) {
            if (empty($f['req'])) {
                continue;
            }
            $blocked = !empty($f['needs']) && trim((string) ($answers[$f['needs']] ?? '')) === '';
            if ($blocked || !self::filled($f, $answers)) {
                $out[] = $f['l'];
            }
        }

        return $out;
    }

    public static function requiredCount(int $number, array $answers): int
    {
        return count(array_filter(self::fields($number, $answers), fn ($f) => !empty($f['req'])));
    }

    /**
     * Respuestas del dueño a partir del formulario. Reemplaza todos sus campos
     * (un checkbox sin marcar no llega en el POST) y conserva lo del equipo.
     * Solo acepta valores de las opciones declaradas.
     */
    public static function sanitizeClient(int $number, array $input, array $current): array
    {
        $answers = $current;

        // El rubro define qué preguntas clave existen: se resuelve primero.
        if ($number === 1 && array_key_exists('rubro', $input)) {
            $rubro = (string) $input['rubro'];
            $answers['rubro'] = in_array($rubro, self::RUBROS, true) ? $rubro : '';
        }

        foreach (self::fields($number, $answers) as $f) {
            if (self::isTeamField($f) || $f['k'] === 'rubro' && $number === 1) {
                continue;
            }
            $raw = $input[$f['k']] ?? null;

            $answers[$f['k']] = match ($f['t']) {
                'text'    => self::str($raw, 300),
                'area'    => self::str($raw, 4000),
                'url'     => self::url($raw, $f['l']),
                'select'  => in_array((string) $raw, $f['o'], true) ? (string) $raw : '',
                'multi', 'tasks' => array_values(array_intersect($f['o'], is_array($raw) ? $raw : [])),
                'date'    => self::date($raw),
                'confirm' => !empty($raw) && trim((string) ($answers[$f['needs'] ?? ''] ?? '')) !== '',
                default   => $answers[$f['k']] ?? null,
            };
        }

        return $answers;
    }

    /** Lo que redacta el equipo: texto libre y el estado de cada avance. */
    public static function sanitizeTeam(int $number, array $input, array $current): array
    {
        $answers = $current;

        foreach (self::fields($number, $answers) as $f) {
            if ($f['t'] === 'team' && array_key_exists($f['k'], $input)) {
                $answers[$f['k']] = self::str($input[$f['k']], 4000);
            }
            if ($f['t'] === 'tracker') {
                $state = [];
                foreach ($f['items'] as $i => $_) {
                    $v = $input[$f['k']][$i] ?? 'pending';
                    $state[$i] = array_key_exists($v, self::TRACKER_STATES) ? $v : 'pending';
                }
                $answers[$f['k']] = $state;
            }
        }

        return $answers;
    }

    protected static function str($v, int $max): string
    {
        return mb_substr(trim((string) (is_array($v) ? '' : $v)), 0, $max);
    }

    protected static function url($v, string $label): string
    {
        $v = self::str($v, 500);

        if ($v !== '' && !preg_match('#^https?://[^\s]+$#i', $v)) {
            throw new ApplicationException("«{$label}» debe ser un enlace que empiece con http:// o https://");
        }

        return $v;
    }

    protected static function date($v): string
    {
        $v = self::str($v, 10);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) !== false ? $v : '';
    }
}
