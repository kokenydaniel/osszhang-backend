<?php

namespace App\Support;

final class StorageDisk
{
    public static function default(): string
    {
        if (! self::objectStorageConfigured()) {
            $configured = self::readEnv('FILESYSTEM_DISK');

            return is_string($configured) && $configured !== '' ? $configured : 'local';
        }

        $configured = self::readEnv('FILESYSTEM_DISK');

        return is_string($configured) && $configured !== '' ? $configured : 's3';
    }

    public static function objectStorageConfigured(): bool
    {
        return filled(self::accessKey())
            && filled(self::secretKey())
            && filled(self::bucket())
            && filled(self::endpoint());
    }

    public static function accessKey(): ?string
    {
        return self::readEnv('SUPABASE_STORAGE_ACCESS_KEY') ?: self::readEnv('AWS_ACCESS_KEY_ID');
    }

    public static function secretKey(): ?string
    {
        return self::readEnv('SUPABASE_STORAGE_SECRET_KEY') ?: self::readEnv('AWS_SECRET_ACCESS_KEY');
    }

    public static function bucket(): ?string
    {
        return self::readEnv('SUPABASE_STORAGE_BUCKET') ?: self::readEnv('AWS_BUCKET');
    }

    public static function endpoint(): ?string
    {
        return self::readEnv('SUPABASE_STORAGE_ENDPOINT') ?: self::readEnv('AWS_ENDPOINT');
    }

    public static function region(): string
    {
        $configured = self::readEnv('SUPABASE_STORAGE_REGION') ?: self::readEnv('AWS_DEFAULT_REGION');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        if (str_contains((string) self::endpoint(), 'supabase.co')) {
            return 'eu-west-1';
        }

        return 'eu-central-1';
    }

    public static function usePathStyleEndpoint(): bool
    {
        $value = self::readEnv('SUPABASE_STORAGE_USE_PATH_STYLE') ?? self::readEnv('AWS_USE_PATH_STYLE_ENDPOINT');

        if ($value === null) {
            return str_contains((string) self::endpoint(), 'supabase.co');
        }

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    public static function applyRuntimeConfig(): void
    {
        if (! self::objectStorageConfigured()) {
            return;
        }

        config([
            'filesystems.disks.s3.driver' => 's3',
            'filesystems.disks.s3.key' => self::accessKey(),
            'filesystems.disks.s3.secret' => self::secretKey(),
            'filesystems.disks.s3.bucket' => self::bucket(),
            'filesystems.disks.s3.region' => self::region(),
            'filesystems.disks.s3.endpoint' => self::endpoint(),
            'filesystems.disks.s3.use_path_style_endpoint' => self::usePathStyleEndpoint(),
            'filesystems.disks.s3.request_checksum_calculation' => 'when_required',
            'filesystems.disks.s3.response_checksum_validation' => 'when_required',
            'filesystems.disks.s3.throw' => true,
        ]);
    }

    private static function readEnv(string $key): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
