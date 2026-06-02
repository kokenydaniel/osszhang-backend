<?php

namespace App\Console\Commands;

use App\Models\Household;
use App\Models\User;
use App\Services\ShopifyImportService;
use App\Support\BusinessSettings;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncShopifyImports extends Command
{
    protected $signature = 'shopify:sync-scheduled';

    protected $description = 'Import Shopify orders for households with automatic sync enabled';

    public function handle(ShopifyImportService $importService): int
    {
        $now = Carbon::now();
        $ran = 0;

        Household::query()
            ->where('business_enabled', true)
            ->where('shopify_import_enabled', true)
            ->whereNotNull('shopify_shop_url')
            ->whereNotNull('shopify_access_token')
            ->orderBy('id')
            ->chunkById(50, function ($households) use ($importService, $now, &$ran) {
                foreach ($households as $household) {
                    $settings = $household->resolvedBusinessSettings();
                    $schedule = $settings['shopify_sync_schedule'] ?? 'off';
                    $interval = BusinessSettings::syncIntervalMinutes($schedule);
                    if ($interval === null) {
                        continue;
                    }

                    $lastSynced = $settings['shopify_last_synced_at'] ?? null;
                    if ($lastSynced) {
                        try {
                            $last = Carbon::parse($lastSynced);
                            if ($last->diffInMinutes($now) < $interval) {
                                continue;
                            }
                        } catch (\Throwable) {
                            // proceed with sync
                        }
                    }

                    $user = User::query()
                        ->where('household_id', $household->id)
                        ->where('role', 'admin')
                        ->orderBy('id')
                        ->first()
                        ?? User::query()->where('household_id', $household->id)->orderBy('id')->first();

                    if (! $user) {
                        continue;
                    }

                    $result = $importService->import($user);
                    if (($result['success'] ?? false) === true) {
                        $merged = array_merge($settings, [
                            'shopify_last_synced_at' => $now->toIso8601String(),
                        ]);
                        $household->business_settings = BusinessSettings::resolve($merged);
                        $household->saveQuietly();
                        $ran++;
                        $this->line("Synced household #{$household->id}");
                    }
                }
            });

        $this->info("Shopify sync finished ({$ran} household(s)).");

        return self::SUCCESS;
    }
}
