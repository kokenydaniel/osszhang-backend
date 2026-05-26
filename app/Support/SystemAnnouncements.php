<?php

namespace App\Support;

use App\Models\SystemAnnouncement;
use Illuminate\Support\Facades\Cache;

class SystemAnnouncements
{
    private const CACHE_KEY = 'system_announcements.active';

    public static function active(): ?array
    {
        return Cache::remember(self::CACHE_KEY, 60, function (): ?array {
            $announcement = SystemAnnouncement::query()
                ->where('is_active', true)
                ->latest('updated_at')
                ->first();

            if ($announcement === null) {
                return null;
            }

            return (new \App\Http\Resources\SystemAnnouncementResource($announcement))->resolve();
        });
    }

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
