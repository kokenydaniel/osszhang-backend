<?php

namespace App\Http\Resources;

use App\Models\SystemAnnouncement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SystemAnnouncementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'message' => $this->message,
            'type' => $this->type,
            'is_active' => (bool) $this->is_active,
            'isActive' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
