<?php

namespace App\Services;

use App\Integrations\SumUp\SumUpClient;
use App\Integrations\SumUp\SumUpPeriodActivity;
use App\Integrations\SumUp\SumUpReportExporter;
use App\Models\Household;
use App\Models\User;
use App\Support\AccessControl;
use App\Support\BusinessDocumentTypes;
use Carbon\Carbon;

class SumUpImportService
{
    public function __construct(
        private readonly SumUpClient $sumUp,
        private readonly SumUpReportExporter $exporter,
        private readonly BusinessDocumentService $documents,
    ) {}

    /** @return array{success: bool, message?: string, error?: string, status?: int, imported?: int} */
    public function importMonth(User $user, int $year, int $month): array
    {
        if (! AccessControl::canUseFeature($user, 'sumup_import')) {
            return [
                'success' => false,
                'error' => 'A SumUp import Premium előfizetéssel érhető el.',
                'status' => 403,
            ];
        }

        $household = $user->household;
        if (! $household?->sumup_import_enabled) {
            return [
                'success' => false,
                'error' => 'Kapcsold be a SumUp importot a Beállítások → Modulok → Vállalkozás menüben.',
                'status' => 400,
            ];
        }

        if (! $household->sumup_merchant_code || ! $household->sumup_api_key) {
            return [
                'success' => false,
                'error' => 'Add meg a SumUp Merchant kódot és API kulcsot a beállításokban.',
                'status' => 400,
            ];
        }

        $this->sumUp->setApiKey($household->sumup_api_key);

        $start = Carbon::create($year, $month, 1, 0, 0, 0, config('app.timezone', 'Europe/Budapest'));
        $end = $start->copy()->endOfMonth();
        $monthKey = sprintf('%04d-%02d', $year, $month);
        $periodLabel = $start->format('Y.m.d.').' – '.$end->format('Y.m.d.');
        $merchantCode = (string) $household->sumup_merchant_code;

        try {
            $transactions = $this->sumUp->listTransactionsForPeriod(
                $merchantCode,
                $start->copy()->utc()->toIso8601String(),
                $end->copy()->utc()->toIso8601String(),
            );

            $payouts = $this->sumUp->listPayoutsForPeriod(
                $merchantCode,
                $start->toDateString(),
                $end->toDateString(),
            );

            $hasSales = SumUpPeriodActivity::hasSalesTransactions($transactions);
            $hasPayouts = SumUpPeriodActivity::hasPayoutRecords($payouts);

            $this->documents->clearImportedSourceForMonth($household, $year, $month, 'sumup');

            $files = [];

            if ($hasSales) {
                $files[] = [
                    'name' => "sumup-tranzakciok-{$monthKey}.xls",
                    'key' => "sumup:transactions:{$monthKey}",
                    'mime' => 'application/vnd.ms-excel',
                    'label' => 'SumUp tranzakciók (XLS)',
                    'contents' => $this->exporter->transactionsXls($transactions, $periodLabel, $merchantCode),
                ];
                $files[] = [
                    'name' => "sumup-beveteli-jelentes-{$monthKey}.pdf",
                    'key' => "sumup:revenue:{$monthKey}",
                    'mime' => 'application/pdf',
                    'label' => 'SumUp bevételi jelentés (PDF)',
                    'contents' => $this->exporter->revenueReportPdf($transactions, $payouts, $periodLabel, $merchantCode),
                ];

                $receiptsPdf = $this->exporter->receiptsPdf(
                    $this->sumUp,
                    $merchantCode,
                    $transactions,
                    (int) config('sumup.max_receipts_per_import', 200),
                );
                if ($receiptsPdf !== null) {
                    $files[] = [
                        'name' => "sumup-nyugtak-{$monthKey}.pdf",
                        'key' => "sumup:receipts:{$monthKey}",
                        'mime' => 'application/pdf',
                        'label' => 'SumUp nyugták (PDF)',
                        'contents' => $receiptsPdf,
                    ];
                }
            }

            if ($hasPayouts) {
                $files[] = [
                    'name' => "sumup-kifizetesek-{$monthKey}.pdf",
                    'key' => "sumup:payouts:{$monthKey}",
                    'mime' => 'application/pdf',
                    'label' => 'SumUp kifizetések (PDF)',
                    'contents' => $this->exporter->payoutsPdf($payouts, $periodLabel, $merchantCode),
                ];
            }

            $imported = 0;
            foreach ($files as $file) {
                $imported += $this->storeImportFile(
                    $household,
                    $user,
                    $year,
                    $month,
                    $file['name'],
                    $file['key'],
                    $file['contents'],
                    $file['mime'],
                    $file['label'],
                );
            }

            $settings = $household->resolvedBusinessSettings();
            $settings['sumup_last_synced_at'] = now()->toIso8601String();
            $household->business_settings = $settings;
            $household->save();

            $message = $imported > 0
                ? "SumUp import kész: {$imported} fájl ({$monthKey})."
                : "SumUp import kész: ebben a hónapban nem volt SumUp forgalom vagy kifizetés ({$monthKey}).";

            return [
                'success' => true,
                'message' => $message,
                'imported' => $imported,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status' => 502,
            ];
        }
    }

    private function storeImportFile(
        Household $household,
        User $user,
        int $year,
        int $month,
        string $filename,
        string $importKey,
        string $contents,
        string $mime,
        string $label,
    ): int {
        $this->documents->storeFromContents(
            $household,
            $user,
            $year,
            $month,
            BusinessDocumentTypes::SUMUP_REPORT,
            $filename,
            $contents,
            $mime,
            'sumup',
            $importKey,
            $label,
        );

        return 1;
    }
}
