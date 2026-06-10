<?php namespace Aero\Sites\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SiteResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'name'          => $this->name,
            'handle'        => $this->handle,
            'niche'         => $this->niche_type,
            'status'        => $this->status,
            'primary_color' => $this->primary_color,
            'logo_url'      => $this->logo?->getPath(),
            'favicon_url'   => $this->favicon?->getPath(),
            'seo'           => $this->seoConfig ? [
                'title_format'        => $this->seoConfig->title_format,
                'default_description' => $this->seoConfig->default_description,
                'og_image_url'        => $this->seoConfig->og_image?->getPath(),
                'google_analytics_id' => $this->seoConfig->google_analytics_id,
                'sitemap_enabled'     => (bool) $this->seoConfig->sitemap_enabled,
            ] : null,
            'contact' => $this->contactConfig ? [
                'email'       => $this->contactConfig->contact_email,
                'phone'       => $this->contactConfig->phone,
                'whatsapp'    => $this->contactConfig->whatsapp,
                'address'     => $this->contactConfig->address,
                'lat'         => $this->contactConfig->lat,
                'lng'         => $this->contactConfig->lng,
                'form_enabled' => (bool) $this->contactConfig->form_enabled,
            ] : null,
        ];
    }
}
