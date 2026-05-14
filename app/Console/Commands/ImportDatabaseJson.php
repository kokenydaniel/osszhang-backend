<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ImportDatabaseJson extends Command
{
    protected $signature = 'db:import-json {file=database_dump.json}';
    protected $description = 'Import database tables from a JSON file';

    public function handle()
    {
        $file = $this->argument('file');

        if (!File::exists($file)) {
            $this->error("File not found: {$file}");
            return;
        }

        $data = json_decode(File::get($file), true);

        // Order is important to respect foreign keys
        $tables = [
            'households',
            'users',
            'transactions',
            'utilities',
            'meters',
            'meter_readings',
            'debts',
            'savings',
            'ledger_entries',
            'business_orders',
            'settings'
        ];

        DB::statement('SET CONSTRAINTS ALL DEFERRED'); // For Postgres

        foreach ($tables as $table) {
            if (isset($data[$table])) {
                $this->info("Importing table: {$table}");
                
                // Convert objects to arrays if needed
                $rows = array_map(function($row) {
                    return (array) $row;
                }, $data[$table]);

                DB::table($table)->truncate();
                
                if (!empty($rows)) {
                    DB::table($table)->insert($rows);
                }
            }
        }

        $this->info("Database imported successfully from {$file}");
    }
}
