<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductUpdate;
use App\Support\ProductUpdates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductUpdateController extends Controller
{
    public function dismiss(Request $request, ProductUpdate $productUpdate): JsonResponse
    {
        ProductUpdates::dismiss($request->user(), $productUpdate);

        return response()->json(['data' => null]);
    }
}
