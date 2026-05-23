<?php

namespace App\Console\Commands;

use Database\Seeders\DemoHouseholdSeeder;
use Illuminate\Console\Command;

class SeedDemoHousehold extends Command
{
    protected $signature = 'demo:seed {--fresh : Meglévő demo háztartás törlése és újralétrehozás}';

    protected $description = 'Összhang demo háztartás létrehozása mintaadatokkal (portfólió / tesztelés)';

    public function handle(DemoHouseholdSeeder $seeder): int
    {
        $fresh = (bool) $this->option('fresh');
        $existedBefore = $seeder->findDemoHousehold() !== null;

        $household = $seeder->run($fresh);

        if (! $fresh && $existedBefore) {
            $this->info('Demo háztartás már létezik — változatlan.');
        } else {
            $this->info('Demo háztartás elkészült.');
        }
        $this->newLine();
        $this->line('  Háztartás: '.DemoHouseholdSeeder::HOUSEHOLD_NAME);
        $this->line('  Meghívó kód: '.DemoHouseholdSeeder::INVITE_CODE);
        $this->newLine();
        $this->line('  Felhasználók (mindkettő jelszava: '.DemoHouseholdSeeder::PASSWORD.')');
        $this->line('    • demo  — admin, teljes hozzáférés');
        $this->line('    • viki  — tag, rezsi partner, teljes hozzáférés');
        $this->newLine();
        $this->line('  Modulok: költségvetés, rezsi, tartozások, megtakarítások, órák, vállalkozás');
        $this->line('  Háztartás ID: '.$household->id);

        return self::SUCCESS;
    }
}
