<?php

namespace App\Services\Formatters;

use App\Models\Household;
use App\Models\Meter;
use App\Models\MeterReading;

class MeterRecordFormatter extends AbstractEncryptedRecordFormatter
{
    public function meterLegacy(Meter $m): array
    {
        return [
            'name' => (string) $m->name,
            'location' => (string) $m->location,
        ];
    }

    public function meterResolved(Meter $m, Household $household): array
    {
        return $this->resolve($household, $m->encrypted_payload, $this->meterLegacy($m));
    }

    public function persistMeter(Meter $m, Household $household, array $sensitive): void
    {
        $this->persist($household, $m, $sensitive, [
            'name' => '—',
            'location' => '—',
        ]);
    }

    public function readingLegacy(MeterReading $r): array
    {
        return [
            'value' => (float) $r->value,
            'consumption' => (float) $r->consumption,
        ];
    }

    public function readingResolved(MeterReading $r, Household $household): array
    {
        return $this->resolve($household, $r->encrypted_payload, $this->readingLegacy($r));
    }

    public function persistReading(MeterReading $r, Household $household, array $sensitive): void
    {
        $this->persist($household, $r, $sensitive, [
            'value' => 0,
            'consumption' => 0,
        ]);
    }

    public function formatMeter(Meter $m, Household $household): array
    {
        $s = $this->meterResolved($m, $household);
        $readings = $m->relationLoaded('readings')
            ? $m->readings->map(fn (MeterReading $r) => $this->formatReading($r, $household))->values()->all()
            : [];

        return [
            'id' => $m->id,
            'name' => (string) ($s['name'] ?? ''),
            'unit' => $m->unit,
            'location' => (string) ($s['location'] ?? ''),
            'icon' => $m->icon,
            'readings' => $readings,
        ];
    }

    public function formatReading(MeterReading $r, Household $household): array
    {
        $s = $this->readingResolved($r, $household);

        return [
            'id' => $r->id,
            'meter_id' => $r->meter_id,
            'value' => (float) ($s['value'] ?? 0),
            'consumption' => (float) ($s['consumption'] ?? 0),
            'date' => $r->date,
            'month' => $r->month,
            'year' => $r->year,
            'is_reset' => (bool) $r->is_reset,
            'is_estimated' => (bool) $r->is_estimated,
            'is_official' => (bool) $r->is_official,
        ];
    }
}
