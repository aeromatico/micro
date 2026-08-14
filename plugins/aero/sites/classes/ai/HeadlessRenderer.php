<?php namespace Aero\Sites\Classes\Ai;

/**
 * Renderiza Puck data a HTML estático.
 *
 * Usa el renderizador PHP puro (PuckHtmlRenderer) que replica los componentes
 * del editor visual. No depende de Node ni de proc_open, por lo que funciona
 * en cualquier hosting (incluidos los que bloquean funciones de sistema).
 */
class HeadlessRenderer
{
    protected PuckHtmlRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new PuckHtmlRenderer();
    }

    /**
     * Convierte el JSON de Puck data (almacenado en `puck_data`) en HTML.
     *
     * @param array $puckData El array decodificado (el modelo lo tiene como $jsonable)
     * @return string|null HTML resultante, o null si falló
     */
    public function render(array $puckData): ?string
    {
        try {
            $html = $this->renderer->render($puckData);
            return $html !== '' ? $html : null;
        } catch (\Exception $e) {
            \Log::error('Aero\\Sites\\HeadlessRenderer falló: ' . $e->getMessage());
            return null;
        }
    }
}
