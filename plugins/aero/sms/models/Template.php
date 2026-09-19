<?php namespace Aero\Sms\Models;

use Model;

class Template extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_sms_templates';

    public $fillable = ['name', 'slug', 'body', 'is_active'];

    public $rules = [
        'name' => 'required',
        'slug' => 'required|alpha_dash|unique:aero_sms_templates,slug',
        'body' => 'required',
    ];

    protected $casts = ['is_active' => 'boolean'];

    /** Reemplaza {{variable}} por su valor; las que no vienen se dejan vacías. */
    public static function render(string $body, array $vars = []): string
    {
        return preg_replace_callback('/\{\{\s*([\w.]+)\s*\}\}/', function ($m) use ($vars) {
            $value = $vars[$m[1]] ?? '';

            return is_scalar($value) ? (string) $value : '';
        }, $body);
    }
}
