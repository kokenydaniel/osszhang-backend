<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReceivableService;
use Illuminate\Http\Request;

class ReceivableController extends Controller
{
    public function __construct(private readonly ReceivableService $receivables) {}

    public function index(Request $request)
    {
        return response()->json($this->receivables->index($request->user()));
    }

    public function storeContact(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'note' => 'nullable|string|max:500',
        ]);

        return response()->json(
            $this->receivables->createContact($request->user(), $validated),
            201,
        );
    }

    public function updateContact(Request $request, int $receivable_contact)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:120',
            'note' => 'nullable|string|max:500',
        ]);

        return response()->json(
            $this->receivables->updateContact($request->user(), $receivable_contact, $validated),
        );
    }

    public function destroyContact(Request $request, int $receivable_contact)
    {
        return response()->json(
            $this->receivables->deleteContact($request->user(), $receivable_contact),
        );
    }

    public function storeEntry(Request $request, int $receivable_contact)
    {
        $validated = $request->validate([
            'entryType' => 'required_without:entry_type|in:lent,repaid',
            'entry_type' => 'required_without:entryType|in:lent,repaid',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'sometimes|string|max:8',
            'source' => 'required|in:savings,transfer,cash',
            'entryDate' => 'required_without:entry_date|date',
            'entry_date' => 'required_without:entryDate|date',
            'note' => 'nullable|string|max:500',
        ]);

        return response()->json(
            $this->receivables->createEntry($request->user(), $receivable_contact, $validated),
            201,
        );
    }

    public function updateEntry(Request $request, int $receivable_entry)
    {
        $validated = $request->validate([
            'entryType' => 'sometimes|in:lent,repaid',
            'entry_type' => 'sometimes|in:lent,repaid',
            'amount' => 'sometimes|numeric|min:0.01',
            'currency' => 'sometimes|string|max:8',
            'source' => 'sometimes|in:savings,transfer,cash',
            'entryDate' => 'sometimes|date',
            'entry_date' => 'sometimes|date',
            'note' => 'nullable|string|max:500',
        ]);

        return response()->json(
            $this->receivables->updateEntry($request->user(), $receivable_entry, $validated),
        );
    }

    public function destroyEntry(Request $request, int $receivable_entry)
    {
        return response()->json(
            $this->receivables->deleteEntry($request->user(), $receivable_entry),
        );
    }
}
