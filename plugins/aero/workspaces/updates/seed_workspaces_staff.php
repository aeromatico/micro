<?php

use Aero\Workspaces\Models\Skill;
use Aero\Workspaces\Models\Staff;
use Aero\Workspaces\Models\TaskRate;
use October\Rain\Database\Updates\Seeder;

/**
 * Carga inicial del catálogo: 12 agentes BORRADOR (inactivos) con skills
 * oficiales/hub, tarifa de contratación 0 y tarifa por encargo.
 *
 * Idempotente y sin pisar: busca por slug y, si ya existe, no toca nada, así
 * los cambios del superadmin desde el backend se conservan.
 *
 * Los prompts y textos son propuestas para revisar, no definitivos.
 */
return new class extends Seeder
{
    /** Skills del catálogo oficial/hub (las personales se crean por tenant). */
    protected const SKILLS = [
        'web'   => ['kind' => 'official', 'name' => 'Investigador web', 'description' => 'Úsala cuando el encargo necesite datos, fuentes o tendencias verificables antes de escribir o diseñar.'],
        'guion' => ['kind' => 'official', 'name' => 'Redacción de guiones', 'description' => 'Úsala cuando haya que convertir una idea en guion con escenas, tiempos y llamadas a la acción.'],
        'img'   => ['kind' => 'official', 'name' => 'Generación de imagen', 'description' => 'Úsala cuando el flujo necesite imágenes a partir de un brief: portadas, miniaturas o ilustraciones.'],
        'clip'  => ['kind' => 'official', 'name' => 'Generación de clips', 'description' => 'Envía una tarea asíncrona para crear clips de texto a video o de imagen a video y devuelve el resultado.'],
        'voz'   => ['kind' => 'official', 'name' => 'Locución', 'description' => 'Úsala cuando haga falta voz en off. Crea la tarea de audio, devuelve su id y descarga el archivo final.'],
        'mus'   => ['kind' => 'official', 'name' => 'Música de fondo', 'description' => 'Úsala cuando un video o contenido necesite música ambiental con duración y tono definidos.'],
        'mon'   => ['kind' => 'official', 'name' => 'Montaje final', 'description' => 'Úsala cuando el video está listo para ensamblarse: recibe clips, audio, subtítulos y parámetros de edición.'],
        'seo'   => ['kind' => 'official', 'name' => 'Análisis SEO', 'description' => 'Úsala para proponer títulos, descripciones y etiquetas según la intención de búsqueda del tema.'],
        'ppt'   => ['kind' => 'official', 'name' => 'Maquetación de presentaciones', 'description' => 'Úsala cuando haya que ordenar contenido en diapositivas con jerarquía y estilo coherentes.'],
        'sub'   => ['kind' => 'hub', 'name' => 'Subtitulado automático', 'description' => 'Genera subtítulos sincronizados desde el audio. Compartida por otros equipos del Skill Hub.'],
        'res'   => ['kind' => 'hub', 'name' => 'Resumen de reuniones', 'description' => 'Resume una transcripción en acuerdos, responsables y fechas. Compartida por otros equipos.'],
    ];

    /**
     * slug, nombre, rol, categoría, rareza, bio, etiquetas, capacidades [Creatividad, Viralidad, Ejecución, Narrativa, Estética, Eficiencia],
     * skills, precio por encargo, avatar (índice del recorte original), orden, guía.
     */
    protected const STAFF = [
        ['ada-quispe', 'Ada Quispe', 'Coordinadora', 'contenido', 'ssr', 'Recibe tu encargo, lo divide en tareas y arma el equipo justo. Cuida plazos y presupuesto.', ['planificación', 'coordinación', 'presupuesto'], [78, 70, 94, 88, 72, 90], ['res', 'web'], 60, 4, 1, ['Pídele objetivos completos.', 'Dile el presupuesto máximo en puntos y la fecha límite.', 'Dale el público al que va dirigido el resultado.']],
        ['mateo-rojas', 'Mateo Rojas', 'Investigador', 'info', 'sr', 'Busca, contrasta y resume fuentes. Entrega datos claros y citados para que el equipo escriba sin inventar.', ['fuentes', 'tendencias', 'datos'], [66, 58, 90, 74, 55, 92], ['web', 'res'], 35, 2, 2, ['Dale un tema y una pregunta concreta.', 'Indica el país o idioma de las fuentes.', 'Pídele un máximo de 5 hallazgos con enlace.']],
        ['lucia-vargas', 'Lucía Vargas', 'Guionista', 'contenido', 'sr', 'Escribe guiones con ritmo, gancho en los primeros segundos y cierre claro.', ['guiones', 'ganchos', 'storytelling'], [88, 82, 80, 95, 70, 78], ['guion'], 40, 1, 3, ['Cuéntale la duración y la plataforma.', 'Dale el tono: cercano, técnico o humorístico.']],
        ['hugo-mamani', 'Hugo Mamani', 'Especialista SEO', 'info', 'r', 'Convierte el tema en títulos y etiquetas que la gente sí busca.', ['seo', 'títulos', 'palabras clave'], [60, 76, 84, 55, 48, 94], ['seo'], 20, 7, 4, ['Dale el tema y el canal donde publicarás.', 'Pídele 5 títulos y una descripción de 150 caracteres.']],
        ['sofia-ortiz', 'Sofía Ortiz', 'Diseñadora de miniaturas', 'imagen', 'sr', 'Diseña portadas que se leen en un vistazo: contraste fuerte, rostro claro y poco texto.', ['miniaturas', 'portadas', 'color'], [92, 86, 78, 70, 94, 80], ['img'], 45, 11, 5, ['Pásale el título del video y una referencia visual.', 'Pídele 3 variantes para elegir.']],
        ['bruno-claros', 'Bruno Claros', 'Editor de video', 'video', 'ssr', 'Monta clips, audio y subtítulos en una entrega lista para publicar.', ['montaje', 'ritmo', 'exportación'], [80, 78, 96, 84, 88, 86], ['mon', 'sub', 'mus'], 90, 10, 6, ['Entrégale guion, clips y audio ya aprobados.', 'Indica formato (16:9 o 9:16) y duración final.']],
        ['camila-soria', 'Camila Soria', 'Directora de video', 'video', 'ssr', 'Dirige piezas completas de principio a fin: plan de escenas, clips y revisión de continuidad.', ['dirección', 'escenas', 'continuidad'], [94, 88, 90, 92, 91, 76], ['clip', 'guion', 'mon'], 120, 8, 7, ['Dale una idea y un público; ella arma el plan de escenas.', 'Aprueba el plan antes de generar los clips.']],
        ['diego-lara', 'Diego Lara', 'Ilustrador', 'imagen', 'r', 'Ilustra personajes y escenas en estilos definidos, constante para series con el mismo look.', ['personajes', 'ilustración', 'estilo'], [84, 60, 72, 66, 90, 70], ['img'], 25, 9, 8, ['Descríbele el personaje y el estilo en dos frases.']],
        ['valerio-pena', 'Valerio Peña', 'Redactor de contenido', 'contenido', 'r', 'Escribe artículos, publicaciones y correos con claridad. Prefiere frases cortas y datos concretos.', ['artículos', 'blog', 'correos'], [72, 64, 82, 78, 60, 88], ['guion', 'web'], 20, 5, 9, ['Dale tema, extensión y público.', 'Pídele una estructura antes del texto final.']],
        ['tomas-aguilar', 'Tomás Aguilar', 'Presentaciones', 'ppt', 'sr', 'Ordena ideas en diapositivas con jerarquía clara. Menos texto, más mensaje.', ['diapositivas', 'jerarquía', 'ventas'], [76, 58, 90, 82, 86, 84], ['ppt'], 30, 0, 10, ['Pásale el objetivo y la duración de la charla.', 'Dile cuántas diapositivas quieres como máximo.']],
        ['renata-villca', 'Renata Villca', 'Locutora IA', 'video', 'sr', 'Locuta guiones con naturalidad en varios tonos. Marca pausas y énfasis donde el texto lo pide.', ['voz', 'locución', 'tono'], [74, 70, 88, 80, 62, 90], ['voz', 'sub'], 30, 6, 11, ['Entrégale el guion final con marcas de pausa.', 'Elige el tono: cálido, serio o enérgico.']],
        ['ivan-cuellar', 'Iván Cuéllar', 'Analista de datos', 'info', 'r', 'Convierte tablas en conclusiones claras con gráficos sencillos. Señala lo que importa y lo que falta.', ['análisis', 'gráficos', 'métricas'], [58, 50, 86, 64, 66, 90], ['web', 'res'], 25, 3, 12, ['Pásale la tabla y la pregunta de negocio.', 'Indica el periodo que quieres comparar.']],
    ];

    public function run(): void
    {
        $skillIds = [];

        foreach (static::SKILLS as $slug => $data) {
            $skill = Skill::firstOrCreate(
                ['tenant_id' => null, 'slug' => $slug],
                ['kind' => $data['kind'], 'name' => $data['name'], 'description' => $data['description']]
            );
            $skillIds[$slug] = $skill->id;
        }

        foreach (static::STAFF as [$slug, $name, $role, $category, $rarity, $bio, $tags, $caps, $skills, $fee, $avatar, $order, $guide]) {
            if (Staff::where('slug', $slug)->exists()) {
                continue;
            }

            $staff = new Staff();
            $staff->fill([
                'name' => $name, 'slug' => $slug, 'role' => $role, 'kind' => 'ai', 'rarity' => $rarity,
                'category' => $category, 'bio' => $bio, 'tags' => $tags,
                'capabilities' => ['creatividad' => $caps[0], 'viralidad' => $caps[1], 'ejecucion' => $caps[2], 'narrativa' => $caps[3], 'estetica' => $caps[4], 'eficiencia' => $caps[5]],
                'guide' => $guide,
                'system_prompt' => "Eres {$name}, {$role} del equipo. {$bio} Responde en español, de forma clara, y entrega solo lo que se te pidió. [BORRADOR: prompt pendiente de revisión]",
                'connector_id' => 26,
                'avatar' => "crop:{$avatar}",
                'is_active' => false,
                'sort_order' => $order,
                'hire_fee' => 0,
            ]);
            $staff->save();

            // Tarifa por encargo: el precio que venía en el prototipo (puntos por encargo).
            TaskRate::create(['staff_id' => $staff->id, 'task_type' => 'encargo', 'fee' => $fee]);

            $staff->skills()->attach(array_map(fn ($s) => $skillIds[$s], $skills));
        }
    }
};
