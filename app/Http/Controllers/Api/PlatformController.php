<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\PlatformSettings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

class PlatformController extends Controller
{
    public function updateBetaMode(Request $request)
    {
        if (! $request->user()->lifetime_admin) {
            throw new AuthorizationException('Nincs jogosultságod a platform beállítások módosításához.');
        }

        $validated = $request->validate([
            'enabled' => 'required|boolean',
        ]);

        PlatformSettings::setBetaMode((bool) $validated['enabled']);

        return response()->json([
            'beta_mode' => PlatformSettings::isBetaMode(),
            'betaMode' => PlatformSettings::isBetaMode(),
        ]);
    }
}
