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

        if (
            filled(env('AWS_BUCKET'))
            && filled(env('AWS_ACCESS_KEY_ID'))
            && filled(env('AWS_SECRET_ACCESS_KEY'))
        ) {
            return 's3';
        }

        return 'local';
    }
}
