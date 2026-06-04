<?php

return [
    'categories' => ['bug', 'feature', 'improvement', 'question', 'other'],
    'legacy_categories' => [
        'suggestion' => 'improvement',
        'wish' => 'feature',
        'missing' => 'feature',
    ],
    'statuses' => ['new', 'read', 'replied', 'resolved'],
    'max_message_length' => 5000,
    'max_subject_length' => 200,
    'max_files' => 5,
    'attachment_max_kb' => 10240,
    'attachment_mimes' => ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif'],
];
