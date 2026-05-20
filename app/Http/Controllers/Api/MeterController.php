<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Services\EncryptedRecordService;
use Illuminate\Http\Request;

class MeterController extends Controller
{
    public function __construct(
        private readonly EncryptedRecordService $crypto,
    ) {}

    private function recalculateConsumptions(int $meterId, Household $household): void
    {
        $readings = MeterReading::where('meter_id', $meterId)
            ->orderBy('date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $previous = null;
        foreach ($readings as $reading) {
            $resolved = $this->crypto->readingResolved($reading, $household);
            $value = (float) ($resolved['value'] ?? 0);

            if (! $previous) {
                $consumption = 0;
            } elseif ($reading->is_reset) {
                $consumption = 0;
            } else {
                $prevValue = (float) ($this->crypto->readingResolved($previous, $household)['value'] ?? 0);
                $diff = $value - $prevValue;
                $consumption = $diff >= 0 ? $diff : $value;
            }

            $this->crypto->persistReading($reading, $household, [
                'value' => $value,
                'consumption' => $consumption,
            ]);
            $reading->save();

            $previous = $reading;
        }
    }

    public function index(Request $request)
    {
        $household = $request->user()->household;

        return response()->json(
            Meter::where('household_id', $household->id)
                ->with(['readings' => fn ($q) => $q->orderBy('date', 'desc')])
                ->get()
                ->map(fn ($m) => $this->crypto->formatMeter($m, $household))
        );
    }

    public function store(Request $request)
    {
        $household = $request->user()->household;
        $v = $request->validate([
            'name' => 'required|string',
            'unit' => 'required|string',
            'location' => 'nullable|string',
        ]);

        $meter = new Meter([
            'household_id' => $household->id,
            'unit' => $v['unit'],
            'icon' => '📊',
        ]);
        $this->crypto->persistMeter($meter, $household, [
            'name' => $v['name'],
            'location' => $v['location'] ?? 'Otthon',
        ]);
        $meter->save();

        return response()->json($this->crypto->formatMeter($meter->load('readings'), $household), 201);
    }

    public function show(Request $request, $id)
    {
        $household = $request->user()->household;
        $meter = Meter::where('household_id', $household->id)
            ->with(['readings' => fn ($q) => $q->orderBy('date', 'desc')])
            ->findOrFail($id);

        return response()->json($this->crypto->formatMeter($meter, $household));
    }

    public function addReading(Request $request, $id)
    {
        $household = $request->user()->household;
        $meter = Meter::where('household_id', $household->id)->findOrFail($id);

        $v = $request->validate([
            'value' => 'required|numeric',
            'date' => 'required|date',
        ]);

        $date = new \DateTime($v['date']);

        $reading = new MeterReading([
            'meter_id' => $meter->id,
            'date' => $v['date'],
            'month' => (int) $date->format('m'),
            'year' => (int) $date->format('Y'),
            'is_reset' => $request->isReset || $request->is_reset || false,
            'is_estimated' => $request->isEstimated || $request->is_estimated || false,
            'is_official' => $request->isOfficial || $request->is_official || false,
        ]);
        $this->crypto->persistReading($reading, $household, [
            'value' => (float) $v['value'],
            'consumption' => 0,
        ]);
        $reading->save();

        $this->recalculateConsumptions($meter->id, $household);

        return response()->json($this->crypto->formatMeter($meter->fresh()->load(['readings' => fn ($q) => $q->orderBy('date', 'desc')]), $household));
    }

    public function updateReading(Request $request, $meterId, $readingId)
    {
        $household = $request->user()->household;
        $meter = Meter::where('household_id', $household->id)->findOrFail($meterId);
        $reading = MeterReading::where('meter_id', $meter->id)->findOrFail($readingId);

        $v = $request->validate([
            'value' => 'required|numeric',
            'date' => 'required|date',
        ]);

        $date = new \DateTime($v['date']);
        $reading->date = $v['date'];
        $reading->month = (int) $date->format('m');
        $reading->year = (int) $date->format('Y');
        $reading->is_reset = $request->isReset || $request->is_reset || $reading->is_reset;
        $reading->is_estimated = $request->isEstimated || $request->is_estimated || $reading->is_estimated;
        $reading->is_official = $request->isOfficial || $request->is_official || $reading->is_official;

        $resolved = $this->crypto->readingResolved($reading, $household);
        $resolved['value'] = (float) $v['value'];
        $this->crypto->persistReading($reading, $household, $resolved);
        $reading->save();

        $this->recalculateConsumptions($meter->id, $household);

        return response()->json($this->crypto->formatMeter($meter->fresh()->load(['readings' => fn ($q) => $q->orderBy('date', 'desc')]), $household));
    }

    public function deleteReading(Request $request, $meterId, $readingId)
    {
        $household = $request->user()->household;
        $meter = Meter::where('household_id', $household->id)->findOrFail($meterId);
        MeterReading::where('meter_id', $meter->id)->findOrFail($readingId)->delete();

        $this->recalculateConsumptions($meter->id, $household);

        return response()->json($this->crypto->formatMeter($meter->fresh()->load(['readings' => fn ($q) => $q->orderBy('date', 'desc')]), $household));
    }

    public function destroy($id)
    {
        Meter::where('household_id', auth()->user()->household_id)->findOrFail($id)->delete();

        return response()->json(null, 204);
    }
}
