<?php

namespace App\Support;

use App\Models\User;

final class Username
{
    public static function normalize(string $value): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9_]/', '', strtolower(trim($value))));
    }

    public static function fromEmail(string $email): string
    {
        $local = explode('@', strtolower(trim($email)), 2)[0] ?? 'user';
        $base = self::normalize($local);

        return $base !== '' ? substr($base, 0, 32) : 'user';
    }

    public static function uniqueFromBase(string $base, ?int $exceptUserId = null): string
    {
        $candidate = substr($base !== '' ? $base : 'user', 0, 32);
        $suffix = 1;

        while (self::exists($candidate, $exceptUserId)) {
            $suffix++;
            $tail = '_'.$suffix;
            $candidate = substr($base, 0, 32 - strlen($tail)).$tail;
        }

        return $candidate;
    }

    public static function exists(string $username, ?int $exceptUserId = null): bool
    {
        $query = User::query()->where('username', $username);
        if ($exceptUserId !== null) {
            $query->where('id', '!=', $exceptUserId);
        }

        return $query->exists();
    }
}
