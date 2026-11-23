<?php namespace Aero\Social\Models;

use Aero\Social\Classes\Credentials;
use Aero\Social\Classes\CurrentTenant;
use Aero\Social\Classes\Networks;
use Model;

/**
 * Llaves de una app de desarrollador PROPIA del tenant para una red (opcional).
 * Sin ella se usa la app de Market. El secreto se guarda cifrado.
 */
class App extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Encryptable;

    public $table = 'aero_social_apps';

    protected $encryptable = ['client_secret'];

    protected $hidden = ['client_secret'];

    protected $fillable = ['network', 'client_id', 'client_secret'];

    public $rules = [
        'network'       => 'required|in:youtube,x,tiktok',
        'client_id'     => 'required|max:255',
        'client_secret' => 'required',
    ];

    public $attributeNames = [
        'network' => 'red', 'client_id' => 'ID de cliente', 'client_secret' => 'secreto',
    ];

    public function beforeValidate(): void
    {
        $this->tenant_id ??= CurrentTenant::id();

        $dup = static::where('tenant_id', $this->tenant_id)->where('network', $this->network)
            ->when($this->exists, fn ($q) => $q->where('id', '!=', $this->id))->exists();
        if ($dup) {
            throw new \ValidationException(['network' => 'Ya tienes llaves para esa red; edita las existentes.']);
        }
    }

    public function getNetworkOptions(): array
    {
        return collect(Credentials::BYO)->mapWithKeys(fn ($n) => [$n => Networks::label($n)])->all();
    }

    public function getNetworkLabelAttribute(): string
    {
        return Networks::label((string) $this->network);
    }
}
