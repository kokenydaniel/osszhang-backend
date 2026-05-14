<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ExportDatabaseJson extends Command
{
    protected $signature = 'db:export-json {file=database_dump.json}';
    protected $description = 'Export relevant database tables to a JSON file';

    public function handle()
    {
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

        $data = [];

        foreach ($tables as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                $this->info("Exporting table: {$table}");
                $data[$table] = DB::table($table)->get()->toArray();
            }
        }

        File::put($this->argument('file'), json_encode($data, JSON_PRETTY_PRINT));
        $this->info("Database exported to " . $this->argument('file'));
    }
}
