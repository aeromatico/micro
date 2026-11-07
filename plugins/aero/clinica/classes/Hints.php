<?php namespace Aero\Clinica\Classes;

/**
 * Una línea de ayuda arriba de cada pantalla: para qué sirve y dónde se usa. Texto fijo, sin datos.
 */
class Hints
{
    public const TEXT = [
        'today' => ['Agenda de hoy', 'Las reservas de hoy que vienen de Reservas. Una reserva «pendiente» se confirma con el botón «Confirmar» (o en Reservas); ya confirmada, puede iniciar la consulta. Si la nota ya existe, la retoma o la abre para leerla.'],
        'consultations' => ['Consultas', 'Todas las notas clínicas de la clínica, en borrador y firmadas. Aquí retoma un borrador o abre una nota firmada para leerla, imprimir la receta o las órdenes y agregar una adenda. Una consulta nueva se inicia desde la agenda o desde la ficha del paciente.'],
        'signature' => ['Mi firma', 'Su perfil de médico: título, matrícula, especialidad y las imágenes de firma y sello. Salen impresos en sus notas, recetas y documentos. Cada profesional configura la suya una sola vez.'],
        'notetemplates' => ['Plantillas de nota', 'Definen las secciones con que se escribe una consulta o un control. Cree una propia solo si necesita otra estructura (pediatría, control prenatal…). La plantilla se copia al abrir la nota: editarla después no cambia las notas ya hechas.'],
        'diagnosiscatalogs' => ['Diagnósticos del negocio', 'Opcional. Sus diagnósticos favoritos o con su propia redacción: salen primero en el buscador de la consulta, sin escribir. CIE-10 ya se busca por código o nombre; los «Frecuentes» también crecen solos con las notas firmadas.'],
        'examcatalogs' => ['Exámenes', 'Opcional. La lista que se ofrece al pedir exámenes en una consulta (laboratorio, imagen u otro). Puede escribir un examen a mano aunque no esté aquí.'],
        'analytes' => ['Analitos', 'Lo que se mide en laboratorio (glucosa, hemoglobina…). Se eligen al registrar un resultado en la ficha del paciente y cada uno es una fila de la hoja de seguimiento. Los rangos alto/bajo se enlazan con ellos.'],
        'ranges' => ['Rangos alto/bajo', 'Marcan ▲/▼ en rojo los signos vitales y resultados fuera de rango. Son solo una ayuda visual y no bloquean nada. Un rango incorrecto engaña: use los que un médico apruebe. En laboratorio, la referencia que escribe el propio laboratorio al registrar el resultado manda sobre estos.'],
        'documenttemplates' => ['Formatos de documentos', 'Textos con variables ({{paciente}}, {{diagnostico}}…) para certificados, constancias, informes, consentimientos y derivaciones. Se usan desde la ficha del paciente → Documentos: se genera un borrador, se completan los espacios en blanco y se emite con firma y sello.'],
        'charges' => ['Cobros', 'Registro aparte y opcional del cobro de la atención (efectivo, transferencia o QR). No interviene en la parte clínica ni bloquea firmar. Al pagarse pasa a Finanzas si ese módulo está activo.'],
        'imports' => ['Importar historias anteriores', 'Suba archivos de historias que ya tiene (PDF, imágenes, Word, texto). El sistema sugiere de qué paciente son; usted confirma o descarta cada uno.'],
        'exports' => ['Exportar datos', 'Descarga completa de los datos del negocio o de un paciente (ZIP con los archivos originales). Útil para respaldo o para entregar la historia a un paciente.'],
        'auditlogs' => ['Bitácora', 'Quién abrió, creó, cambió, firmó, imprimió o exportó qué. Registra solo nombres de campos, nunca el contenido clínico, y no se puede editar ni borrar.'],
        'settings' => ['Configuración', 'Recordatorios de vacunas (apagados por defecto) y qué plantilla abre por defecto cada consulta o control. La plantilla por defecto solo importa si tiene más de una por tipo.'],
        'patients' => ['Pacientes', 'Abra una ficha para ver antecedentes, alergias, hoja de seguimiento, laboratorio, vacunas y documentos, o para iniciar una consulta. El buscador filtra por nombre, documento o cliente.'],
    ];

    public static function render(string $screen): string
    {
        [$title, $text] = self::TEXT[$screen] ?? [null, null];
        if (!$text) {
            return '';
        }

        return '<div class="cl-hint" style="margin:0 0 12px;padding:9px 13px;border-left:3px solid #4a90d9;background:rgba(74,144,217,.10);border-radius:3px;font-size:13px;line-height:1.45">'
            . '<strong>' . e($title) . ':</strong> ' . e($text) . '</div>';
    }
}
