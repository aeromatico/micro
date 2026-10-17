<?php namespace Aero\Help\Models;

use Model;

/**
 * Flujo guiado: lista de pasos (JSON). Cada paso tiene `key`, `type` y, según
 * el tipo, texto, opciones (con el paso al que saltan) o una referencia a una
 * guía, un artículo, un video o un enlace. Se valida al guardar: un flujo con
 * un salto a un paso inexistente dejaría a la persona sin salida.
 */
class Flow extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_help_flows';

    public $fillable = ['slug', 'title', 'plugin', 'intro', 'steps', 'is_published', 'version'];

    public $rules = [
        'title' => 'required|max:255',
        'slug'  => 'required|alpha_dash|max:120|unique:aero_help_flows,slug',
    ];

    public $jsonable = ['steps'];

    protected $casts = ['is_published' => 'boolean'];

    public const TYPES = [
        'message' => 'Mensaje',
        'choice'  => 'Pregunta con botones',
        'ask'     => 'Pregunta abierta',
        'guide'   => 'Guía interactiva',
        'doc'     => 'Artículo de documentación',
        'video'   => 'Video del servicio',
        'link'    => 'Enlace o pantalla',
        'done'    => 'Final (¿se resolvió?)',
    ];

    public function getPluginOptions(): array
    {
        return ['' => '— Ninguno —'] + collect(\System\Classes\PluginManager::instance()->getPlugins())
            ->keys()->filter(fn ($code) => str_starts_with((string) $code, 'Aero.'))
            ->mapWithKeys(fn ($code) => [strtolower(substr($code, 5)) => $code])->all();
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function beforeSave(): void
    {
        $steps = array_values((array) $this->steps);
        $keys = [];

        foreach ($steps as $i => $step) {
            $key = trim((string) ($step['key'] ?? ''));
            $label = 'Paso ' . ($i + 1) . ($key !== '' ? " («{$key}»)" : '');

            if (!preg_match('/^[a-z0-9_]{1,40}$/', $key)) {
                throw new \ApplicationException("{$label}: la clave debe tener minúsculas, números o guion bajo (máx. 40).");
            }
            if (isset($keys[$key])) {
                throw new \ApplicationException("{$label}: la clave está repetida.");
            }
            if (!isset(self::TYPES[$step['type'] ?? ''])) {
                throw new \ApplicationException("{$label}: elige el tipo de paso.");
            }
            $keys[$key] = true;
        }

        foreach ($steps as $i => $step) {
            $label = 'Paso ' . ($i + 1) . " («{$step['key']}»)";

            foreach (array_filter([$step['next'] ?? null]) as $target) {
                if (!isset($keys[$target])) {
                    throw new \ApplicationException("{$label}: el siguiente paso «{$target}» no existe.");
                }
            }

            if (($step['type'] ?? '') === 'choice') {
                $options = (array) ($step['options'] ?? []);
                if (!$options) {
                    throw new \ApplicationException("{$label}: una pregunta con botones necesita opciones.");
                }
                foreach ($options as $option) {
                    if (trim((string) ($option['label'] ?? '')) === '' || trim((string) ($option['value'] ?? '')) === '') {
                        throw new \ApplicationException("{$label}: cada opción necesita texto y valor.");
                    }
                    if (!empty($option['next']) && !isset($keys[$option['next']])) {
                        throw new \ApplicationException("{$label}: la opción «{$option['label']}» salta a un paso que no existe.");
                    }
                }
            }
        }

        if ($this->exists && $this->isDirty('steps')) {
            $this->version = (int) $this->version + 1;
        }
    }
}
