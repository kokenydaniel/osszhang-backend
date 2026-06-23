<?php

namespace App\Services\Travel;

/**
 * Deterministic minimum travel cost floors — the AI must not undercut these totals.
 */
class TravelCostEstimator
{
    /** @var array<string, float> km estimates from Budapest unless both cities matched */
    private const ROUTE_KM = [
        'balaton' => 120,
        'debrecen' => 230,
        'szeged' => 175,
        'pécs' => 200,
        'pecs' => 200,
        'győr' => 120,
        'gyor' => 120,
        'vienna' => 250,
        'bécs' => 250,
        'becs' => 250,
        'bratislava' => 200,
        'pozsony' => 200,
        'prague' => 530,
        'prága' => 530,
        'praga' => 530,
        'berlin' => 1100,
        'munich' => 650,
        'münchen' => 650,
        'munchen' => 650,
        'rome' => 1200,
        'róma' => 1200,
        'roma' => 1200,
        'paris' => 1450,
        'párizs' => 1450,
        'parizs' => 1450,
        'barcelona' => 1900,
        'amsterdam' => 1350,
        'london' => 1700,
        'zürich' => 850,
        'zurich' => 850,
        'split' => 750,
        'dubrovnik' => 850,
        'athens' => 1500,
        'athén' => 1500,
        'athen' => 1500,
        'istanbul' => 1100,
        'isztanbul' => 1100,
    ];

    /** @var array<string, float> HUF per litre (2025–2026 realistic averages) */
    private const FUEL_HUF_PER_L = [
        'hu' => 650,
        'at' => 620,
        'sk' => 580,
        'cz' => 560,
        'de' => 640,
        'it' => 680,
        'hr' => 600,
        'default' => 620,
    ];

    /**
     * @param  array{
     *   destination: string,
     *   origin_location?: string|null,
     *   duration_days: int,
     *   travelers_count?: int,
     *   trip_style?: string,
     *   accommodation_preference?: string,
     *   transport_mode?: string,
     *   transport_already_booked?: bool,
     *   accommodation_already_booked?: bool,
     *   car_fuel_consumption_l100?: float|null,
     * }  $input
     * @return array<string, mixed>
     */
    public function estimate(array $input): array
    {
        $destination = trim($input['destination']);
        $origin = trim((string) ($input['origin_location'] ?? 'Budapest'));
        $days = max(1, (int) ($input['duration_days'] ?? 1));
        $travelers = max(1, (int) ($input['travelers_count'] ?? 1));
        $mode = (string) ($input['transport_mode'] ?? 'mixed');
        $alreadyBooked = (bool) ($input['transport_already_booked'] ?? false);
        $accommodationAlreadyBooked = (bool) ($input['accommodation_already_booked'] ?? false);
        $consumption = (float) ($input['car_fuel_consumption_l100'] ?? 7.0);
        if ($consumption < 3 || $consumption > 25) {
            $consumption = 7.0;
        }

        $tier = $this->destinationTier($destination);
        $transport = $this->estimateTransport(
            $mode,
            $origin,
            $destination,
            $days,
            $travelers,
            $alreadyBooked,
            $consumption,
            $tier,
        );

        $daily = $this->estimateDailyCosts(
            $tier,
            (string) ($input['accommodation_preference'] ?? 'mixed'),
            (string) ($input['trip_style'] ?? 'mixed'),
            $travelers,
        );

        $accommodation = $accommodationAlreadyBooked
            ? 0.0
            : round($daily['accommodation'] * $days, 2);
        $food = round($daily['food'] * $days, 2);
        $activities = round($daily['activities'] * $days, 2);
        $insurance = round($this->travelInsuranceMinimum($days, $travelers, $tier), 2);
        $miscellaneous = round($daily['miscellaneous'] * $days, 2);

        $transportCost = (float) ($transport['estimated_cost'] ?? 0);
        $breakdownFloor = [
            'transport' => $transportCost,
            'accommodation' => $accommodation,
            'food' => $food,
            'activities' => $activities,
            'insurance' => $insurance,
            'miscellaneous' => $miscellaneous,
        ];

        $totalMinimum = round(array_sum($breakdownFloor), 2);
        $dailyMinimum = round($totalMinimum / $days, 2);

        return [
            'destination_tier' => $tier,
            'transport_detail' => $transport,
            'accommodation_already_booked' => $accommodationAlreadyBooked,
            'daily_minimum_huf' => $dailyMinimum,
            'total_minimum_huf' => $totalMinimum,
            'breakdown_floor' => $breakdownFloor,
            'travelers_count' => $travelers,
            'notes' => $transport['notes'] ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function estimateTransport(
        string $mode,
        string $origin,
        string $destination,
        int $days,
        int $travelers,
        bool $alreadyBooked,
        float $consumptionL100,
        string $tier,
    ): array {
        $mode = in_array($mode, ['car', 'plane', 'train', 'bus', 'mixed'], true) ? $mode : 'mixed';

        if ($alreadyBooked) {
            return [
                'mode' => $mode,
                'already_booked' => true,
                'estimated_cost' => 0.0,
                'description' => 'A közlekedés már lefoglalva / külön elszámolva — a terv nem számol extra utazási költséget.',
                'notes' => ['A szállás, étkezés és programok költségét továbbra is realisztikusan becsüld.'],
            ];
        }

        return match ($mode) {
            'car' => $this->estimateCarTransport($origin, $destination, $days, $travelers, $consumptionL100, $tier),
            'plane' => $this->estimatePlaneTransport($destination, $travelers, $tier),
            'train' => $this->estimateTrainTransport($origin, $destination, $travelers, $tier),
            'bus' => $this->estimateBusTransport($origin, $destination, $travelers, $tier),
            default => $this->estimateMixedTransport($origin, $destination, $days, $travelers, $consumptionL100, $tier),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function estimateCarTransport(
        string $origin,
        string $destination,
        int $days,
        int $travelers,
        float $consumptionL100,
        string $tier,
    ): array {
        $oneWayKm = $this->estimateRouteKm($origin, $destination);
        $roundTripKm = $oneWayKm * 2;
        $localKm = min(450, max(80, $days * 35));
        $totalKm = $roundTripKm + $localKm;

        $fuelRegion = $this->fuelRegionForDestination($destination);
        $fuelPrice = self::FUEL_HUF_PER_L[$fuelRegion] ?? self::FUEL_HUF_PER_L['default'];
        $liters = ($totalKm / 100) * $consumptionL100;
        $fuelCost = round($liters * $fuelPrice, 2);

        $tolls = $this->estimateTolls($origin, $destination, $oneWayKm, $tier);
        $parking = round(max(1500, $days * ($tier === 'local' ? 1200 : 2500)), 2);
        $total = round($fuelCost + $tolls + $parking, 2);

        return [
            'mode' => 'car',
            'already_booked' => false,
            'estimated_cost' => $total,
            'estimated_distance_km' => (int) round($totalKm),
            'one_way_distance_km' => (int) round($oneWayKm),
            'fuel_liters' => round($liters, 1),
            'fuel_price_per_liter_huf' => $fuelPrice,
            'fuel_cost_huf' => $fuelCost,
            'tolls_and_parking_huf' => round($tolls + $parking, 2),
            'car_fuel_consumption_l100' => $consumptionL100,
            'description' => sprintf(
                'Autóval ~%s km (oda-vissza + helyi %s km), %.1f l üzemanyag (~%s Ft/l), útdíj/parkolás.',
                number_format($totalKm, 0, '', ' '),
                number_format($localKm, 0, '', ' '),
                $liters,
                number_format($fuelPrice, 0, '', ' '),
            ),
            'notes' => [
                'Az üzemanyagár országonként eltér; a fogyasztást a megadott l/100 km alapján számoltuk.',
                'Nagyobb családnál ('.$travelers.' fő) autó gyakran olcsóbb, mint több repjegy.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function estimatePlaneTransport(string $destination, int $travelers, string $tier): array
    {
        $perPerson = match ($tier) {
            'premium' => 280000,
            'mid' => 95000,
            'local' => 45000,
            default => 65000,
        };

        if ($this->isDomesticHungary($destination)) {
            $perPerson = 35000;
        }

        $total = round($perPerson * $travelers, 2);

        return [
            'mode' => 'plane',
            'already_booked' => false,
            'estimated_cost' => $total,
            'per_person_huf' => $perPerson,
            'description' => sprintf(
                'Repülőjegy becslés (oda-vissza, %d fő): ~%s Ft/fő — realisztikus budget/low-cost ársáv, nem promóciós akció.',
                $travelers,
                number_format($perPerson, 0, '', ' '),
            ),
            'notes' => [
                'Tartalmazza a tipikus repülőtéri illetékeket és egy közepes poggyász díját.',
                'Csúcsidőben vagy közvetlen járatnál magasabb is lehet — ne tervezz 5 000 Ft-os repülővel.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function estimateTrainTransport(string $origin, string $destination, int $travelers, string $tier): array
    {
        $oneWayKm = $this->estimateRouteKm($origin, $destination);
        $perPersonOneWay = match (true) {
            $oneWayKm <= 300 => 6500,
            $oneWayKm <= 600 => 12000,
            $oneWayKm <= 1000 => 22000,
            default => 35000,
        };

        if ($tier === 'premium') {
            $perPersonOneWay = (int) round($perPersonOneWay * 1.35);
        }

        $total = round($perPersonOneWay * 2 * $travelers, 2);

        return [
            'mode' => 'train',
            'already_booked' => false,
            'estimated_cost' => $total,
            'per_person_return_huf' => $perPersonOneWay * 2,
            'description' => sprintf(
                'Vonat oda-vissza (%d fő), ~%s km távolság alapján.',
                $travelers,
                number_format($oneWayKm, 0, '', ' '),
            ),
            'notes' => ['InterCity/Railjet szintű árak; alvókupe vagy last minute drágíthat.'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function estimateBusTransport(string $origin, string $destination, int $travelers, string $tier): array
    {
        $oneWayKm = $this->estimateRouteKm($origin, $destination);
        $perPersonOneWay = match (true) {
            $oneWayKm <= 300 => 4500,
            $oneWayKm <= 600 => 9000,
            $oneWayKm <= 1000 => 16000,
            default => 24000,
        };

        $total = round($perPersonOneWay * 2 * $travelers, 2);

        return [
            'mode' => 'bus',
            'already_booked' => false,
            'estimated_cost' => $total,
            'per_person_return_huf' => $perPersonOneWay * 2,
            'description' => sprintf('Busz oda-vissza (%d fő).', $travelers),
            'notes' => ['Hosszú távú nemzetközi busz — FlixBus szintű tarifa.'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function estimateMixedTransport(
        string $origin,
        string $destination,
        int $days,
        int $travelers,
        float $consumptionL100,
        string $tier,
    ): array {
        $car = $this->estimateCarTransport($origin, $destination, $days, $travelers, $consumptionL100, $tier);
        $plane = $this->estimatePlaneTransport($destination, $travelers, $tier);
        $cheaper = ((float) $car['estimated_cost']) <= ((float) $plane['estimated_cost']) ? $car : $plane;

        return [
            ...$cheaper,
            'mode' => 'mixed',
            'description' => 'Vegyes mód: a realisztikusabb autó- vagy repülős becslés minimumát használjuk.',
            'notes' => array_merge($cheaper['notes'] ?? [], [
                'Autó: '.number_format((float) $car['estimated_cost'], 0, '', ' ').' Ft, repülő: '
                .number_format((float) $plane['estimated_cost'], 0, '', ' ').' Ft.',
            ]),
        ];
    }

    /**
     * @return array{accommodation: float, food: float, activities: float, miscellaneous: float}
     */
    private function estimateDailyCosts(
        string $tier,
        string $accommodation,
        string $tripStyle,
        int $travelers,
    ): array {
        $base = match ($tier) {
            'premium' => ['accommodation' => 42000, 'food' => 18000, 'activities' => 22000, 'miscellaneous' => 5000],
            'mid' => ['accommodation' => 26000, 'food' => 12000, 'activities' => 14000, 'miscellaneous' => 3500],
            'local' => ['accommodation' => 14000, 'food' => 8000, 'activities' => 7000, 'miscellaneous' => 2000],
            default => ['accommodation' => 22000, 'food' => 11000, 'activities' => 12000, 'miscellaneous' => 3000],
        };

        $accMult = match ($accommodation) {
            'hostel' => 0.65,
            'hotel' => 1.25,
            'apartment' => 1.05,
            default => 1.0,
        };

        $styleMult = match ($tripStyle) {
            'beach' => 1.05,
            'city' => 1.1,
            'adventure' => 1.2,
            'domestic' => 0.85,
            default => 1.0,
        };

        $sharedAcc = $travelers > 1 ? 1 + (($travelers - 1) * 0.55) : 1;
        $perPersonFood = $travelers;

        return [
            'accommodation' => round($base['accommodation'] * $accMult * $styleMult * $sharedAcc, 2),
            'food' => round($base['food'] * $styleMult * $perPersonFood, 2),
            'activities' => round($base['activities'] * $styleMult * max(1, $travelers * 0.85), 2),
            'miscellaneous' => round($base['miscellaneous'] * max(1, $travelers * 0.5), 2),
        ];
    }

    private function travelInsuranceMinimum(int $days, int $travelers, string $tier): float
    {
        if ($tier === 'local') {
            return 0;
        }

        return max(4000, $days * 900) * max(1, min($travelers, 4) * 0.85);
    }

    private function estimateRouteKm(string $origin, string $destination): float
    {
        $dest = mb_strtolower($destination);
        foreach (self::ROUTE_KM as $city => $km) {
            if (str_contains($dest, $city)) {
                return (float) $km;
            }
        }

        if ($this->isDomesticHungary($destination)) {
            return 180;
        }

        return match ($this->destinationTier($destination)) {
            'premium' => 1600,
            'mid' => 900,
            default => 700,
        };
    }

    private function estimateTolls(string $origin, string $destination, float $oneWayKm, string $tier): float
    {
        $tolls = 0.0;

        if ($oneWayKm > 80) {
            $tolls += 5200;
        }

        if (! $this->isDomesticHungary($destination)) {
            $tolls += match ($tier) {
                'premium' => 28000,
                'mid' => 18000,
                default => 12000,
            };
        }

        return round($tolls * 2, 2);
    }

    private function fuelRegionForDestination(string $destination): string
    {
        $dest = mb_strtolower($destination);
        if ($this->isDomesticHungary($destination)) {
            return 'hu';
        }
        if (str_contains($dest, 'ausztria') || str_contains($dest, 'bécs') || str_contains($dest, 'vienna')) {
            return 'at';
        }
        if (str_contains($dest, 'szlovák') || str_contains($dest, 'pozsony') || str_contains($dest, 'bratislava')) {
            return 'sk';
        }
        if (str_contains($dest, 'cseh') || str_contains($dest, 'prága') || str_contains($dest, 'prague')) {
            return 'cz';
        }
        if (str_contains($dest, 'német') || str_contains($dest, 'german') || str_contains($dest, 'berlin') || str_contains($dest, 'münchen')) {
            return 'de';
        }
        if (str_contains($dest, 'olasz') || str_contains($dest, 'ital') || str_contains($dest, 'rome') || str_contains($dest, 'róma')) {
            return 'it';
        }
        if (str_contains($dest, 'horvát') || str_contains($dest, 'croat') || str_contains($dest, 'split') || str_contains($dest, 'dubrovnik')) {
            return 'hr';
        }

        return 'default';
    }

    private function destinationTier(string $destination): string
    {
        $dest = mb_strtolower($destination);
        $premium = ['london', 'párizs', 'paris', 'zürich', 'zurich', 'new york', 'tokió', 'tokyo', 'dubai', 'ibiza', 'milánó', 'milan'];
        $mid = ['berlin', 'bécs', 'vienna', 'róma', 'rome', 'barcelona', 'amsterdam', 'prága', 'prague', 'split', 'dubrovnik', 'athens', 'athén'];
        $local = ['budapest', 'balaton', 'debrecen', 'szeged', 'pecs', 'pécs', 'győr', 'magyarország', 'hungary', 'hévíz', 'heviz', 'siófok', 'siofok'];

        foreach ($premium as $city) {
            if (str_contains($dest, $city)) {
                return 'premium';
            }
        }
        foreach ($mid as $city) {
            if (str_contains($dest, $city)) {
                return 'mid';
            }
        }
        foreach ($local as $city) {
            if (str_contains($dest, $city)) {
                return 'local';
            }
        }

        return 'default';
    }

    private function isDomesticHungary(string $destination): bool
    {
        return $this->destinationTier($destination) === 'local';
    }
}
