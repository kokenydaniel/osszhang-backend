<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RentalService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RentalController extends Controller
{
    public function __construct(private readonly RentalService $rental) {}

    public function index(Request $request)
    {
        $year = $request->filled('year') ? (int) $request->query('year') : null;
        $month = $request->filled('month') ? (int) $request->query('month') : null;

        return response()->json($this->rental->index($request->user(), $year, $month));
    }

    public function store(Request $request)
    {
        $validated = $this->validateProperty($request);

        return response()->json($this->rental->createProperty($request->user(), $validated), 201);
    }

    public function update(Request $request, int $rental_property)
    {
        $validated = $this->validateProperty($request, true);

        return response()->json($this->rental->updateProperty($request->user(), $rental_property, $validated));
    }

    public function destroy(Request $request, int $rental_property)
    {
        return response()->json($this->rental->deleteProperty($request->user(), $rental_property));
    }

    public function export(Request $request): StreamedResponse
    {
        $year = (int) $request->query('year', now()->format('Y'));
        $rows = $this->rental->exportRows($request->user(), $year);

        $filename = sprintf('berbeadas-%d.csv', $year);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            if ($rows !== []) {
                fputcsv($out, array_keys($rows[0]), ';');
                foreach ($rows as $row) {
                    fputcsv($out, $row, ';');
                }
            } else {
                fputcsv($out, ['Ingatlan', 'Év', 'Hónap', 'Összeg'], ';');
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function validateProperty(Request $request, bool $partial = false): array
    {
        $prefix = $partial ? 'sometimes|' : '';

        return $request->validate([
            'name' => $prefix.'required|string|max:150',
            'address' => 'nullable|string|max:255',
            'monthly_rent' => 'nullable|numeric|min:0',
            'monthlyRent' => 'nullable|numeric|min:0',
            'monthly_common_cost' => 'nullable|numeric|min:0',
            'monthlyCommonCost' => 'nullable|numeric|min:0',
            'currency' => 'sometimes|string|max:8',
            'tenant_name' => 'nullable|string|max:150',
            'tenantName' => 'nullable|string|max:150',
            'contract_ends_at' => 'nullable|date',
            'contractEndsAt' => 'nullable|date',
            'due_day' => 'nullable|integer|min:1|max:28',
            'dueDay' => 'nullable|integer|min:1|max:28',
            'notes' => 'nullable|string|max:2000',
            'agreement_notes' => 'nullable|string|max:2000',
            'agreementNotes' => 'nullable|string|max:2000',
            'budget_sync_enabled' => 'sometimes|boolean',
            'budgetSyncEnabled' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'isActive' => 'sometimes|boolean',
        ]);
    }
}
