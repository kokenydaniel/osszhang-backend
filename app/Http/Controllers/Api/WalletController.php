<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\StoreWalletRequest;
use App\Http\Requests\Wallet\UpdateWalletManualBalanceRequest;
use App\Http\Requests\Wallet\UpdateWalletRequest;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(private readonly WalletService $walletService) {}

    public function index(Request $request)
    {
        return response()->json($this->walletService->listAccessible($request->user()));
    }

    public function store(StoreWalletRequest $request)
    {
        return response()->json(
            $this->walletService->create($request->user(), $request->validated()),
            201,
        );
    }

    public function update(UpdateWalletRequest $request, Wallet $wallet)
    {
        return response()->json(
            $this->walletService->update($request->user(), $wallet, $request->validated()),
        );
    }

    public function updateManualBalance(UpdateWalletManualBalanceRequest $request, Wallet $wallet)
    {
        return response()->json(
            $this->walletService->updateManualBalance(
                $request->user(),
                $wallet,
                (float) $request->manual_balance,
            ),
        );
    }

    public function destroy(Request $request, Wallet $wallet)
    {
        $this->walletService->delete($request->user(), $wallet);

        return response()->json(null, 204);
    }
}
