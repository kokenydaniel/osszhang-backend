<?php

namespace App\Console\Commands;

use App\Models\BusinessOrder;
use App\Models\Debt;
use App\Models\Household;
use App\Models\Investment;
use App\Models\LedgerEntry;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\Saving;
use App\Models\Transaction;
use App\Models\Utility;
use App\Models\UtilitySettlement;
use App\Services\EncryptedRecordService;
use App\Services\HouseholdCipherService;
use App\Services\TransactionSensitiveData;
use Illuminate\Console\Command;

class EncryptHouseholdData extends Command
{
    protected $signature = 'household:encrypt-all {--household=}';

    protected $description = 'Összes háztartási pénzügyi adat áttitkosítása (meglévő nyílt rekordok)';

    public function handle(
        EncryptedRecordService $crypto,
        TransactionSensitiveData $transactions,
        HouseholdCipherService $cipher,
    ): int {
        $query = Household::query();
        if ($id = $this->option('household')) {
            $query->where('id', $id);
        }

        $counts = [
            'transactions' => 0,
            'utilities' => 0,
            'settlements' => 0,
            'debts' => 0,
            'savings' => 0,
            'ledger' => 0,
            'investments' => 0,
            'meters' => 0,
            'readings' => 0,
            'business_orders' => 0,
            'households' => 0,
        ];

        foreach ($query->cursor() as $household) {
            $cipher->ensureCipherKey($household);

            if (empty($household->sensitive_encrypted)) {
                $crypto->persistHouseholdSensitive($household, $crypto->householdSensitive($household));
                $household->saveQuietly();
                $counts['households']++;
            }

            Transaction::where('household_id', $household->id)
                ->whereNull('encrypted_payload')
                ->with('items')
                ->get()
                ->each(function (Transaction $t) use ($transactions, $household, &$counts) {
                    $data = $transactions->resolve($t, $household);
                    $transactions->persistSensitive($t, $household, $data);
                    $t->save();
                    $counts['transactions']++;
                });

            Utility::where('household_id', $household->id)
                ->whereNull('encrypted_payload')
                ->get()
                ->each(function (Utility $u) use ($crypto, $household, &$counts) {
                    $crypto->persistUtility($u, $household, $crypto->utilityLegacy($u));
                    $u->save();
                    $counts['utilities']++;
                });

            UtilitySettlement::where('household_id', $household->id)
                ->whereNull('encrypted_payload')
                ->get()
                ->each(function (UtilitySettlement $s) use ($crypto, $household, &$counts) {
                    $crypto->persistSettlement($s, $household, $crypto->settlementLegacy($s));
                    $s->save();
                    $counts['settlements']++;
                });

            Debt::where('household_id', $household->id)
                ->whereNull('encrypted_payload')
                ->get()
                ->each(function (Debt $d) use ($crypto, $household, &$counts) {
                    $crypto->persistDebt($d, $household, $crypto->debtLegacy($d));
                    $d->save();
                    $counts['debts']++;
                });

            Saving::where('household_id', $household->id)
                ->whereNull('encrypted_payload')
                ->get()
                ->each(function (Saving $s) use ($crypto, $household, &$counts) {
                    $crypto->persistSaving($s, $household, $crypto->savingLegacy($s));
                    $s->save();
                    $counts['savings']++;
                });

            LedgerEntry::whereNotNull('saving_id')
                ->whereNull('encrypted_payload')
                ->whereIn('saving_id', Saving::where('household_id', $household->id)->pluck('id'))
                ->get()
                ->each(function (LedgerEntry $e) use ($crypto, $household, &$counts) {
                    $crypto->persistLedger($e, $household, $crypto->ledgerLegacy($e));
                    $e->save();
                    $counts['ledger']++;
                });

            Investment::where('household_id', $household->id)
                ->whereNull('encrypted_payload')
                ->get()
                ->each(function (Investment $i) use ($crypto, $household, &$counts) {
                    $crypto->persistInvestment($i, $household, $crypto->investmentLegacy($i));
                    $i->save();
                    $counts['investments']++;
                });

            Meter::where('household_id', $household->id)
                ->whereNull('encrypted_payload')
                ->get()
                ->each(function (Meter $m) use ($crypto, $household, &$counts) {
                    $crypto->persistMeter($m, $household, $crypto->meterLegacy($m));
                    $m->save();
                    $counts['meters']++;
                });

            $meterIds = Meter::where('household_id', $household->id)->pluck('id');
            MeterReading::whereIn('meter_id', $meterIds)
                ->whereNull('encrypted_payload')
                ->get()
                ->each(function (MeterReading $r) use ($crypto, $household, &$counts) {
                    $crypto->persistReading($r, $household, $crypto->readingLegacy($r));
                    $r->save();
                    $counts['readings']++;
                });

            BusinessOrder::where('household_id', $household->id)
                ->whereNull('encrypted_payload')
                ->get()
                ->each(function (BusinessOrder $o) use ($crypto, $household, &$counts) {
                    $crypto->persistBusinessOrder($o, $household, $crypto->businessOrderLegacy($o));
                    $o->save();
                    $counts['business_orders']++;
                });
        }

        $this->table(['Típus', 'Db'], collect($counts)->map(fn ($c, $k) => [$k, $c])->values()->all());

        return self::SUCCESS;
    }
}
