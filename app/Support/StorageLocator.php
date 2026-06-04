<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

final class StorageLocator
{
    public static function forPath(string $storedDisk, string $path): Filesystem
    {
        foreach (self::candidateDisks($storedDisk) as $name) {
            if (self::safeReadable($name, $path)) {
                return Storage::disk($name);
            }
        }

        return Storage::disk($storedDisk);
    }

    public static function exists(string $storedDisk, string $path): bool
    {
        return self::read($storedDisk, $path) !== null;
    }

    public static function read(string $storedDisk, string $path): ?string
    {
        foreach (self::candidateDisks($storedDisk) as $name) {
            $contents = self::safeGet($name, $path);
            if ($contents !== null) {
                return $contents;
            }
        }

        return null;
    }

    private static function safeReadable(string $diskName, string $path): bool
    {
        return self::safeGet($diskName, $path) !== null;
    }

    private static function safeGet(string $diskName, string $path): ?string
    {
        try {
            if (Storage::disk($diskName)->exists($path)) {
                $contents = Storage::disk($diskName)->get($path);

                return is_string($contents) && $contents !== '' ? $contents : null;
            }
        } catch (\Throwable) {
        }

        try {
            $contents = Storage::disk($diskName)->get($path);

            return is_string($contents) && $contents !== '' ? $contents : null;
        } catch (\Throwable) {
            return null;
        }
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
