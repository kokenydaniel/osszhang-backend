<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

final class StorageLocator
{
    public static function forPath(string $storedDisk, string $path): Filesystem
    {
        foreach (self::candidateDisks($storedDisk) as $name) {
            if (self::safeExists($name, $path)) {
                return Storage::disk($name);
            }
        }

        return Storage::disk($storedDisk);
    }

    public static function exists(string $storedDisk, string $path): bool
    {
        foreach (self::candidateDisks($storedDisk) as $name) {
            if (self::safeExists($name, $path)) {
                return true;
            }
        }

        return false;
    }

    private static function safeExists(string $diskName, string $path): bool
    {
        try {
            return Storage::disk($diskName)->exists($path);
        } catch (\Throwable) {
            return false;
        }
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
