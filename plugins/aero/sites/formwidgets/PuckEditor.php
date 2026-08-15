<?php namespace Aero\Sites\FormWidgets;

use Backend\Classes\FormWidgetBase;

class PuckEditor extends FormWidgetBase
{
    protected $defaultAlias = 'puckeditor';

    public function render(): string
    {
        $this->prepareVars();
        return $this->makePartial('puckeditor');
    }

    protected function prepareVars(): void
    {
        $value = $this->getLoadValue();

        // $jsonable decoded it to array; re-encode for JS
        $puckJson = null;
        if ($value) {
            $puckJson = is_array($value)
                ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG)
                : (string) $value;
        }

        $this->vars['puckJson']      = $puckJson;
        $this->vars['contentValue']  = $this->model->content ?? '';
        $this->vars['puckDataName']  = $this->getFieldName();
        $this->vars['contentName']   = str_replace('[puck_data]', '[content]', $this->getFieldName());
        $this->vars['editorId']      = $this->getId('mount');
        $this->vars['puckDataId']    = $this->getId('puck-data');
        $this->vars['contentId']     = $this->getId('content');

        // El preview de Puck se monta sin iframe (iframe.enabled: false en
        // index.jsx) directo al DOM del backend, así que comparte <head>/DOM
        // con esta página — basta inyectar las CSS vars del tenant (variante
        // 'light', el editor no tiene toggle de modo oscuro) scopeadas al
        // contenedor del mount, más el <link> de Google Fonts real, para que
        // el editor se vea igual que el sitio publicado.
        $tenant = $this->model->tenant ?? null;
        $this->vars['tenantCssVars']   = $tenant ? ($tenant->getEffectiveCssVars()['light'] ?? null) : null;
        $this->vars['googleFontsUrl']  = $tenant ? $tenant->getGoogleFontsUrl() : null;
    }

    public function getSaveValue($value): mixed
    {
        // Value arrives as JSON string from the hidden textarea.
        // The model's $jsonable will handle decode on read.
        return $value ?: null;
    }

    protected function loadAssets(): void
    {
        $version = '?v=' . hash('crc32', (string) filemtime(__DIR__ . '/puckeditor/assets/puck-editor.js'));

        $this->addCss('puck-editor.css' . $version);
        $this->addCss('puck-editor-theme.css' . $version);
        $this->addJs('puck-editor.js' . $version);
    }
}
