<?php

namespace App\Integrations\SumUp;

use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xls;

class SumUpReportExporter
{

    public function transactionsXls(array $transactions, string $periodLabel, string $merchantCode): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Tranzakciók');

        $headers = [
            'Dátum',
            'Tranzakció kód',
            'Típus',
            'Státusz',
            'Összeg',
            'Deviza',
            'Fizetés típus',
            'Visszatérített',
            'Kifizetés dátuma',
            'Felhasználó',
            'Termék / megjegyzés',
        ];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:K1')->getFont()->setBold(true);
        $sheet->getStyle('A1:K1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E8EEF4');

        $row = 2;
        foreach ($transactions as $tx) {
            $sheet->fromArray([
                $this->formatLocalDateTime((string) ($tx['timestamp'] ?? '')),
                (string) ($tx['transaction_code'] ?? ''),
                (string) ($tx['type'] ?? ''),
                (string) ($tx['status'] ?? ''),
                $this->numericCell($tx['amount'] ?? null),
                (string) ($tx['currency'] ?? ''),
                (string) ($tx['payment_type'] ?? ''),
                $this->numericCell($tx['refunded_amount'] ?? 0),
                (string) ($tx['payout_date'] ?? ''),
                (string) ($tx['user'] ?? ''),
                (string) ($tx['product_summary'] ?? ''),
            ], null, 'A'.$row);
            $row++;
        }

        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $metaRow = $row + 1;
        $sheet->setCellValue('A'.$metaRow, 'Időszak: '.$periodLabel);
        $sheet->setCellValue('A'.($metaRow + 1), 'Merchant: '.$merchantCode);
        $sheet->setCellValue('A'.($metaRow + 2), 'Exportálva: '.now()->timezone($this->timezone())->format('Y-m-d H:i'));

        $writer = new Xls($spreadsheet);
        ob_start();
        $writer->save('php://output');

        return (string) ob_get_clean();
    }

    public function payoutsPdf(array $payouts, string $periodLabel, string $merchantCode): string
    {
        $rows = '';
        foreach ($payouts as $p) {
            $rows .= '<tr>'
                .'<td>'.e((string) ($p['date'] ?? '')).'</td>'
                .'<td>'.e((string) ($p['type'] ?? '')).'</td>'
                .'<td class="num">'.e($this->formatMoney($p['amount'] ?? null)).'</td>'
                .'<td>'.e((string) ($p['currency'] ?? '')).'</td>'
                .'<td class="num">'.e($this->formatMoney($p['fee'] ?? null)).'</td>'
                .'<td>'.e((string) ($p['status'] ?? '')).'</td>'
                .'<td>'.e((string) ($p['reference'] ?? '')).'</td>'
                .'<td>'.e((string) ($p['transaction_code'] ?? '')).'</td>'
                .'</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="8" class="muted">Nincs kifizetés ebben az időszakban.</td></tr>';
        }

        $html = $this->pdfShell(
            'SumUp kifizetések',
            $periodLabel,
            $merchantCode,
            '<table><thead><tr>
                <th>Dátum</th><th>Típus</th><th>Összeg</th><th>Deviza</th>
                <th>Díj</th><th>Státusz</th><th>Referencia</th><th>Tranzakció kód</th>
            </tr></thead><tbody>'.$rows.'</tbody></table>',
        );

        return $this->renderPdf($html);
    }

    public function revenueReportPdf(
        array $transactions,
        array $payouts,
        string $periodLabel,
        string $merchantCode,
    ): string {
        $successful = 0;
        $successfulAmount = [];
        $refundedAmount = [];
        $statusCounts = [];

        foreach ($transactions as $tx) {
            $status = (string) ($tx['status'] ?? 'UNKNOWN');
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
            $currency = (string) ($tx['currency'] ?? 'HUF');
            $amount = (float) ($tx['amount'] ?? 0);
            $refunded = (float) ($tx['refunded_amount'] ?? 0);

            if ($status === 'SUCCESSFUL') {
                $successful++;
                $successfulAmount[$currency] = ($successfulAmount[$currency] ?? 0) + $amount;
            }
            if ($refunded > 0) {
                $refundedAmount[$currency] = ($refundedAmount[$currency] ?? 0) + $refunded;
            }
        }

        $payoutTotal = [];
        $feeTotal = [];
        foreach ($payouts as $p) {
            if (($p['type'] ?? '') !== 'PAYOUT') {
                continue;
            }
            $currency = (string) ($p['currency'] ?? 'HUF');
            $payoutTotal[$currency] = ($payoutTotal[$currency] ?? 0) + (float) ($p['amount'] ?? 0);
            $feeTotal[$currency] = ($feeTotal[$currency] ?? 0) + (float) ($p['fee'] ?? 0);
        }

        $summaryRows = '';
        foreach ($successfulAmount as $currency => $amount) {
            $summaryRows .= '<tr><td>Sikeres forgalom ('.$currency.')</td><td class="num">'
                .$this->formatMoney($amount).' '.$currency.'</td></tr>';
        }
        foreach ($refundedAmount as $currency => $amount) {
            $summaryRows .= '<tr><td>Visszatérítések ('.$currency.')</td><td class="num">'
                .$this->formatMoney($amount).' '.$currency.'</td></tr>';
        }
        foreach ($payoutTotal as $currency => $amount) {
            $summaryRows .= '<tr><td>Kifizetések ('.$currency.')</td><td class="num">'
                .$this->formatMoney($amount).' '.$currency.'</td></tr>';
        }
        foreach ($feeTotal as $currency => $amount) {
            $summaryRows .= '<tr><td>SumUp díjak ('.$currency.')</td><td class="num">'
                .$this->formatMoney($amount).' '.$currency.'</td></tr>';
        }

        $statusRows = '';
        ksort($statusCounts);
        foreach ($statusCounts as $status => $count) {
            $statusRows .= '<tr><td>'.e($status).'</td><td class="num">'.$count.'</td></tr>';
        }

        $html = $this->pdfShell(
            'SumUp bevételi jelentés',
            $periodLabel,
            $merchantCode,
            '<p class="muted">Összefoglaló a könyveléshez — PenzPilot automatikus export.</p>
            <h2>Összesítés</h2>
            <table><tbody>
                <tr><td>Sikeres tranzakciók száma</td><td class="num">'.$successful.'</td></tr>
                <tr><td>Összes tranzakció</td><td class="num">'.count($transactions).'</td></tr>
                '.$summaryRows.'
            </tbody></table>
            <h2>Státusz bontás</h2>
            <table><thead><tr><th>Státusz</th><th>Darab</th></tr></thead><tbody>'.$statusRows.'</tbody></table>',
        );

        return $this->renderPdf($html);
    }

    public function receiptsPdf(
        SumUpClient $client,
        string $merchantCode,
        array $transactions,
        int $maxReceipts,
    ): ?string {
        $blocks = [];
        $fetched = 0;
        $skipped = 0;

        foreach ($transactions as $tx) {
            if ($fetched >= $maxReceipts) {
                $skipped++;

                continue;
            }

            $code = (string) ($tx['transaction_code'] ?? '');
            $status = (string) ($tx['status'] ?? '');
            if ($code === '' || ! in_array($status, ['SUCCESSFUL', 'REFUNDED'], true)) {
                continue;
            }

            $receipt = $client->fetchReceipt($merchantCode, $code);
            if ($receipt === null) {
                continue;
            }

            $blocks[] = $this->receiptBlock($receipt);
            $fetched++;
        }

        if ($blocks === []) {
            return null;
        }

        $periodNote = '<p class="muted">'.$fetched.' nyugta.'
            .($skipped > 0 ? ' További '.$skipped.' tranzakció kimaradt a limit miatt.' : '')
            .'</p>';

        $body = $periodNote.implode('<div class="page-break"></div>', $blocks);

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
            .$this->baseStyles()
            .'.receipt{max-width:520px;margin:0 auto;padding:16px;border:1px solid #ddd}
            .receipt h3{margin:0 0 8px;font-size:14px}
            .receipt .line{display:flex;justify-content:space-between;font-size:12px;margin:4px 0}
            .page-break{page-break-before:always}</style></head><body>'
            .'<h1>SumUp nyugták</h1><p class="muted">Merchant: '.e($merchantCode).'</p>'
            .$body
            .'</body></html>';

        return $this->renderPdf($html);
    }

    private function receiptBlock(array $receipt): string
    {
        $tx = is_array($receipt['transaction_data'] ?? null) ? $receipt['transaction_data'] : [];
        $merchantData = is_array($receipt['merchant_data'] ?? null) ? $receipt['merchant_data'] : [];
        $merchant = is_array($merchantData['merchant_profile'] ?? null) ? $merchantData['merchant_profile'] : [];

        $businessName = (string) ($merchant['business_name'] ?? 'SumUp');
        $amount = (string) ($tx['amount'] ?? '');
        $currency = (string) ($tx['currency'] ?? '');
        $code = (string) ($tx['transaction_code'] ?? '');
        $timestamp = $this->formatLocalDateTime((string) ($tx['timestamp'] ?? ''));
        $status = (string) ($tx['status'] ?? '');
        $paymentType = (string) ($tx['payment_type'] ?? '');
        $vat = (string) ($tx['vat_amount'] ?? '');
        $tip = (string) ($tx['tip_amount'] ?? '');

        return '<div class="receipt">'
            .'<h3>'.e($businessName).'</h3>'
            .'<div class="line"><span>Tranzakció</span><span>'.e($code).'</span></div>'
            .'<div class="line"><span>Dátum</span><span>'.e($timestamp).'</span></div>'
            .'<div class="line"><span>Státusz</span><span>'.e($status).'</span></div>'
            .'<div class="line"><span>Fizetés</span><span>'.e($paymentType).'</span></div>'
            .'<div class="line"><span>Összeg</span><strong>'.e(trim($amount.' '.$currency)).'</strong></div>'
            .($vat !== '' && $vat !== '0' ? '<div class="line"><span>ÁFA</span><span>'.e($vat).'</span></div>' : '')
            .($tip !== '' && $tip !== '0' ? '<div class="line"><span>Borravaló</span><span>'.e($tip).'</span></div>' : '')
            .'</div>';
    }

    private function pdfShell(string $title, string $periodLabel, string $merchantCode, string $body): string
    {
        return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'.$this->baseStyles().'</style></head><body>'
            .'<h1>'.e($title).'</h1>'
            .'<p><strong>Időszak:</strong> '.e($periodLabel).'</p>'
            .'<p><strong>Merchant:</strong> '.e($merchantCode).'</p>'
            .'<p class="muted">Exportálva: '.e(now()->timezone($this->timezone())->format('Y-m-d H:i')).' · PenzPilot</p>'
            .$body
            .'</body></html>';
    }

    private function baseStyles(): string
    {
        return 'body{font-family:DejaVu Sans,sans-serif;font-size:11px;color:#111}
            h1{font-size:18px;margin:0 0 8px}
            h2{font-size:13px;margin:18px 0 8px}
            table{width:100%;border-collapse:collapse;margin-top:8px}
            th,td{border:1px solid #ccc;padding:6px;text-align:left}
            th{background:#e8eef4}
            .num{text-align:right;white-space:nowrap}
            .muted{color:#555;font-size:10px}';
    }

    private function renderPdf(string $html): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    private function formatLocalDateTime(string $timestamp): string
    {
        if ($timestamp === '') {
            return '';
        }

        try {
            return Carbon::parse($timestamp)->timezone($this->timezone())->format('Y-m-d H:i');
        } catch (\Throwable) {
            return $timestamp;
        }
    }

    private function formatMoney(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return is_numeric($value)
            ? number_format((float) $value, 2, ',', ' ')
            : (string) $value;
    }

    private function numericCell(mixed $value): float|string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return is_numeric($value) ? (float) $value : (string) $value;
    }

    private function timezone(): string
    {
        return (string) config('app.timezone', 'Europe/Budapest');
    }
}
