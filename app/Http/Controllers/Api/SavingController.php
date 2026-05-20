<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Models\Saving;
use App\Services\EncryptedRecordService;
use Illuminate\Http\Request;

class SavingController extends Controller
{
    public function __construct(
        private readonly EncryptedRecordService $crypto,
    ) {}

    public function index(Request $request)
    {
        $household = $request->user()->household;

        return response()->json(
            Saving::where('household_id', $household->id)
                ->with('ledger')
                ->get()
                ->map(fn ($s) => $this->crypto->formatSaving($s, $household))
        );
    }

    public function store(Request $request)
    {
        $household = $request->user()->household;
        $v = $request->validate([
            'institution' => 'required|string',
            'currency' => 'required|string',
            'owner' => 'sometimes|string',
            'count_in_savings' => 'sometimes|boolean',
        ]);

        $saving = new Saving([
            'household_id' => $household->id,
            'count_in_savings' => $v['count_in_savings'] ?? true,
        ]);
        $this->crypto->persistSaving($saving, $household, [
            'institution' => $v['institution'],
            'currency' => $v['currency'],
            'owner' => $v['owner'] ?? 'Közös',
        ]);
        $saving->save();

        return response()->json($this->crypto->formatSaving($saving->load('ledger'), $household), 201);
    }

    public function addEntry(Request $request, $id)
    {
        $household = $request->user()->household;
        $saving = Saving::where('household_id', $household->id)->findOrFail($id);

        $v = $request->validate([
            'amount' => 'required|numeric',
            'reason' => 'required|string',
            'date' => 'required|date',
        ]);

        $entry = new LedgerEntry([
            'saving_id' => $saving->id,
            'date' => $v['date'],
        ]);
        $this->crypto->persistLedger($entry, $household, [
            'amount' => (float) $v['amount'],
            'reason' => $v['reason'],
        ]);
        $entry->save();

        return response()->json($this->crypto->formatSaving($saving->load('ledger'), $household));
    }

    public function updateEntry(Request $request, $savingId, $entryId)
    {
        $household = $request->user()->household;
        $saving = Saving::where('household_id', $household->id)->findOrFail($savingId);
        $entry = LedgerEntry::where('saving_id', $saving->id)->findOrFail($entryId);

        $v = $request->validate([
            'amount' => 'sometimes|numeric',
            'reason' => 'sometimes|string',
            'date' => 'sometimes|date',
        ]);

        $sensitive = $this->crypto->ledgerResolved($entry, $household);
        if (array_key_exists('amount', $v)) {
            $sensitive['amount'] = (float) $v['amount'];
        }
        if (array_key_exists('reason', $v)) {
            $sensitive['reason'] = $v['reason'];
        }
        if (array_key_exists('date', $v)) {
            $entry->date = $v['date'];
        }

        $this->crypto->persistLedger($entry, $household, $sensitive);
        $entry->save();

        return response()->json($this->crypto->formatSaving($saving->load('ledger'), $household));
    }

    public function deleteEntry(Request $request, $savingId, $entryId)
    {
        $household = $request->user()->household;
        $saving = Saving::where('household_id', $household->id)->findOrFail($savingId);
        LedgerEntry::where('saving_id', $saving->id)->findOrFail($entryId)->delete();

        return response()->json($this->crypto->formatSaving($saving->load('ledger'), $household));
    }

    public function update(Request $request, $id)
    {
        $household = $request->user()->household;
        $saving = Saving::where('household_id', $household->id)->findOrFail($id);

        $v = $request->validate([
            'institution' => 'sometimes|string',
            'currency' => 'sometimes|string',
            'owner' => 'sometimes|string',
            'count_in_savings' => 'sometimes|boolean',
        ]);

        $sensitive = $this->crypto->savingResolved($saving, $household);
        if (array_key_exists('institution', $v)) {
            $sensitive['institution'] = $v['institution'];
        }
        if (array_key_exists('currency', $v)) {
            $sensitive['currency'] = $v['currency'];
        }
        if (array_key_exists('owner', $v)) {
            $sensitive['owner'] = $v['owner'];
        }
        if (array_key_exists('count_in_savings', $v)) {
            $saving->count_in_savings = $v['count_in_savings'];
        }

        $this->crypto->persistSaving($saving, $household, $sensitive);
        $saving->save();

        return response()->json($this->crypto->formatSaving($saving->load('ledger'), $household));
    }

    public function destroy($id)
    {
        Saving::where('household_id', auth()->user()->household_id)->findOrFail($id)->delete();

        return response()->json(null, 204);
    }
}
