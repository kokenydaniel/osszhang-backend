<?php

return [
    'defaults' => [
        'default_location' => 'Otthon',
        'units' => ['kWh', 'm³', 'GJ'],
        'templates' => [
            ['name' => 'Villany', 'unit' => 'kWh', 'location' => 'Otthon'],
            ['name' => 'Víz', 'unit' => 'm³', 'location' => 'Otthon'],
            ['name' => 'Gáz', 'unit' => 'm³', 'location' => 'Otthon'],
        ],
        'reading_reminder_day' => 5,
        'consumption_alert_percent' => 25,
        'show_annual_summary_on_dashboard' => true,
        'location_groups' => [
            ['name' => 'Otthon', 'locations' => ['Otthon']],
        ],
    ],
];
