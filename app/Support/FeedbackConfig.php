<?php

namespace App\Support;

class FeedbackConfig
{
    /** @return list<string> */
    public static function categories(): array
    {
        $fromConfig = config('feedback.categories');

        return is_array($fromConfig) && $fromConfig !== []
            ? array_values($fromConfig)
            : ['bug', 'feature', 'improvement', 'question', 'other'];
    }

    /** @return list<string> */
    public static function statuses(): array
    {
        $fromConfig = config('feedback.statuses');

        return is_array($fromConfig) && $fromConfig !== []
            ? array_values($fromConfig)
            : ['new', 'read', 'replied', 'resolved'];
    }

    /** @return array<string, string> */
    public static function legacyCategories(): array
    {
        $fromConfig = config('feedback.legacy_categories');

        return is_array($fromConfig) ? $fromConfig : [
            'suggestion' => 'improvement',
            'wish' => 'feature',
            'missing' => 'feature',
        ];
    }

    /** @return list<string> */
    public static function allowedCategoryInputs(): array
    {
        return array_values(array_unique(array_merge(
            self::categories(),
            array_keys(self::legacyCategories()),
        )));
    }
}
