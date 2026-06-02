<?php

namespace App\Support;

class DashboardSettings
{
    public const WIDGET_IDS = [
        'alerts',
        'ai_cfo',
        'primary_metrics',
        'secondary_metrics',
        'main_grid',
        'business_chart',
        'ai_briefing',
    ];

    public static function defaults(): array
    {
        return config('dashboard.defaults', []);
    }

    public static function resolve(?array $stored): array
    {
        $defaults = self::defaults();
        $fallback = $defaults['widget_order'] ?? self::WIDGET_IDS;

        if (! is_array($stored)) {
            return ['widget_order' => $fallback];
        }

        $order = [];
        foreach ($stored['widget_order'] ?? [] as $id) {
            $id = (string) $id;
            if (in_array($id, self::WIDGET_IDS, true) && ! in_array($id, $order, true)) {
                $order[] = $id;
            }
        }

        foreach (self::WIDGET_IDS as $id) {
            if (! in_array($id, $order, true)) {
                $order[] = $id;
            }
        }

        return ['widget_order' => $order];
    }
}
