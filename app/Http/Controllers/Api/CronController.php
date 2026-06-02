<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;

class CronController extends Controller
{
    public function shopifySync(): \Illuminate\Http\JsonResponse
    {
        $exitCode = Artisan::call('shopify:sync-scheduled');

        return response()->json([
            'ok' => $exitCode === 0,
            'output' => trim(Artisan::output()),
        ]);
    }
}
