<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ValidatesUploadedAttachments;
use App\Http\Controllers\Controller;
use App\Models\BusinessDocument;
use App\Services\BusinessDocumentService;
use App\Services\SumUpImportService;
use Illuminate\Http\Request;

class BusinessDocumentController extends Controller
{
    public function __construct(
        private readonly BusinessDocumentService $documents,
        private readonly SumUpImportService $sumUpImport,
    ) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $household = $request->user()->household;

        return response()->json([
            'data' => $this->documents->listForMonth(
                $household,
                (int) $validated['year'],
                (int) $validated['month'],
            ),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'document_type' => 'required|string|max:32',
            'business_order_id' => 'nullable|integer|exists:business_orders,id',
            'label' => 'nullable|string|max:200',
        ]);

        $this->validateUploadedAttachment($request->file('file'), 20480, self::BUSINESS_DOC_EXTENSIONS);

        $household = $request->user()->household;

        $document = $this->documents->store(
            $household,
            $request->user(),
            (int) $validated['year'],
            (int) $validated['month'],
            $validated['document_type'],
            $request->file('file'),
            isset($validated['business_order_id']) ? (int) $validated['business_order_id'] : null,
            $validated['label'] ?? null,
        );

        return response()->json(['data' => $document], 201);
    }

    public function destroy(Request $request, BusinessDocument $businessDocument)
    {
        abort_if($businessDocument->household_id !== $request->user()->household_id, 404);
        $this->documents->delete($businessDocument);

        return response()->json(['message' => 'Törölve.']);
    }

    public function download(Request $request, BusinessDocument $businessDocument)
    {
        abort_if($businessDocument->household_id !== $request->user()->household_id, 404);

        return $this->documents->downloadResponse($businessDocument);
    }

    public function sumupImport(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $result = $this->sumUpImport->importMonth(
            $request->user(),
            (int) $validated['year'],
            (int) $validated['month'],
        );

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'message' => $result['error'] ?? 'SumUp import sikertelen.',
            ], (int) ($result['status'] ?? 400));
        }

        return response()->json(['data' => $result]);
    }

    public function bundle(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $household = $request->user()->household;

        return $this->documents->bundleZipResponse(
            $household,
            (int) $validated['year'],
            (int) $validated['month'],
        );
    }
}
