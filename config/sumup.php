<?php

return [
    'api_base_url' => env('SUMUP_API_BASE_URL', 'https://api.sumup.com'),
    /** Max receipt PDF blocks per import (API call limit). */
    'max_receipts_per_import' => (int) env('SUMUP_MAX_RECEIPTS_PER_IMPORT', 200),
];
