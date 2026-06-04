<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ValidatesUploadedAttachments;
use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Transaction;
use App\Services\AttachmentService;
use Illuminate\Http\Request;

class AttachmentController extends Controller
{
    use ValidatesUploadedAttachments;

    private const RECEIPT_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

    private const CONTRACT_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

    private const POLICY_EXTENSIONS = ['pdf'];

    public function __construct(private readonly AttachmentService $attachments) {}

    public function storeForTransaction(Request $request, Transaction $transaction)
    {
        $this->validateUploadedAttachment($request->file('file'), 10240, self::RECEIPT_EXTENSIONS);

        $household = $request->user()->household;
        abort_if($transaction->household_id !== $household->id, 404);

        $attachment = $this->attachments->store($household, $request->user(), $transaction, $request->file('file'));

        return response()->json(['data' => $attachment], 201);
    }

    public function indexForTransaction(Request $request, Transaction $transaction)
    {
        abort_if($transaction->household_id !== $request->user()->household_id, 404);

        return response()->json(['data' => $this->attachments->listFor($transaction)]);
    }

    public function storeForLedgerEntry(Request $request, int $ledgerEntry)
    {
        $this->validateUploadedAttachment($request->file('file'), 10240, self::RECEIPT_EXTENSIONS);

        $household = $request->user()->household;
        $entry = $this->attachments->resolveLedgerEntry($household, $ledgerEntry);

        $attachment = $this->attachments->store($household, $request->user(), $entry, $request->file('file'));

        return response()->json(['data' => $attachment], 201);
    }

    public function indexForLedgerEntry(Request $request, int $ledgerEntry)
    {
        $household = $request->user()->household;
        $entry = $this->attachments->resolveLedgerEntry($household, $ledgerEntry);

        return response()->json(['data' => $this->attachments->listFor($entry)]);
    }

    public function storeForBudgetLedgerItem(Request $request, int $transaction)
    {
        $request->validate(['item_id' => 'required|integer']);
        $this->validateUploadedAttachment($request->file('file'), 10240, self::RECEIPT_EXTENSIONS);

        $household = $request->user()->household;
        $entry = $this->attachments->resolveBudgetLedgerItem($household, $transaction, (int) $request->input('item_id'));
        $attachment = $this->attachments->store($household, $request->user(), $entry, $request->file('file'));

        return response()->json(['data' => $attachment], 201);
    }

    public function indexForBudgetLedgerItem(Request $request, int $transaction)
    {
        $request->validate(['item_id' => 'required|integer']);

        $household = $request->user()->household;
        $entry = $this->attachments->resolveBudgetLedgerItem($household, $transaction, (int) $request->input('item_id'));

        return response()->json(['data' => $this->attachments->listFor($entry)]);
    }

    public function storeForInsurancePolicy(Request $request, int $insurancePolicy)
    {
        $this->validateUploadedAttachment($request->file('file'), 15360, self::POLICY_EXTENSIONS);

        $household = $request->user()->household;
        abort_unless($household->insurance_enabled, 403);
        $policy = $this->attachments->resolveInsurancePolicy($household, $insurancePolicy);

        $attachment = $this->attachments->store($household, $request->user(), $policy, $request->file('file'));

        return response()->json(['data' => $attachment], 201);
    }

    public function indexForInsurancePolicy(Request $request, int $insurancePolicy)
    {
        $household = $request->user()->household;
        abort_unless($household->insurance_enabled, 403);
        $policy = $this->attachments->resolveInsurancePolicy($household, $insurancePolicy);

        return response()->json(['data' => $this->attachments->listFor($policy)]);
    }

    public function storeForRentalProperty(Request $request, int $rental_property)
    {
        $this->validateUploadedAttachment($request->file('file'), 15360, self::CONTRACT_EXTENSIONS);

        $household = $request->user()->household;
        abort_unless($household->rental_enabled, 403);
        $property = $this->attachments->resolveRentalProperty($household, $rental_property);

        $attachment = $this->attachments->store($household, $request->user(), $property, $request->file('file'));

        return response()->json(['data' => $attachment], 201);
    }

    public function indexForRentalProperty(Request $request, int $rental_property)
    {
        $household = $request->user()->household;
        abort_unless($household->rental_enabled, 403);
        $property = $this->attachments->resolveRentalProperty($household, $rental_property);

        return response()->json(['data' => $this->attachments->listFor($property)]);
    }

    public function download(Request $request, Attachment $attachment)
    {
        $this->attachments->assertHouseholdOwnsAttachment($request->user()->household, $attachment);

        return $this->attachments->downloadResponse($attachment);
    }

    public function destroy(Request $request, Attachment $attachment)
    {
        $this->attachments->assertHouseholdOwnsAttachment($request->user()->household, $attachment);
        $this->attachments->delete($attachment);

        return response()->json(['message' => 'Törölve.']);
    }
}
