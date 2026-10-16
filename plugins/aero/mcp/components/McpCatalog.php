<?php namespace Aero\Mcp\Components;

use Aero\Mcp\Classes\McpToolRegistry;
use Aero\Mcp\Models\Settings;
use Cms\Classes\ComponentBase;

/**
 * Alimenta la página pública /docs/mcp con el catálogo REAL de tools: solo los
 * módulos activados en Ajustes → MCP (los demás no responden, así que no se
 * anuncian). Solo nombres y resúmenes; nunca datos de ningún tenant.
 */
class McpCatalog extends ComponentBase
{
    /** Etiqueta y gancho de cada módulo; los no listados se muestran con su nombre. */
    protected const LABELS = [
        'crm'        => ['CRM', 'Contactos, empresas, oportunidades, tickets y actividades.'],
        'shop'       => ['Tienda', 'Productos, colecciones, clientes y pedidos.'],
        'pay'        => ['Bolivia Pay', 'Cobros por QR, pagos y cuentas bancarias.'],
        'finance'    => ['Finanzas', 'Ingresos, egresos, libro diario y caja chica.'],
        'hello'      => ['Hello', 'Conversaciones, mensajes, contactos y campañas por WhatsApp.'],
        'sms'        => ['SMS', 'Mensajes, lotes y plantillas.'],
        'gym'        => ['Gimnasio', 'Socios, membresías, clases y reservas.'],
        'school'     => ['Colegios', 'Alumnos, cobranza, asistencia y boletines.'],
        'office'     => ['Reservas', 'Recursos y reservas de oficinas y salas.'],
        'pos'        => ['Punto de venta', 'Ventas, turnos, mesas y movimientos de caja.'],
        'sites'      => ['Sitios', 'Páginas, dominios, formularios de contacto y equipo.'],
        'workflows'  => ['Automatizaciones', 'Flujos por nodos y su historial de ejecuciones.'],
        'workspaces' => ['Oficina IA', 'Agentes, tareas y habilidades.'],
        'tracking'   => ['Seguimiento', 'Flotas, trabajos, paradas y posiciones.'],
        'livechat'   => ['Chat en vivo', 'Conversaciones y bandejas del widget.'],
        'credits'    => ['Créditos', 'Saldos, movimientos y compras.'],
        'docs'       => ['Documentación', 'Artículos y categorías.'],
        'invoicing'  => ['Facturación', 'Comprobantes y su estado.'],
        'api_docs'   => ['Catálogo de API', 'Consulta qué endpoints existen y qué permisos piden.'],
        'services'   => ['Servicios', 'Servicios contratados por el tenant.'],
        'sheets'     => ['Google Sheets', 'Importación y exportación con hojas de cálculo.'],
        'site'       => ['Sitio', 'Datos de contacto y landing del sitio.'],
        'workflows_builder' => ['Constructor de automatizaciones', 'Crear, validar y editar flujos.'],
        'wpflash'    => ['WordPress Flash', 'Sitios WordPress y sus vínculos de productos y clientes.'],
        'chat'       => ['Chat de pedidos', 'Cobros, pedidos y eventos del chat.'],
        'chatbots'   => ['Chatbots', 'Bots configurados.'],
        'notify'     => ['Notificaciones', 'Reglas, plantillas y envíos.'],
        'hub'        => ['Hub', 'Trabajos de generación de medios.'],
        'shopify'    => ['Shopify', 'Tiendas conectadas y sus pedidos.'],
        'community'  => ['Comunidad', 'Foro y sugerencias.'],
    ];

    public array $modules = [];
    public int $toolCount = 0;
    public string $modulesJson = '[]';

    public function componentDetails(): array
    {
        return [
            'name'        => 'Catálogo MCP',
            'description' => 'Módulos y tools del servidor MCP activados en la plataforma.',
        ];
    }

    public function onRun()
    {
        $enabled = Settings::enabledModules();
        $grouped = [];

        foreach (McpToolRegistry::catalog() as $name => $tool) {
            if (!in_array($tool['module'], $enabled, true)) {
                continue;
            }

            $grouped[$tool['module']][] = [
                'name'    => (string) $name,
                'summary' => $this->summary((string) ($tool['description'] ?? $name)),
                'write'   => (bool) $tool['write'],
            ];
        }

        ksort($grouped);

        foreach ($grouped as $module => $tools) {
            [$label, $blurb] = self::LABELS[$module] ?? [ucfirst(str_replace('_', ' ', $module)), ''];
            usort($tools, fn ($a, $b) => [$a['write'], $a['name']] <=> [$b['write'], $b['name']]);

            $this->modules[] = [
                'id'     => $module,
                'label'  => $label,
                'blurb'  => $blurb,
                'tools'  => $tools,
                'writes' => count(array_filter($tools, fn ($t) => $t['write'])),
            ];
            $this->toolCount += count($tools);
        }

        $this->modulesJson = str_replace('</', '<\/', json_encode($this->modules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** Primera frase, sin la lista de campos devueltos. */
    protected function summary(string $description): string
    {
        $first = trim(explode('. Campos', $description)[0]);
        $first = preg_split('/(?<=[.!?])\s/u', $first)[0] ?? $first;

        return mb_strlen($first) > 140 ? mb_substr($first, 0, 137) . '…' : $first;
    }
}
