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
            $disk = Storage::disk($diskName);
            if (! $disk->exists($path)) {
                return null;
            }

            $contents = $disk->get($path);
            if (! is_string($contents) || $contents === '') {
                return null;
            }

            $objectSize = $disk->size($path);
            if (is_int($objectSize) && $objectSize > 0 && strlen($contents) !== $objectSize) {
                return null;
            }

            return $contents;
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
