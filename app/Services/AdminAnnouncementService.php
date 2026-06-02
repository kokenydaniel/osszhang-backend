<?php

namespace App\Services;

use App\Http\Resources\SystemAnnouncementResource;
use App\Models\SystemAnnouncement;
use App\Support\SystemAnnouncements;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminAnnouncementService
{
    /** @return Collection<int, SystemAnnouncement> */
    public function listAnnouncements(): Collection
    {
        return SystemAnnouncement::query()
            ->orderByDesc('created_at')
            ->get();
    }

    public function createAnnouncement(string $message, string $type): array
    {
        $announcement = SystemAnnouncement::query()->create([
            'message' => $message,
            'type' => $type,
            'is_active' => false,
        ]);

        return (new SystemAnnouncementResource($announcement))->resolve();
    }

    public function updateAnnouncement(SystemAnnouncement $announcement, string $message, string $type): array
    {
        $this->validateType($type);

        $announcement->update([
            'message' => $message,
            'type' => $type,
        ]);

        if ($announcement->is_active) {
            SystemAnnouncements::clearCache();
        }

        return (new SystemAnnouncementResource($announcement->fresh()))->resolve();
    }

    public function deleteAnnouncement(SystemAnnouncement $announcement): void
    {
        $wasActive = $announcement->is_active;
        $announcement->delete();

        if ($wasActive) {
            SystemAnnouncements::clearCache();
        }
    }

    public function toggleActive(SystemAnnouncement $announcement): array
    {
        return DB::transaction(function () use ($announcement): array {
            $announcement->refresh();

            if ($announcement->is_active) {
                $announcement->update(['is_active' => false]);
            } else {
                SystemAnnouncement::query()
                    ->where('is_active', true)
                    ->update(['is_active' => false]);

                $announcement->update(['is_active' => true]);
            }

            SystemAnnouncements::clearCache();

            return (new SystemAnnouncementResource($announcement->fresh()))->resolve();
        });
    }

    public function validateType(string $type): void
    {
        if (! in_array($type, ['info', 'warning', 'danger'], true)) {
            throw ValidationException::withMessages([
                'type' => ['Érvénytelen üzenettípus.'],
            ]);
        }
    }
}
