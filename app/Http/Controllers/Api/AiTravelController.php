<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AIFinance\TravelPlanPdfRequest;
use App\Http\Requests\AIFinance\TravelPlanRequest;
use App\Services\AIFinanceService;
use App\Services\Travel\TravelPlanPdfExporter;
use App\Services\TravelPlanService;
use App\Support\AccessControl;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

class AiTravelController extends Controller
{
    public function __construct(
        private readonly AIFinanceService $aiFinanceService,
        private readonly TravelPlanService $travelPlans,
        private readonly TravelPlanPdfExporter $pdfExporter,
    ) {}

    public function plan(TravelPlanRequest $request)
    {
        $user = $request->user();
        if ($user === null || ! AccessControl::canAccessModule($user, 'travel_planner')) {
            throw new AuthorizationException(AccessControl::moduleAccessDeniedMessage('travel_planner'));
        }

        $validated = $request->validated();
        $envelope = $this->aiFinanceService->travelPlan($user, $validated);

        if (isset($envelope['data']) && is_array($envelope['data'])) {
            try {
                $walletId = isset($validated['wallet_id']) ? (int) $validated['wallet_id'] : null;
                $saved = $this->travelPlans->store(
                    $user,
                    $validated,
                    $envelope['data'],
                    $envelope['data']['financial_context'] ?? null,
                    $walletId,
                );
                $envelope['data']['saved_plan_id'] = $saved['id'];
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json($envelope);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        if ($user === null || ! AccessControl::canAccessModule($user, 'travel_planner')) {
            throw new AuthorizationException(AccessControl::moduleAccessDeniedMessage('travel_planner'));
        }

        $walletId = $request->filled('walletId') ? (int) $request->query('walletId') : null;

        return response()->json(
            $this->travelPlans->listForUser($user, $walletId),
        );
    }

    public function show(Request $request, int $travelPlan)
    {
        $user = $request->user();
        if ($user === null || ! AccessControl::canAccessModule($user, 'travel_planner')) {
            throw new AuthorizationException(AccessControl::moduleAccessDeniedMessage('travel_planner'));
        }

        return response()->json(
            $this->travelPlans->show($user, $travelPlan),
        );
    }

    public function destroy(Request $request, int $travelPlan)
    {
        $user = $request->user();
        if ($user === null || ! AccessControl::canAccessModule($user, 'travel_planner')) {
            throw new AuthorizationException(AccessControl::moduleAccessDeniedMessage('travel_planner'));
        }

        $this->travelPlans->destroy($user, $travelPlan);

        return response()->json(['message' => 'Törölve.']);
    }

    public function linkSaving(Request $request, int $travelPlan)
    {
        $user = $request->user();
        if ($user === null || ! AccessControl::canAccessModule($user, 'travel_planner')) {
            throw new AuthorizationException(AccessControl::moduleAccessDeniedMessage('travel_planner'));
        }

        $validated = $request->validate([
            'saving_id' => 'required|integer|exists:savings,id',
        ]);

        return response()->json(
            $this->travelPlans->linkSavingGoal($user, $travelPlan, (int) $validated['saving_id']),
        );
    }

    public function pdf(TravelPlanPdfRequest $request)
    {
        $user = $request->user();
        if ($user === null || ! AccessControl::canAccessModule($user, 'travel_planner')) {
            throw new AuthorizationException(AccessControl::moduleAccessDeniedMessage('travel_planner'));
        }

        $validated = $request->validated();
        $plan = $validated['plan'];
        $form = $validated['form'];
        $formLabels = isset($validated['form_labels']) && is_array($validated['form_labels'])
            ? $validated['form_labels']
            : null;
        $meta = isset($validated['meta']) && is_array($validated['meta'])
            ? $validated['meta']
            : null;

        $destination = (string) ($plan['destination'] ?? $form['destination'] ?? 'utazas');
        $pdf = $this->pdfExporter->generate($plan, $form, $formLabels, $meta);
        $filename = $this->pdfExporter->filenameForDestination($destination);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function update(Request $request, int $travelPlan)
    {
        $user = $request->user();
        if ($user === null || ! AccessControl::canAccessModule($user, 'travel_planner')) {
            throw new AuthorizationException(AccessControl::moduleAccessDeniedMessage('travel_planner'));
        }

        $validated = $request->validate([
            'cost_line_items' => 'required|array',
            'cost_line_items.*.id' => 'required|string|max:120',
            'cost_line_items.*.label' => 'required|string|max:200',
            'cost_line_items.*.category' => 'required|string|max:50',
            'cost_line_items.*.amount_huf' => 'required|numeric|min:0',
            'cost_line_items.*.status' => 'required|in:planned,paid,excluded',
            'cost_line_items.*.source' => 'required|in:ai,custom',
            'cost_line_items.*.split_enabled' => 'sometimes|boolean',
            'cost_line_items.*.split_between' => 'nullable|integer|min:2|max:20',
            'total_estimated_cost' => 'required|numeric|min:0',
            'remaining_to_pay_huf' => 'required|numeric|min:0',
            'paid_total_huf' => 'nullable|numeric|min:0',
            'cost_breakdown' => 'required|array',
            'financial_fit' => 'nullable|array',
        ]);

        return response()->json(
            $this->travelPlans->updateCostAdjustments($user, $travelPlan, $validated),
        );
    }
}
