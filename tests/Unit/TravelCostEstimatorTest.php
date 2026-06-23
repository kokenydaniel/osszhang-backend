<?php

namespace Tests\Unit;

use App\Services\Travel\TravelCostEstimator;
use Tests\TestCase;

class TravelCostEstimatorTest extends TestCase
{
    public function test_plane_minimum_is_realistic_not_trivial(): void
    {
        $estimator = new TravelCostEstimator();
        $result = $estimator->estimate([
            'destination' => 'London',
            'origin_location' => 'Budapest',
            'duration_days' => 5,
            'travelers_count' => 2,
            'transport_mode' => 'plane',
            'transport_already_booked' => false,
        ]);

        $this->assertGreaterThan(100000, (float) $result['breakdown_floor']['transport']);
        $this->assertGreaterThan(300000, (float) $result['total_minimum_huf']);
    }

    public function test_car_includes_fuel_and_distance(): void
    {
        $estimator = new TravelCostEstimator();
        $result = $estimator->estimate([
            'destination' => 'Bécs',
            'origin_location' => 'Budapest',
            'duration_days' => 3,
            'travelers_count' => 4,
            'transport_mode' => 'car',
            'car_fuel_consumption_l100' => 7.5,
            'transport_already_booked' => false,
        ]);

        $detail = $result['transport_detail'];
        $this->assertSame('car', $detail['mode']);
        $this->assertGreaterThan(0, (float) $detail['fuel_cost_huf']);
        $this->assertGreaterThan(100, (int) $detail['estimated_distance_km']);
    }

    public function test_already_booked_transport_cost_is_zero(): void
    {
        $estimator = new TravelCostEstimator();
        $result = $estimator->estimate([
            'destination' => 'Róma',
            'duration_days' => 4,
            'transport_mode' => 'plane',
            'transport_already_booked' => true,
        ]);

        $this->assertSame(0.0, (float) $result['breakdown_floor']['transport']);
        $this->assertTrue((bool) $result['transport_detail']['already_booked']);
    }

    public function test_already_booked_accommodation_cost_is_zero(): void
    {
        $estimator = new TravelCostEstimator();
        $result = $estimator->estimate([
            'destination' => 'Róma',
            'duration_days' => 4,
            'transport_mode' => 'plane',
            'accommodation_already_booked' => true,
        ]);

        $this->assertSame(0.0, (float) $result['breakdown_floor']['accommodation']);
        $this->assertTrue((bool) $result['accommodation_already_booked']);
    }
}
