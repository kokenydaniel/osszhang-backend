<?php

namespace App\Console\Commands;

use App\Models\Attachment;
use App\Models\BusinessDocument;
use App\Models\UserFeedbackReport;
use App\Models\UserFeedbackReportAttachment;
use App\Support\StorageDisk;
use App\Support\StorageLocator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateLocalFilesToObjectStorage extends Command
{
    protected $signature = 'storage:migrate-local-to-s3';

    protected $description = 'Meglévő fájlok másolása object storage-ba (forrás megmarad)';

    public function handle(): int
    {
        if (! StorageDisk::objectStorageConfigured()) {
            $this->error('Object storage nincs konfigurálva.');

            return self::FAILURE;
        }

        $targetDisk = StorageDisk::default();
        $target = Storage::disk($targetDisk);
        $copied = 0;
        $skipped = 0;

        foreach ($this->fileRecords() as $record) {
            $path = $record['path'];
            $storedDisk = $record['disk'];

            if ($target->exists($path)) {
                if ($record['model']->disk !== $targetDisk) {
                    $record['model']->update(['disk' => $targetDisk]);
                }
                $skipped++;

                continue;
            }

            $source = StorageLocator::forPath($storedDisk, $path);
            if (! $source->exists($path)) {
                $skipped++;

                continue;
            }

            $stream = $source->readStream($path);
            if ($stream === false) {
                $skipped++;

                continue;
            }

            $target->writeStream($path, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            if (! $target->exists($path)) {
                $skipped++;

                continue;
            }

            $record['model']->update(['disk' => $targetDisk]);
            $copied++;
        }

        $this->info("Másolva: {$copied}, kihagyva: {$skipped}");

        return self::SUCCESS;
    }

    /** @return \Generator<int, array{model: object, disk: string, path: string}> */
    private function fileRecords(): \Generator
    {
        foreach (Attachment::query()->whereNotNull('path')->cursor() as $row) {
            yield ['model' => $row, 'disk' => $row->disk, 'path' => $row->path];
        }

        foreach (BusinessDocument::query()->whereNotNull('path')->cursor() as $row) {
            yield ['model' => $row, 'disk' => $row->disk, 'path' => $row->path];
        }

        foreach (UserFeedbackReportAttachment::query()->whereNotNull('path')->cursor() as $row) {
            yield ['model' => $row, 'disk' => $row->disk, 'path' => $row->path];
        }

        foreach (UserFeedbackReport::query()->whereNotNull('path')->whereNotNull('disk')->cursor() as $row) {
            yield ['model' => $row, 'disk' => $row->disk, 'path' => $row->path];
        }
    }
}
