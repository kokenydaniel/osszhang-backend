<?php

namespace App\Support;

class HufConverter
{

    private array $rates;

    public function __construct(?array $rateOverrides = null)
    {

        $defaults = config('exchange_rates.huf_per_unit', ['HUF' => 1]);
        $this->rates = ['HUF' => 1.0];

        foreach ($defaults as $currency => $rate) {
            $this->rates[strtoupper((string) $currency)] = (float) $rate;
        }

        if ($rateOverrides !== null) {
            foreach ($rateOverrides as $currency => $rate) {
                $normalized = strtoupper(trim((string) $currency));
                $numeric = (float) $rate;
                if ($normalized !== '' && $numeric > 0) {
                    $this->rates[$normalized] = $numeric;
                }
            }
        }
    }

    public function toHuf(float $amount, string $currency): float
    {
        $currency = strtoupper(trim($currency)) ?: 'HUF';
        if ($currency === 'HUF') {
            return $amount;
        }

        $perUnit = (float) ($this->rates[$currency] ?? 0);

        return $perUnit > 0 ? $amount * $perUnit : $amount;
    }

    public function rates(): array
    {
        return $this->rates;
    }
}
