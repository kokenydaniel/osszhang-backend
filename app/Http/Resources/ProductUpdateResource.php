<?php

namespace App\Http\Resources;

use App\Models\ProductUpdate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProductUpdate */
class ProductUpdateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'body' => $this->body,
            'bullets' => $this->bullets ?? [],
            'location_hint' => $this->location_hint,
            'locationHint' => $this->location_hint,
            'kind' => $this->kind,
            'module_id' => $this->module_id,
            'moduleId' => $this->module_id,
            'required_tier' => $this->required_tier,
            'requiredTier' => $this->required_tier,
            'audience_role' => $this->audience_role,
            'audienceRole' => $this->audience_role,
            'cta_label' => $this->cta_label,
            'ctaLabel' => $this->cta_label,
            'cta_href' => $this->cta_href,
            'ctaHref' => $this->cta_href,
            'hero_icon' => $this->hero_icon,
            'heroIcon' => $this->hero_icon,
            'is_active' => (bool) $this->is_active,
            'isActive' => (bool) $this->is_active,
            'published_at' => $this->published_at?->toIso8601String(),
            'publishedAt' => $this->published_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'expiresAt' => $this->expires_at?->toIso8601String(),
            'priority' => (int) $this->priority,
            'created_at' => $this->created_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
