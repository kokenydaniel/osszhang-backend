<?php

namespace App\Console\Commands;

use App\Services\WalletProvisioningService;
use Illuminate\Console\Command;

class MigrateLegacyWallets extends Command
{
    protected $signature = 'wallets:migrate-legacy';

    protected $description = 'Közös kassza létrehozása minden háztartáshoz és meglévő tranzakciók átkötése';

    public function handle(WalletProvisioningService $provisioning): int
    {
        $this->info('Kasszák migrálása indul...');

        $stats = $provisioning->migrateLegacyData();

        $this->newLine();
        $this->line("  Háztartások feldolgozva: {$stats['households']}");
        $this->line("  Új közös kasszák:       {$stats['wallets_created']}");
        $this->line("  Tranzakciók átkötve:    {$stats['transactions_linked']}");
        $this->newLine();

        try {
            $provisioning->assertAllTransactionsLinked();
            $this->info('Minden tranzakció rendelkezik wallet_id-val.');
        } catch (\RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
