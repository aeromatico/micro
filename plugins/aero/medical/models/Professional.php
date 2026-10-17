<?php namespace Aero\Medical\Models;

use Aero\Medical\Classes\TenantOwned;
use Model;

/**
 * Datos de firma de un profesional del negocio. La firma y el sello son
 * imágenes en disco PRIVADO (nunca una URL pública). No es firma electrónica
 * certificada: sirve para la impresión; la inmutabilidad la da la nota firmada.
 */
class Professional extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use TenantOwned;

    public $table = 'aero_medical_professionals';

    public $fillable = ['tenant_id', 'user_id', 'display_name', 'title', 'license', 'specialty'];

    protected $hidden = ['signature_image', 'seal_image'];

    public $rules = [
        'display_name' => 'required|string|max:255',
        'title'        => 'nullable|string|max:32',
        'license'      => 'nullable|string|max:64',
        'specialty'    => 'nullable|string|max:128',
    ];

    /** Imágenes cifradas en la BD (data URI): nunca archivos ni URL. */
    public $casts = [
        'signature_image' => 'encrypted',
        'seal_image'      => 'encrypted',
    ];

    public const IMAGE_FIELDS = ['signature' => 'signature_image', 'seal' => 'seal_image'];
    public const MAX_IMAGE_BYTES = 400 * 1024;

    /** Perfil del usuario en el tenant; si no existe lo crea con su nombre (se completa en «Mi firma»). */
    public static function forUser(int $tenantId, $user): self
    {
        $name = trim("{$user->first_name} {$user->last_name}") ?: (string) $user->email;

        return static::firstOrCreate(
            ['tenant_id' => $tenantId, 'user_id' => $user->id],
            ['display_name' => $name]
        );
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->title ? $this->title . ' ' : '') . $this->display_name);
    }

    /** Imagen para la impresión (data URI) o null. */
    public function imageDataUri(string $which): ?string
    {
        $col = self::IMAGE_FIELDS[$which] ?? null;

        return $col && $this->{$col} ? (string) $this->{$col} : null;
    }

    /** Guarda una imagen subida (PNG/JPG, tamaño acotado) cifrada. Lanza ApplicationException si no es válida. */
    public function storeImage(string $which, \Illuminate\Http\UploadedFile $file): void
    {
        $col = self::IMAGE_FIELDS[$which] ?? null;
        if (!$col) {
            throw new \ApplicationException('Imagen no válida.');
        }

        $path = $file->getRealPath();
        $info = $path ? @getimagesize($path) : false;
        $mime = $info['mime'] ?? null;

        if (!$info || !in_array($mime, ['image/png', 'image/jpeg'], true)) {
            throw new \ApplicationException('Suba una imagen PNG o JPG.');
        }
        if ($file->getSize() > self::MAX_IMAGE_BYTES) {
            throw new \ApplicationException('La imagen pesa demasiado (máximo 400 KB).');
        }
        if ($info[0] > 2000 || $info[1] > 2000) {
            throw new \ApplicationException('La imagen es demasiado grande (máximo 2000 px por lado).');
        }

        $this->{$col} = 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($path));
        $this->save();
    }

    public function removeImage(string $which): void
    {
        if ($col = self::IMAGE_FIELDS[$which] ?? null) {
            $this->{$col} = null;
            $this->save();
        }
    }
}
