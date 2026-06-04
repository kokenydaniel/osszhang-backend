<?php

namespace App\Console\Commands;

use App\Support\StorageDisk;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class StorageProbeCommand extends Command
{
    protected $signature = 'storage:probe';

    protected $description = 'Ellenőrzi az object storage kapcsolatot';

    public function handle(): int
    {
        $this->line('default='.config('filesystems.default'));
        $this->line('configured='.(StorageDisk::objectStorageConfigured() ? 'yes' : 'no'));
        $this->line('region='.StorageDisk::region());
        $this->line('bucket='.(StorageDisk::bucket() ?? ''));
        $this->line('endpoint='.(StorageDisk::endpoint() ?? ''));

        if (! StorageDisk::objectStorageConfigured()) {
            return self::FAILURE;
        }

        StorageDisk::applyRuntimeConfig();
        Storage::forgetDisk('s3');

        $key = 'healthcheck/'.uniqid().'.txt';

        try {
            $written = Storage::disk('s3')->put($key, 'ok');
            $exists = Storage::disk('s3')->exists($key);
            $contents = Storage::disk('s3')->get($key);
            Storage::disk('s3')->delete($key);
            $this->line('put='.var_export($written, true));
            $this->line('exists='.($exists ? 'yes' : 'no'));
            $this->line('get='.($contents === 'ok' ? 'yes' : 'no'));

            return $written && $exists && $contents === 'ok' ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
