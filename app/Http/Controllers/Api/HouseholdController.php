<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Household\AddMemberRequest;
use App\Http\Requests\Household\DestroyHouseholdRequest;
use App\Http\Requests\Household\UpdateHouseholdRequest;
use App\Http\Requests\Household\UpdateInviteCodeRequest;
use App\Http\Requests\Household\UpdateMemberRequest;
use App\Http\Resources\HouseholdResource;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Http\Request;

class HouseholdController extends Controller
{
    public function __construct(private readonly HouseholdService $householdService) {}

    public function show(Request $request)
    {
        return new HouseholdResource($this->householdService->show($request->user()->household));
    }

    public function update(UpdateHouseholdRequest $request)
    {
        return new HouseholdResource(
            $this->householdService->update($request->user()->household, $request),
        );
    }

    public function updateInviteCode(UpdateInviteCodeRequest $request)
    {
        return response()->json($this->householdService->updateInviteCode($request->user(), $request));
    }

    public function updateMember(UpdateMemberRequest $request, User $member)
    {
        return response()->json($this->householdService->updateMember($request->user(), $member, $request));
    }

    public function addMember(AddMemberRequest $request)
    {
        return response()->json($this->householdService->addMember($request->user(), $request), 201);
    }

    public function deleteMember(Request $request, User $member)
    {
        return response()->json($this->householdService->deleteMember($request->user(), $member));
    }

    public function updateCategories(Request $request)
    {
        return response()->json(
            $this->householdService->updateCategories($request->user()->household, $request->categories),
        );
    }

    public function destroy(DestroyHouseholdRequest $request)
    {
        $this->householdService->destroy($request->user(), $request);

        return response()->json(null, 204);
    }
}
