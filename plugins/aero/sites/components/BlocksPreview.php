<?php namespace Aero\Sites\Components;

use Aero\Sites\Classes\ComponentBlockCatalog;
use Cms\Classes\ComponentBase;
use Response;

/**
 * Target público de <iframe> para la galería de bloques del theme `master`
 * (/elements/blocks) — equivalente frontend de
 * ComponentGallery::preview() del backend. Devuelve el mismo documento
 * standalone (mismo HeadlessRenderer/PuckHtmlRenderer + CSS de
 * themes/microsites) para que ambas galerías y el editor Puck no puedan
 * mostrar algo distinto entre sí. Sin datos sensibles: solo defaultProps de
 * muestra, por eso no requiere sesión de backend.
 */
class BlocksPreview extends ComponentBase
{
    public function componentDetails(): array
    {
        return [
            'name'        => 'Preview de bloque (Puck)',
            'description' => 'Devuelve el HTML standalone de un bloque/variante — usado como src de iframe.',
        ];
    }

    public function onRun()
    {
        $block = (string) (input('block') ?: 'Hero');

        if (!array_key_exists($block, ComponentBlockCatalog::BLOCKS)) {
            $block = 'Hero';
        }

        $variants = ComponentBlockCatalog::BLOCKS[$block]['variants'];
        $variant = (string) (input('variant') ?: array_key_first($variants));
        if (!array_key_exists($variant, $variants)) {
            $variant = array_key_first($variants);
        }

        $themeHandle = input('theme');
        $mode = input('mode') === 'dark' ? 'dark' : 'light';

        $propsOverride = null;
        $rawOverride = input('props');
        if (is_string($rawOverride) && $rawOverride !== '') {
            $decoded = json_decode($rawOverride, true);
            if (is_array($decoded)) {
                $propsOverride = $decoded;
            }
        }

        $document = ComponentBlockCatalog::renderPreviewDocument($block, $variant, $themeHandle, $mode, $propsOverride);

        return Response::make($document, 200)->header('Content-Type', 'text/html; charset=utf-8');
    }
}
