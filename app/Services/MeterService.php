<?php

namespace App\Services;

use App\Models\Household;
use App\Models\Meter;
use App\Models\MeterReading;
use DateTime;
use Illuminate\Http\Request;

class MeterService
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

    public function listForHousehold(Household $household): array
    {
        return Meter::where('household_id', $household->id)
            ->with(['readings' => fn ($q) => $q->orderBy('date', 'desc')])
            ->get()
            ->map(fn ($m) => $this->crypto->formatMeter($m, $household))
            ->all();
    }

    public function create(Household $household, array $validated): array
    {
        $meter = new Meter([
            'household_id' => $household->id,
            'unit' => $validated['unit'],
            'icon' => '📊',
        ]);
        $this->crypto->persistMeter($meter, $household, [
            'name' => $validated['name'],
            'location' => $validated['location'] ?? 'Otthon',
        ]);
        $meter->save();

        return $this->crypto->formatMeter($meter->load('readings'), $household);
    }

    public function show(Household $household, int|string $id): array
    {
        $meter = Meter::where('household_id', $household->id)
            ->with(['readings' => fn ($q) => $q->orderBy('date', 'desc')])
            ->findOrFail($id);

        return $this->crypto->formatMeter($meter, $household);
    }

    public function addReading(Household $household, int|string $id, array $validated, Request $request): array
    {
        $meter = Meter::where('household_id', $household->id)->findOrFail($id);

        $date = new DateTime($validated['date']);

        $reading = new MeterReading([
            'meter_id' => $meter->id,
            'date' => $validated['date'],
            'month' => (int) $date->format('m'),
            'year' => (int) $date->format('Y'),
            'is_reset' => $request->isReset || $request->is_reset || false,
            'is_estimated' => $request->isEstimated || $request->is_estimated || false,
            'is_official' => $request->isOfficial || $request->is_official || false,
        ]);
        $this->crypto->persistReading($reading, $household, [
            'value' => (float) $validated['value'],
            'consumption' => 0,
        ]);
        $reading->save();

        $this->recalculateConsumptions($meter->id, $household);

        return $this->crypto->formatMeter(
            $meter->fresh()->load(['readings' => fn ($q) => $q->orderBy('date', 'desc')]),
            $household,
        );
    }

    public function updateReading(Household $household, int|string $meterId, int|string $readingId, array $validated, Request $request): array
    {
        $meter = Meter::where('household_id', $household->id)->findOrFail($meterId);
        $reading = MeterReading::where('meter_id', $meter->id)->findOrFail($readingId);

        $date = new DateTime($validated['date']);
        $reading->date = $validated['date'];
        $reading->month = (int) $date->format('m');
        $reading->year = (int) $date->format('Y');
        $reading->is_reset = $request->isReset || $request->is_reset || $reading->is_reset;
        $reading->is_estimated = $request->isEstimated || $request->is_estimated || $reading->is_estimated;
        $reading->is_official = $request->isOfficial || $request->is_official || $reading->is_official;

        $resolved = $this->crypto->readingResolved($reading, $household);
        $resolved['value'] = (float) $validated['value'];
        $this->crypto->persistReading($reading, $household, $resolved);
        $reading->save();

        $this->recalculateConsumptions($meter->id, $household);

        return $this->crypto->formatMeter(
            $meter->fresh()->load(['readings' => fn ($q) => $q->orderBy('date', 'desc')]),
            $household,
        );
    }

    public function deleteReading(Household $household, int|string $meterId, int|string $readingId): array
    {
        $meter = Meter::where('household_id', $household->id)->findOrFail($meterId);
        MeterReading::where('meter_id', $meter->id)->findOrFail($readingId)->delete();

        $this->recalculateConsumptions($meter->id, $household);

        return $this->crypto->formatMeter(
            $meter->fresh()->load(['readings' => fn ($q) => $q->orderBy('date', 'desc')]),
            $household,
        );
    }

    public function delete(int $householdId, int|string $id): void
    {
        Meter::where('household_id', $householdId)->findOrFail($id)->delete();
    }
}
