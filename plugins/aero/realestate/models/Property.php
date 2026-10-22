<?php namespace Aero\Realestate\Models;

use Model;

/**
 * Inmueble del catálogo. Lo que se ofrece (precio, canon, capital) vive en las
 * ofertas; aquí está lo físico: ubicación, superficies, medios y características.
 */
class Property extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;
    use \Aero\Realestate\Classes\TenantOwned;

    public $table = 'aero_realestate_properties';

    public $fillable = [
        'tenant_id', 'code', 'title', 'slug', 'type', 'description', 'owner_id', 'agent_id', 'location_id', 'address',
        'lat', 'lng', 'land_area', 'built_area', 'bedrooms', 'bathrooms', 'garages', 'floors', 'age_years',
        'video_url', 'status', 'is_featured', 'is_published',
    ];

    public $rules = [
        'title'  => 'required|string|max:255',
        'type'   => 'required|in:house,apartment,land,office,shop,other',
        'status' => 'required|in:available,reserved,sold,rented,anticretico,inactive',
        'lat'    => 'nullable|numeric|between:-90,90',
        'lng'    => 'nullable|numeric|between:-180,180',
    ];

    public $attributes = ['status' => 'available'];

    public $dates = ['published_at'];

    public $belongsTo = [
        'owner'    => [Owner::class, 'key' => 'owner_id'],
        'agent'    => [Agent::class, 'key' => 'agent_id'],
        'location' => [Location::class, 'key' => 'location_id'],
    ];

    public $hasMany = ['offers' => [Offer::class, 'key' => 'property_id']];

    public $belongsToMany = [
        'features' => [Feature::class, 'table' => 'aero_realestate_feature_property', 'key' => 'property_id', 'otherKey' => 'feature_id'],
    ];

    public $attachMany = [
        'photos' => \System\Models\File::class,
        'plans'  => \System\Models\File::class,
    ];

    public static array $types = [
        'house' => 'Casa', 'apartment' => 'Departamento', 'land' => 'Terreno',
        'office' => 'Oficina', 'shop' => 'Local comercial', 'other' => 'Otro',
    ];

    public static array $statuses = [
        'available' => 'Disponible', 'reserved' => 'Reservada', 'sold' => 'Vendida',
        'rented' => 'Alquilada', 'anticretico' => 'En anticrético', 'inactive' => 'Inactiva',
    ];

    public function scopePublished($query)
    {
        return $query->where('is_published', true)->whereNotIn('status', ['inactive']);
    }

    public function getTypeOptions(): array
    {
        return self::$types;
    }

    public function getStatusOptions(): array
    {
        return self::$statuses;
    }

    public function getOwnerIdOptions(): array
    {
        return Owner::visible()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getAgentIdOptions(): array
    {
        return Agent::visible()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getLocationIdOptions(): array
    {
        return Location::available()->whereIn('type', ['city', 'zone', 'neighborhood'])->orderBy('name')->get()
            ->mapWithKeys(fn ($l) => [$l->id => $l->path])->all();
    }

    public function getStatusNameAttribute(): string
    {
        return self::$statuses[$this->status] ?? (string) $this->status;
    }

    public function getTypeNameAttribute(): string
    {
        return self::$types[$this->type] ?? (string) $this->type;
    }

    protected ?array $statusChange = null;

    public function beforeSave(): void
    {
        $this->statusChange = ($this->exists && $this->isDirty('status')) ? ['old' => $this->getOriginal('status'), 'new' => $this->status] : null;

        $this->assertReferencesVisible(['owner_id' => Owner::class, 'agent_id' => Agent::class]);

        // Ubicación desnormalizada: filtros y mapa no dependen de recorrer el árbol.
        if ($this->isDirty('location_id') || !$this->department) {
            $this->department = $this->city = $this->zone = $this->neighborhood = null;
            if ($this->location_id && ($loc = Location::find($this->location_id))) {
                foreach ($loc->chain() as $node) {
                    $this->{$node->type} = $node->name;
                }
            }
        }

        if ($this->is_published && !$this->published_at) {
            $this->published_at = now();
        }
    }

    public function afterCreate(): void
    {
        $updates = [];
        if (!$this->code) {
            $updates['code'] = 'INM-' . str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
        }
        if (!$this->slug) {
            $updates['slug'] = \Str::slug($this->title) . '-' . $this->id;
        }
        if ($updates) {
            static::whereKey($this->id)->update($updates);
            $this->forceFill($updates)->syncOriginal();
        }
    }

    public function afterUpdate(): void
    {
        if ($this->statusChange && $this->is_published) {
            Change::create([
                'tenant_id' => $this->tenant_id, 'property_id' => $this->id, 'kind' => 'status',
                'old_value' => $this->statusChange['old'], 'new_value' => $this->statusChange['new'], 'created_at' => now(),
            ]);
        }
        $this->statusChange = null;
    }
}
