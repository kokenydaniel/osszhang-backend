<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Saving;
use App\Models\LedgerEntry;
use Illuminate\Http\Request;

class SavingController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            Saving::where('household_id', $request->user()->household_id)
                ->with('ledger')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'institution' => 'required|string',
            'currency' => 'required|string',
            'owner' => 'sometimes|string',
            'count_in_savings' => 'sometimes|boolean'
        ]);

        $saving = Saving::create([
            'household_id' => $request->user()->household_id,
            'institution' => $v['institution'],
            'currency' => $v['currency'],
            'owner' => $v['owner'] ?? 'Közös',
            'count_in_savings' => $v['count_in_savings'] ?? true
        ]);

        return response()->json($saving->load('ledger'), 201);
    }

    public function addEntry(Request $request, $id)
    {
        $saving = Saving::where('household_id', $request->user()->household_id)->findOrFail($id);
        
        $v = $request->validate([
            'amount' => 'required|numeric',
            'reason' => 'required|string',
            'date' => 'required|date'
        ]);

        $saving->ledger()->create([
            'amount' => $v['amount'],
            'reason' => $v['reason'],
            'date' => $v['date']
        ]);

        return response()->json($saving->load('ledger'));
    }

    public function deleteEntry(Request $request, $savingId, $entryId)
    {
        $saving = Saving::where('household_id', $request->user()->household_id)->findOrFail($savingId);
        $entry = LedgerEntry::where('saving_id', $saving->id)->findOrFail($entryId);
        $entry->delete();

        return response()->json($saving->load('ledger'));
    }

    public function update(Request $request, $id)
    {
        $saving = Saving::where('household_id', $request->user()->household_id)->findOrFail($id);

        $v = $request->validate([
            'institution' => 'sometimes|string',
            'currency' => 'sometimes|string',
            'owner' => 'sometimes|string',
            'count_in_savings' => 'sometimes|boolean'
        ]);

        $saving->update($v);

        return response()->json($saving->load('ledger'));
    }

    public function destroy($id)
    {
        Saving::where('household_id', auth()->user()->household_id)->findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
