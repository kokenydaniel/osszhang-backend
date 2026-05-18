<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meter;
use App\Models\MeterReading;
use Illuminate\Http\Request;

class MeterController extends Controller
{
    private function recalculateConsumptions(int $meterId): void
    {
        $readings = MeterReading::where('meter_id', $meterId)
            ->orderBy('date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $previous = null;
        foreach ($readings as $reading) {
            if (!$previous) {
                $consumption = 0;
            } elseif ($reading->is_reset) {
                // Reset event itself should not create artificial consumption spike.
                $consumption = 0;
            } else {
                $diff = $reading->value - $previous->value;
                $consumption = $diff >= 0 ? $diff : $reading->value;
            }

            if ((float)$reading->consumption !== (float)$consumption) {
                $reading->consumption = $consumption;
                $reading->save();
            }

            $previous = $reading;
        }
    }

    public function index(Request $request)
    {
        return response()->json(
            Meter::where('household_id', $request->user()->household_id)
                ->with(['readings' => function($q) {
                    $q->orderBy('date', 'desc');
                }])
                ->get()
        );
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'name' => 'required|string',
            'unit' => 'required|string',
            'location' => 'nullable|string'
        ]);

        $meter = Meter::create([
            'household_id' => $request->user()->household_id,
            'name' => $v['name'],
            'unit' => $v['unit'],
            'location' => $v['location'] ?? 'Otthon',
            'icon' => '📊'
        ]);

        return response()->json($meter->load('readings'), 201);
    }

    public function show(Request $request, $id)
    {
        return response()->json(
            Meter::where('household_id', $request->user()->household_id)
                ->with(['readings' => function($q) {
                    $q->orderBy('date', 'desc');
                }])
                ->findOrFail($id)
        );
    }

    public function addReading(Request $request, $id)
    {
        $meter = Meter::where('household_id', $request->user()->household_id)->findOrFail($id);
        
        $v = $request->validate([
            'value' => 'required|numeric',
            'date' => 'required|date'
        ]);

        $date = new \DateTime($v['date']);

        $meter->readings()->create([
            'value' => $v['value'],
            'date' => $v['date'],
            'month' => (int)$date->format('m'),
            'year' => (int)$date->format('Y'),
            'consumption' => 0,
            'is_reset' => $request->isReset || $request->is_reset || false,
            'is_estimated' => $request->isEstimated || $request->is_estimated || false,
            'is_official' => $request->isOfficial || $request->is_official || false
        ]);

        $this->recalculateConsumptions($meter->id);

        return response()->json($meter->load('readings'));
    }

    public function updateReading(Request $request, $meterId, $readingId)
    {
        $meter = Meter::where('household_id', $request->user()->household_id)->findOrFail($meterId);
        $reading = MeterReading::where('meter_id', $meter->id)->findOrFail($readingId);
        
        $v = $request->validate([
            'value' => 'required|numeric',
            'date' => 'required|date'
        ]);

        $date = new \DateTime($v['date']);
        $reading->update([
            'value' => $v['value'],
            'date' => $v['date'],
            'month' => (int)$date->format('m'),
            'year' => (int)$date->format('Y'),
            'is_reset' => $request->isReset || $request->is_reset || $reading->is_reset,
            'is_estimated' => $request->isEstimated || $request->is_estimated || $reading->is_estimated,
            'is_official' => $request->isOfficial || $request->is_official || $reading->is_official
        ]);

        $this->recalculateConsumptions($meter->id);

        return response()->json($meter->load('readings'));
    }

    public function deleteReading(Request $request, $meterId, $readingId)
    {
        $meter = Meter::where('household_id', $request->user()->household_id)->findOrFail($meterId);
        $reading = MeterReading::where('meter_id', $meter->id)->findOrFail($readingId);
        $reading->delete();

        $this->recalculateConsumptions($meter->id);

        return response()->json($meter->load('readings'));
    }

    public function destroy($id)
    {
        Meter::where('household_id', auth()->user()->household_id)->findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
