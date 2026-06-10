<?php namespace Aero\Sites\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'title'            => $this->title,
            'slug'             => $this->slug,
            'content'          => $this->content,
            'meta_title'       => $this->effective_meta_title,
            'meta_description' => $this->meta_description,
            'og_image_url'     => $this->og_image?->getPath(),
            'layout'           => $this->layout,
            'sort_order'       => $this->sort_order,
            'updated_at'       => $this->updated_at?->toIso8601String(),
        ];
    }
}
