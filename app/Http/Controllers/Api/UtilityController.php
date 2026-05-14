<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Utility;
use Illuminate\Http\Request;
class UtilityController extends Controller {
    private function formatUtility($u) {
        return [
            'id' => $u->id, 
            'type' => $u->type, 
            'total' => (float)$u->total, 
            'dueDate' => $u->due_date, 
            'paidDate' => $u->paid_date, 
            'paidBy' => $u->paid_by, 
            'splitRule' => $u->split_rule
        ];
    }

    public function index(Request $request) {
        return response()->json(Utility::where('household_id', $request->user()->household_id)->get()->map(fn($u) => $this->formatUtility($u)));
    }

    public function store(Request $request) {
        $u = Utility::create([
            'household_id' => $request->user()->household_id,
            'type' => $request->type,
            'total' => $request->total,
            'due_date' => $request->dueDate,
            'split_rule' => $request->splitRule,
            'paid_by' => $request->paidBy,
            'paid_date' => $request->paidDate
        ]);
        return response()->json($this->formatUtility($u), 201);
    }

    public function update(Request $request, $id) {
        $u = Utility::where('household_id', $request->user()->household_id)->findOrFail($id);
        
        $data = [];
        if ($request->has('type')) $data['type'] = $request->type;
        if ($request->has('total')) $data['total'] = $request->total;
        if ($request->has('dueDate')) $data['due_date'] = $request->dueDate;
        if ($request->has('splitRule')) $data['split_rule'] = $request->splitRule;
        if ($request->has('paidBy')) $data['paid_by'] = $request->paidBy;
        if ($request->has('paidDate')) $data['paid_date'] = $request->paidDate;

        $u->update($data);
        return response()->json($this->formatUtility($u));
    }
    public function destroy(Request $request, $id) {
        Utility::where('household_id', $request->user()->household_id)->findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
