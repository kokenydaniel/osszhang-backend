<?php

namespace App\Support;

final class StorageDisk
{
    public static function default(): string
    {
        $configured = env('FILESYSTEM_DISK');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        if (self::objectStorageConfigured()) {
            return 's3';
        }

        return 'local';
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
        return env('SUPABASE_STORAGE_ACCESS_KEY') ?: env('AWS_ACCESS_KEY_ID');
    }

    public static function secretKey(): ?string
    {
        return env('SUPABASE_STORAGE_SECRET_KEY') ?: env('AWS_SECRET_ACCESS_KEY');
    }

    public static function bucket(): ?string
    {
        return env('SUPABASE_STORAGE_BUCKET') ?: env('AWS_BUCKET');
    }

    public static function endpoint(): ?string
    {
        return env('SUPABASE_STORAGE_ENDPOINT') ?: env('AWS_ENDPOINT');
    }

    public static function region(): string
    {
        $configured = env('SUPABASE_STORAGE_REGION') ?: env('AWS_DEFAULT_REGION');
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
        $value = env('SUPABASE_STORAGE_USE_PATH_STYLE', env('AWS_USE_PATH_STYLE_ENDPOINT'));

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
}
