<?php

namespace App\Services;

use App\Models\BusinessOrder;
use App\Models\Debt;
use App\Models\Household;
use App\Models\Investment;
use App\Models\LedgerEntry;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\Saving;
use App\Models\Utility;
use App\Models\UtilitySettlement;
use App\Services\Formatters\BusinessOrderRecordFormatter;
use App\Services\Formatters\DebtRecordFormatter;
use App\Services\Formatters\InvestmentRecordFormatter;
use App\Services\Formatters\MeterRecordFormatter;
use App\Services\Formatters\SavingRecordFormatter;
use App\Services\Formatters\UtilityRecordFormatter;
use Illuminate\Support\Facades\Log;

class EncryptedRecordService
{
    public function __construct(
        private readonly HouseholdCipherService $cipher,
        private readonly UtilityRecordFormatter $utilityFormatter,
        private readonly DebtRecordFormatter $debtFormatter,
        private readonly SavingRecordFormatter $savingFormatter,
        private readonly InvestmentRecordFormatter $investmentFormatter,
        private readonly MeterRecordFormatter $meterFormatter,
        private readonly BusinessOrderRecordFormatter $businessOrderFormatter,
    ) {}

    public function ensureKey(Household $household): void
    {
        $this->cipher->ensureCipherKey($household);
    }

    private function decrypt(Household $household, ?string $blob): ?array
    {
        if (! $blob) {
            return null;
        }

        try {
            return $this->cipher->decrypt($household, $blob);
        } catch (\Throwable $e) {
            Log::warning('household.decrypt_failed', [
                'household_id' => $household->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function resolve(Household $household, ?string $blob, array $legacy): array
    {
        return $this->decrypt($household, $blob) ?? $legacy;
    }

    public function householdSensitive(Household $household): array
    {
        $legacy = [
            'manual_balance' => (float) $household->manual_balance,
            'utility_templates' => $household->resolvedUtilityTemplates(),
        ];

        return $this->resolve($household, $household->sensitive_encrypted ?? null, $legacy);
    }

    public function persistHouseholdSensitive(Household $household, array $sensitive): void
    {
        $this->ensureKey($household);
        $household->sensitive_encrypted = $this->cipher->encrypt($household, $sensitive);
        $household->manual_balance = 0;
        $household->utility_templates = [];
    }

    public function resolvedManualBalance(Household $household): float
    {
        $shared = app(WalletProvisioningService::class)->sharedWalletForHousehold($household);

        return (float) ($shared->manual_balance ?? 0);
    }

    public function adjustManualBalance(Household $household, float $delta): void
    {
        $wallet = app(WalletProvisioningService::class)->sharedWalletForHousehold($household);
        $wallet->manual_balance = (float) ($wallet->manual_balance ?? 0) + $delta;
        $wallet->save();
    }

    public function utilityLegacy(Utility $u): array
    {
        return $this->utilityFormatter->utilityLegacy($u);
    }

    public function utilityResolved(Utility $u, Household $household): array
    {
        return $this->utilityFormatter->utilityResolved($u, $household);
    }

    public function persistUtility(Utility $u, Household $household, array $sensitive): void
    {
        $this->utilityFormatter->persistUtility($u, $household, $sensitive);
    }

    public function formatUtility(Utility $u, Household $household): array
    {
        return $this->utilityFormatter->formatUtility($u, $household);
    }

    public function settlementLegacy(UtilitySettlement $s): array
    {
        return $this->utilityFormatter->settlementLegacy($s);
    }

    public function settlementResolved(UtilitySettlement $s, Household $household): array
    {
        return $this->utilityFormatter->settlementResolved($s, $household);
    }

    public function persistSettlement(UtilitySettlement $s, Household $household, array $sensitive): void
    {
        $this->utilityFormatter->persistSettlement($s, $household, $sensitive);
    }

    public function debtLegacy(Debt $d): array
    {
        return $this->debtFormatter->debtLegacy($d);
    }

    public function debtResolved(Debt $d, Household $household): array
    {
        return $this->debtFormatter->debtResolved($d, $household);
    }

    public function persistDebt(Debt $d, Household $household, array $sensitive): void
    {
        $this->debtFormatter->persistDebt($d, $household, $sensitive);
    }

    public function formatDebt(Debt $d, Household $household): array
    {
        return $this->debtFormatter->formatDebt($d, $household);
    }

    public function savingLegacy(Saving $saving): array
    {
        return $this->savingFormatter->savingLegacy($saving);
    }

    public function savingResolved(Saving $saving, Household $household): array
    {
        return $this->savingFormatter->savingResolved($saving, $household);
    }

    public function persistSaving(Saving $saving, Household $household, array $sensitive): void
    {
        $this->savingFormatter->persistSaving($saving, $household, $sensitive);
    }

    public function ledgerLegacy(LedgerEntry $entry): array
    {
        return $this->savingFormatter->ledgerLegacy($entry);
    }

    public function ledgerResolved(LedgerEntry $entry, Household $household): array
    {
        return $this->savingFormatter->ledgerResolved($entry, $household);
    }

    public function persistLedger(LedgerEntry $entry, Household $household, array $sensitive): void
    {
        $this->savingFormatter->persistLedger($entry, $household, $sensitive);
    }

    public function formatSaving(Saving $saving, Household $household): array
    {
        return $this->savingFormatter->formatSaving($saving, $household);
    }

    public function formatLedgerEntry(LedgerEntry $entry, Household $household): array
    {
        return $this->savingFormatter->formatLedgerEntry($entry, $household);
    }

    public function investmentLegacy(Investment $i): array
    {
        return $this->investmentFormatter->investmentLegacy($i);
    }

    public function investmentResolved(Investment $i, Household $household): array
    {
        return $this->investmentFormatter->investmentResolved($i, $household);
    }

    public function persistInvestment(Investment $i, Household $household, array $sensitive): void
    {
        $this->investmentFormatter->persistInvestment($i, $household, $sensitive);
    }

    public function formatInvestment(Investment $i, Household $household): array
    {
        return $this->investmentFormatter->formatInvestment($i, $household);
    }

    public function meterLegacy(Meter $m): array
    {
        return $this->meterFormatter->meterLegacy($m);
    }

    public function meterResolved(Meter $m, Household $household): array
    {
        return $this->meterFormatter->meterResolved($m, $household);
    }

    public function persistMeter(Meter $m, Household $household, array $sensitive): void
    {
        $this->meterFormatter->persistMeter($m, $household, $sensitive);
    }

    public function readingLegacy(MeterReading $r): array
    {
        return $this->meterFormatter->readingLegacy($r);
    }

    public function readingResolved(MeterReading $r, Household $household): array
    {
        return $this->meterFormatter->readingResolved($r, $household);
    }

    public function persistReading(MeterReading $r, Household $household, array $sensitive): void
    {
        $this->meterFormatter->persistReading($r, $household, $sensitive);
    }

    public function formatMeter(Meter $m, Household $household): array
    {
        return $this->meterFormatter->formatMeter($m, $household);
    }

    public function formatReading(MeterReading $r, Household $household): array
    {
        return $this->meterFormatter->formatReading($r, $household);
    }

    public function businessOrderLegacy(BusinessOrder $o): array
    {
        return $this->businessOrderFormatter->businessOrderLegacy($o);
    }

    public function businessOrderResolved(BusinessOrder $o, Household $household): array
    {
        return $this->businessOrderFormatter->businessOrderResolved($o, $household);
    }

    public function persistBusinessOrder(BusinessOrder $o, Household $household, array $sensitive): void
    {
        $this->businessOrderFormatter->persistBusinessOrder($o, $household, $sensitive);
    }

    public function formatBusinessOrder(BusinessOrder $o, Household $household): array
    {
        return $this->businessOrderFormatter->formatBusinessOrder($o, $household);
    }
}
