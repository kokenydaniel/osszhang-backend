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

        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('SET CONSTRAINTS ALL DEFERRED');
        } elseif ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        }

        foreach ($tables as $table) {
            if (isset($data[$table])) {
                $this->info("Importing table: {$table}");

                $rows = array_map(function($row) {
                    return (array) $row;
                }, $data[$table]);

                DB::table($table)->truncate();

                if (!empty($rows)) {
                    DB::table($table)->insert($rows);
                }
            }
        }

        if ($driver === 'pgsql') {
            $this->syncPostgresSequences($tables);
        } elseif ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON');
        }

        $this->info("Database imported successfully from {$file}");
    }

    private function syncPostgresSequences(array $tables): void
    {
        foreach ($tables as $table) {
            DB::statement(
                "SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE((SELECT MAX(id) FROM \"{$table}\"), 1), true)",
            );
            $this->line("Synced sequence: {$table}_id_seq");
        }
    }
}
