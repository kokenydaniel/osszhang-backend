<?php

namespace App\Console\Commands;

use App\Models\Household;
use App\Models\Transaction;
use App\Services\HouseholdCipherService;
use App\Services\TransactionSensitiveData;
use Illuminate\Console\Command;

class EncryptHouseholdTransactions extends Command
{
    protected $signature = 'household:encrypt-transactions {--household=}';

    protected $description = 'Meglévő nyílt tranzakciók áttitkosítása háztartásonként';

    public function handle(TransactionSensitiveData $sensitive, HouseholdCipherService $cipher): int
    {
        $query = Household::query();
        if ($id = $this->option('household')) {
            $query->where('id', $id);
        }

        $count = 0;
        foreach ($query->cursor() as $household) {
            $cipher->ensureCipherKey($household);
            $transactions = Transaction::where('household_id', $household->id)
                ->whereNull('encrypted_payload')
                ->with('items')
                ->get();

            foreach ($transactions as $transaction) {
                $data = $sensitive->resolve($transaction, $household);
                $sensitive->persistSensitive($transaction, $household, $data);
                $transaction->save();
                $count++;
            }
        }

        $this->info("Titkosítva: {$count} tranzakció.");

        return self::SUCCESS;
    }
}
