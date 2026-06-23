<?php

namespace App\Support;

final class PlatformModules
{

    public static function moduleIds(): array
    {
        return AccessControl::MODULES;
    }

    public static function flagKey(string $moduleId): string
    {
        return "enable_module_{$moduleId}";
    }

    public static function defaultReleased(string $moduleId): bool
    {
        return $moduleId === 'budget';
    }

    public static function isReleased(string $moduleId): bool
    {
        if ($moduleId === 'budget') {
            return true;
        }

        if (! in_array($moduleId, self::moduleIds(), true)) {
            return false;
        }

        return FeatureFlags::isEnabled(self::flagKey($moduleId), self::defaultReleased($moduleId));
    }

    public static function releaseBlockedMessage(string $moduleId): string
    {
        $labels = [
            'savings' => 'Megtakarítás',
            'debts' => 'Tartozások',
            'utilities' => 'Rezsi',
            'meters' => 'Közműórák',
            'business' => 'Vállalkozás',
            'pocket_money' => 'Zsebpénz',
            'insurance' => 'Biztosítások',
            'rental' => 'Bérbeadás',
            'receivables' => 'Kintlévőség',
            'travel_planner' => 'Utazástervező',
        ];

        $label = $labels[$moduleId] ?? $moduleId;

        return "A(z) {$label} modul még nem érhető el — hamarosan.";
    }
}
