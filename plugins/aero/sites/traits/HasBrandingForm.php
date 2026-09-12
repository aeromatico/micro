<?php namespace Aero\Sites\Traits;

use Aero\Sites\Models\Tenant;
use Backend\Widgets\Form;
use Flash;

/**
 * Form de Branding (identidad visual: logo, paleta, tipografía) — vive en un
 * trait porque tanto ContentEditor (ahí es donde el usuario ve el resultado
 * en vivo mientras diseña) como SiteSettings (donde vivía antes) lo montan
 * sobre el mismo Tenant. Requiere que la clase que lo usa tenga
 * `getCurrentTenant()` (ver ResolvesCurrentTenant) y sea un Backend\Classes\Controller
 * (usa $this->makeWidget()/$this->pageTitle-less context).
 */
trait HasBrandingForm
{
    public ?Form $brandingWidget = null;

    protected function makeBrandingWidget(Tenant $tenant): Form
    {
        // Atributos virtuales para precargar el form con el override actual
        // (theme_overrides.colors.*/fonts.*); no se guardan directo,
        // onSaveBranding() los recompone en theme_overrides (y los descarta
        // del modelo antes de save(), ver ahí el porqué).
        $colorOverrides = $tenant->theme_overrides['colors'] ?? [];
        $tenant->override_primary = $colorOverrides['primary'] ?? null;
        $tenant->override_accent  = $colorOverrides['accent'] ?? null;

        $fontOverrides = $tenant->theme_overrides['fonts'] ?? [];
        $tenant->override_font_heading  = $fontOverrides['heading'] ?? null;
        $tenant->override_font_heading2 = $fontOverrides['heading2'] ?? null;
        $tenant->override_font_body     = $fontOverrides['body'] ?? null;

        $config            = new \stdClass;
        $config->model     = $tenant;
        $config->arrayName = 'Branding';
        $config->alias     = 'brandingForm';
        $config->fields    = [
            'is_site_active' => [
                'label'   => 'Sitio activado',
                'type'    => 'switch',
                'span'    => 'full',
                'comment' => 'Si se desactiva, el sitio deja de mostrarse a los visitantes y el menú "Sitio Web" se oculta en este panel.',
            ],
            'name' => [
                'label'    => 'Nombre del sitio',
                'type'     => 'text',
                'required' => true,
                'span'     => 'left',
            ],
            'primary_color' => [
                'label'       => 'Color principal (legacy)',
                'type'        => 'text',
                'span'        => 'right',
                'placeholder' => '#3b82f6',
                'comment'     => 'Solo se usa si no hay un tema visual asignado abajo.',
            ],
            'logo' => [
                'label'       => 'Logo',
                'type'        => 'fileupload',
                'mode'        => 'image',
                'imageWidth'  => 400,
                'imageHeight' => 200,
                'span'        => 'left',
                'comment'     => 'Si subís una imagen, reemplaza al logo de texto de abajo.',
            ],
            'favicon' => [
                'label'       => 'Favicon',
                'type'        => 'fileupload',
                'mode'        => 'image',
                'imageWidth'  => 64,
                'imageHeight' => 64,
                'span'        => 'right',
                'comment'     => 'Recomendado: 32×32 o 64×64 px',
            ],
            'logo_text' => [
                'label'       => 'Logo de texto (opcional)',
                'type'        => 'text',
                'span'        => 'left',
                'placeholder' => $tenant->name,
                'comment'     => 'Se muestra en el header/footer solo si no hay logo de imagen. Vacío = usa el nombre del sitio.',
            ],
            'logo_text_font' => [
                'label'       => 'Fuente del logo de texto',
                'type'        => 'text',
                'span'        => 'right',
                'placeholder' => 'Ej: Playfair Display',
                'comment'     => 'Nombre exacto de Google Fonts. Vacío = usa la fuente de encabezado del tema.',
            ],
            '_palette' => [
                'label' => 'Paleta de colores',
                'type'  => 'section',
                'span'  => 'full',
            ],
            'design_theme_id' => [
                'label'   => 'Tema visual',
                'type'    => 'partial',
                // Ruta absoluta: esta partial vive físicamente en
                // controllers/sitesettings/ (donde vivía originalmente el
                // form de Branding) — un path relativo se resolvería contra
                // el controlador que en cada caso renderiza el form
                // (SiteSettings o ContentEditor, ambos usan este trait).
                'path'    => '$/aero/sites/controllers/sitesettings/_theme_gallery',
                'span'    => 'full',
                'comment' => 'Elegí un tema como punto de partida. Los temas son de solo lectura — para personalizar colores o tipografía usá los campos de abajo.',
            ],
            'override_primary' => [
                'label'       => 'Personalizar color primario (opcional)',
                'type'        => 'colorpicker',
                'span'        => 'left',
                'comment'     => 'Sobreescribe el primario del tema elegido, en ambos modos.',
            ],
            'override_accent' => [
                'label'       => 'Personalizar color de acento (opcional)',
                'type'        => 'colorpicker',
                'span'        => 'right',
                'comment'     => 'Sobreescribe el acento del tema elegido, en ambos modos.',
            ],
            '_typography' => [
                'label' => 'Tipografía',
                'type'  => 'section',
                'span'  => 'full',
            ],
            'override_font_heading' => [
                'label'       => 'Fuente de encabezado principal (H1)',
                'type'        => 'text',
                'span'        => 'left',
                'placeholder' => 'Ej: Plus Jakarta Sans',
                'comment'     => 'Nombre exacto de Google Fonts. Vacío = usa la del tema visual.',
            ],
            'override_font_heading2' => [
                'label'       => 'Fuente de subtítulos (H2-H6)',
                'type'        => 'text',
                'span'        => 'right',
                'placeholder' => 'Ej: Plus Jakarta Sans',
                'comment'     => 'Nombre exacto de Google Fonts. Vacío = usa la del tema visual.',
            ],
            'override_font_body' => [
                'label'       => 'Fuente de texto',
                'type'        => 'text',
                'span'        => 'left',
                'placeholder' => 'Ej: Inter',
                'comment'     => 'Nombre exacto de Google Fonts. Vacío = usa la del tema visual.',
            ],
        ];

        $widget = $this->makeWidget(Form::class, $config);
        $widget->bindToController();
        return $widget;
    }

    public function onSaveBranding()
    {
        $tenant = $this->getCurrentTenant();
        $data   = post('Branding', []);

        $tenant->name            = $data['name']            ?? $tenant->name;
        $tenant->primary_color   = $data['primary_color']   ?? $tenant->primary_color;
        $tenant->logo_text       = trim((string) ($data['logo_text'] ?? '')) ?: null;
        $tenant->logo_text_font  = trim((string) ($data['logo_text_font'] ?? '')) ?: null;
        $tenant->design_theme_id = $data['design_theme_id'] ?: null;
        $tenant->is_site_active  = (bool) ($data['is_site_active'] ?? false);

        $overridePrimary = trim((string) ($data['override_primary'] ?? ''));
        $overrideAccent  = trim((string) ($data['override_accent'] ?? ''));
        $colorOverrides  = array_filter([
            'primary' => $overridePrimary ?: null,
            'accent'  => $overrideAccent ?: null,
        ]);

        $overrideFontHeading  = trim((string) ($data['override_font_heading'] ?? ''));
        $overrideFontHeading2 = trim((string) ($data['override_font_heading2'] ?? ''));
        $overrideFontBody     = trim((string) ($data['override_font_body'] ?? ''));
        $fontOverrides = array_filter([
            'heading'  => $overrideFontHeading ?: null,
            'heading2' => $overrideFontHeading2 ?: null,
            'body'     => $overrideFontBody ?: null,
        ]);

        $overrides = [];
        if ($colorOverrides) $overrides['colors'] = $colorOverrides;
        if ($fontOverrides) $overrides['fonts'] = $fontOverrides;
        $tenant->theme_overrides = $overrides ?: null;

        // makeBrandingWidget() (ejecutado por index() antes de este handler AJAX,
        // sobre la misma instancia cacheada por ResolvesCurrentTenant) setea estos
        // atributos virtuales para precargar el form — no son columnas reales,
        // hay que descartarlos antes de save() o Eloquent intenta persistirlos.
        unset(
            $tenant->override_primary, $tenant->override_accent,
            $tenant->override_font_heading, $tenant->override_font_heading2, $tenant->override_font_body
        );

        $tenant->save();

        // Commit deferred file bindings (logo, favicon)
        $sessionKey = post('_session_key', '');
        if ($sessionKey) {
            $tenant->commitDeferred($sessionKey);
        }

        Flash::success('Branding guardado correctamente.');
        return [];
    }
}
