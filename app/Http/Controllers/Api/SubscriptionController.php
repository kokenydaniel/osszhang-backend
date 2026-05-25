<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SubscriptionController extends Controller
{
    public function __construct(private readonly BillingService $billingService) {}

    public function billing(Request $request)
    {
        return response()->json(
            $this->billingService->billingSummary($request->user()),
        );
    }

    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'price_id' => 'required|string',
        ]);

        return response()->json(
            $this->billingService->createCheckoutSession($request->user(), $validated['price_id']),
        );
    }

    public function portal(Request $request)
    {
        return response()->json(
            $this->billingService->createPortalSession($request->user()),
        );
    }

    public function downloadInvoice(Request $request, string $invoice)
    {
        return $this->billingService->downloadInvoice($request->user(), $invoice);
    }

    /** @deprecated Use Stripe Customer Portal via portal() */
    public function cancel(Request $request)
    {
        throw ValidationException::withMessages([
            'subscription' => ['Az előfizetés lemondása a Stripe ügyfélkapun keresztül érhető el.'],
        ]);
    }

    /** @deprecated Use Stripe Customer Portal via portal() */
    public function reactivate(Request $request)
    {
        throw ValidationException::withMessages([
            'subscription' => ['Az előfizetés kezelése a Stripe ügyfélkapun keresztül érhető el.'],
        ]);
    }

    /** @deprecated Use Stripe Customer Portal via portal() */
    public function downgrade(Request $request)
    {
        throw ValidationException::withMessages([
            'subscription' => ['A csomagváltás a Stripe ügyfélkapun keresztül érhető el.'],
        ]);
    }
}
