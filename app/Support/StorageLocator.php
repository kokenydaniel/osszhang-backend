<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

final class StorageLocator
{
    public static function forPath(string $storedDisk, string $path): Filesystem
    {
        foreach (self::candidateDisks($storedDisk) as $name) {
            $disk = Storage::disk($name);
            if ($disk->exists($path)) {
                return $disk;
            }
        }

        return Storage::disk($storedDisk);
    }

    public static function exists(string $storedDisk, string $path): bool
    {
        foreach (self::candidateDisks($storedDisk) as $name) {
            if (Storage::disk($name)->exists($path)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private static function candidateDisks(string $storedDisk): array
    {
        $candidates = [$storedDisk];

        if (StorageDisk::objectStorageConfigured()) {
            $candidates[] = StorageDisk::default();
        }

        $candidates[] = 'local';
        $candidates[] = 's3';

        return array_values(array_unique($candidates));
    }
}
