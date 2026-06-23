<?php

namespace App\Services\Travel;

use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;

class TravelPlanPdfExporter
{
    /** @var array<string, string> */
    private const CATEGORY_LABELS = [
        'transport' => 'Közlekedés',
        'accommodation' => 'Szállás',
        'food' => 'Étel & ital',
        'activities' => 'Programok',
        'insurance' => 'Utazásbiztosítás',
        'miscellaneous' => 'Egyéb / puffer',
        'custom' => 'Egyéb',
    ];

    /** @var array<string, string> */
    private const STATUS_LABELS = [
        'paid' => 'Kifizetve',
        'planned' => 'Hátralévő',
    ];

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $form
     * @param  array<string, string>|null  $formLabels
     * @param  array<string, mixed>|null  $meta
     */
    public function generate(array $plan, array $form, ?array $formLabels = null, ?array $meta = null): string
    {
        $destination = (string) ($plan['destination'] ?? $form['destination'] ?? 'utazas');
        $body = $this->buildBody($plan, $form, $formLabels ?? [], $meta ?? []);

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'.$this->baseStyles().'</style></head><body>'
            .'<h1>'.e('Utazástervező — '.$destination).'</h1>'
            .'<p class="muted">Exportálva: '.e(now()->timezone($this->timezone())->format('Y-m-d H:i')).' · PenzPilot</p>'
            .$body
            .'</body></html>';

        return $this->renderPdf($html);
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $form
     * @param  array<string, string>  $formLabels
     * @param  array<string, mixed>  $meta
     */
    private function buildBody(array $plan, array $form, array $formLabels, array $meta): string
    {
        $sections = [];

        if (! empty($meta['fallback_used'])) {
            $reason = (string) ($meta['failure_reason'] ?? '');
            $sections[] = $this->callout(
                'AI helyett szabályalapú terv',
                'A mesterséges intelligencia nem volt elérhető — a rendszer reális minimum költségekből készített tervet.'
                .($reason !== '' ? ' ('.$reason.')' : ''),
            );
        }

        $sections[] = $this->buildFormSection($plan, $form, $formLabels);

        if (! empty($plan['summary'])) {
            $sections[] = '<h2>Összefoglaló</h2><p>'.e((string) $plan['summary']).'</p>';
        }

        if (! empty($plan['warning'])) {
            $sections[] = $this->callout('Költségkeret figyelmeztetés', (string) $plan['warning']);
        }

        $sections[] = $this->buildCostSection($plan);
        $sections[] = $this->buildFinancialSection($plan);

        if ($this->shouldShowTransport($plan)) {
            $sections[] = $this->buildTransportSection($plan);
        }

        if (! empty($plan['savings_plan']) && is_array($plan['savings_plan'])) {
            $sections[] = $this->buildSavingsPlanSection($plan['savings_plan']);
        }

        if (! empty($plan['comparison']) && is_array($plan['comparison'])) {
            $sections[] = $this->buildComparisonSection($plan['comparison']);
        }

        $sections[] = $this->buildItinerarySection($plan);

        if (! empty($plan['currency_notes'])) {
            $sections[] = '<h2>Deviza megjegyzések</h2><p>'.e((string) $plan['currency_notes']).'</p>';
        }

        return implode('', array_filter($sections));
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $form
     * @param  array<string, string>  $formLabels
     */
    private function buildFormSection(array $plan, array $form, array $formLabels): string
    {
        $rows = [
            ['Célállomás', (string) ($form['destination'] ?? $plan['destination'] ?? '')],
            ['Indulás helye', (string) ($form['originLocation'] ?? $plan['origin_location'] ?? 'Budapest')],
            ['Utazás hossza', $this->formatDays($form['durationDays'] ?? $plan['duration_days'] ?? null)],
            ['Utazók száma', (string) ($form['travelersCount'] ?? $plan['travelers_count'] ?? '1')],
            ['Költségkeret', $this->formatHuf($form['totalBudget'] ?? $plan['total_budget'] ?? null)],
            ['Tervezett dátum', (string) ($form['targetDate'] ?? '')],
            ['Utazás stílusa', $formLabels['trip_style'] ?? (string) ($plan['trip_style'] ?? '—')],
            ['Szállás preferencia', $formLabels['accommodation'] ?? (string) ($plan['accommodation_preference'] ?? '—')],
            ['Közlekedés', $formLabels['transport'] ?? (string) ($plan['transport_mode'] ?? '—')],
            ['Közlekedés lefoglalva', $formLabels['transport_booked'] ?? $this->formatBool($plan['transport_already_booked'] ?? false)],
            ['Szállás lefoglalva', $formLabels['accommodation_booked'] ?? $this->formatBool($plan['accommodation_already_booked'] ?? false)],
        ];

        if (! empty($formLabels['car_fuel'])) {
            $rows[] = ['Autó fogyasztás', $formLabels['car_fuel']];
        }

        $tableRows = '';
        foreach ($rows as [$label, $value]) {
            if ($value === '' || $value === '—') {
                continue;
            }
            $tableRows .= '<tr><td class="label">'.e($label).'</td><td>'.e($value).'</td></tr>';
        }

        return '<h2>Megadott adatok</h2><table class="kv"><tbody>'.$tableRows.'</tbody></table>';
    }

    /** @param  array<string, mixed>  $plan */
    private function buildCostSection(array $plan): string
    {
        $items = $this->activeCostItems($plan);
        $summary = $this->summarizeCostItems($items);
        $total = $summary['total_trip_huf'];
        $remaining = $summary['remaining_huf'];
        $paid = $summary['paid_huf'];
        $fullTotal = $summary['full_total_huf'];
        $hasSplit = $summary['has_split'];

        $summaryHtml = '<div class="stats">'
            .'<div class="stat"><span class="stat-label">A mi költségünk</span><strong>'.e($this->formatHuf($total)).'</strong></div>'
            .'<div class="stat"><span class="stat-label">Kifizetve</span><strong>'.e($this->formatHuf($paid)).'</strong></div>'
            .'<div class="stat"><span class="stat-label">Hátralévő</span><strong>'.e($this->formatHuf($remaining)).'</strong></div>'
            .'</div>';

        if ($hasSplit) {
            $summaryHtml .= '<p class="muted">Teljes költség (megosztás előtt): '.e($this->formatHuf($fullTotal)).'</p>';
        }

        if ($items === []) {
            return '<h2>Költségek</h2>'.$summaryHtml.'<p class="muted">Nincs költségtétel.</p>';
        }

        $rows = '';
        foreach ($items as $item) {
            $status = (string) ($item['status'] ?? 'planned');
            $fullAmount = (float) ($item['amount_huf'] ?? 0);
            $ourShare = $this->itemOurShare($item);
            $splitLabel = $this->formatSplitLabel($item);

            $rows .= '<tr>'
                .'<td>'.e((string) ($item['label'] ?? '')).'</td>'
                .'<td>'.e($this->categoryLabel((string) ($item['category'] ?? ''))).'</td>'
                .'<td class="num">'.e($this->formatHuf($fullAmount)).'</td>'
                .'<td>'.e($splitLabel).'</td>'
                .'<td class="num">'.e($this->formatHuf($ourShare)).'</td>'
                .'<td>'.e(self::STATUS_LABELS[$status] ?? $status).'</td>'
                .'</tr>';
        }

        return '<h2>Költségek (végleges)</h2>'
            .$summaryHtml
            .'<table><thead><tr><th>Tétel</th><th>Kategória</th><th>Teljes összeg</th><th>Megosztás</th><th>A mi részünk</th><th>Státusz</th></tr></thead><tbody>'
            .$rows
            .'</tbody></table>';
    }

    /** @param  array<string, mixed>  $plan */
    private function buildFinancialSection(array $plan): string
    {
        $fit = $plan['financial_fit'] ?? null;
        if (! is_array($fit) || empty($fit['summary'])) {
            return '';
        }

        $fits = (bool) ($fit['fits_current_budget'] ?? false);
        $badge = $fits
            ? '<span class="badge ok">Belefér</span>'
            : '<span class="badge warn">Nem fér bele</span>';

        $rows = [
            ['Összegzés', (string) $fit['summary']],
            ['Belefér a költségvetésbe', $fits ? 'Igen' : 'Nem'],
        ];

        if (isset($fit['available_for_trip_huf'])) {
            $rows[] = ['Rendelkezésre álló összeg', $this->formatHuf($fit['available_for_trip_huf'])];
        }
        if (isset($fit['travel_eligible_savings_huf'])) {
            $rows[] = ['Utazásra számítható megtakarítás', $this->formatHuf($fit['travel_eligible_savings_huf'])];
        }
        if (isset($fit['monthly_savings_capacity_huf'])) {
            $rows[] = ['Havi megtakarítási kapacitás', $this->formatHuf($fit['monthly_savings_capacity_huf'])];
        }
        if (isset($fit['required_monthly_savings_huf']) && $fit['required_monthly_savings_huf'] !== null) {
            $rows[] = ['Szükséges havi megtakarítás', $this->formatHuf($fit['required_monthly_savings_huf'])];
        }
        if (isset($fit['disposable_remaining_huf'])) {
            $rows[] = ['Maradék (aktuális hónap)', $this->formatHuf($fit['disposable_remaining_huf'])];
        }
        if (! empty($fit['target_date'])) {
            $rows[] = ['Cél dátum', (string) $fit['target_date']];
        }

        $tableRows = '';
        foreach ($rows as [$label, $value]) {
            $tableRows .= '<tr><td class="label">'.e($label).'</td><td>'.e($value).'</td></tr>';
        }

        $eligibleItems = '';
        if (! empty($fit['travel_eligible_savings_items']) && is_array($fit['travel_eligible_savings_items'])) {
            foreach ($fit['travel_eligible_savings_items'] as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $eligibleItems .= '<li>'.e((string) ($item['label'] ?? ''))
                    .' — '.e($this->formatHuf($item['amount_huf'] ?? 0)).'</li>';
            }
        }

        $monthlyBreakdown = '';
        if (! empty($fit['monthly_surplus_breakdown']) && is_array($fit['monthly_surplus_breakdown'])) {
            $monthlyBreakdown .= '<table><thead><tr><th>Hónap</th><th>Bevétel</th><th>Kiadás</th><th>Többlet</th></tr></thead><tbody>';
            foreach ($fit['monthly_surplus_breakdown'] as $month) {
                if (! is_array($month)) {
                    continue;
                }
                $monthlyBreakdown .= '<tr>'
                    .'<td>'.e((string) ($month['label'] ?? '')).'</td>'
                    .'<td class="num">'.e($this->formatHuf($month['income_huf'] ?? 0)).'</td>'
                    .'<td class="num">'.e($this->formatHuf($month['expense_huf'] ?? 0)).'</td>'
                    .'<td class="num">'.e($this->formatHuf($month['surplus_huf'] ?? 0)).'</td>'
                    .'</tr>';
            }
            $monthlyBreakdown .= '</tbody></table>';
        }

        $html = '<h2>Pénzügyi illeszkedés '.$badge.'</h2>'
            .'<table class="kv"><tbody>'.$tableRows.'</tbody></table>';

        if ($eligibleItems !== '') {
            $html .= '<h3>Utazásra számítható tételek</h3><ul>'.$eligibleItems.'</ul>';
        }

        if ($monthlyBreakdown !== '') {
            $html .= '<h3>Havi többlet (utolsó 3 hónap)</h3>'.$monthlyBreakdown;
        }

        return $html;
    }

    /** @param  array<string, mixed>  $plan */
    private function shouldShowTransport(array $plan): bool
    {
        $items = $this->activeCostItems($plan);
        if ($items !== []) {
            foreach ($items as $item) {
                if (($item['category'] ?? '') === 'transport') {
                    return ! empty($plan['transport_detail']);
                }
            }

            return false;
        }

        $breakdown = is_array($plan['cost_breakdown'] ?? null) ? $plan['cost_breakdown'] : [];
        $transport = (float) ($breakdown['transport'] ?? 0);

        return $transport > 0 && ! empty($plan['transport_detail']);
    }

    /** @param  array<string, mixed>  $plan */
    private function buildTransportSection(array $plan): string
    {
        $detail = $plan['transport_detail'] ?? null;
        if (! is_array($detail)) {
            return '';
        }

        $rows = [
            ['Mód', (string) ($detail['mode'] ?? '')],
            ['Becsült költség', $this->formatHuf($detail['estimated_cost'] ?? 0)],
            ['Leírás', (string) ($detail['description'] ?? '')],
        ];

        $optional = [
            'estimated_distance_km' => 'Távolság (km)',
            'one_way_distance_km' => 'Egyirányú távolság (km)',
            'fuel_liters' => 'Üzemanyag (liter)',
            'fuel_price_per_liter_huf' => 'Üzemanyag ár / liter',
            'fuel_cost_huf' => 'Üzemanyag költség',
            'tolls_and_parking_huf' => 'Útdíj és parkolás',
            'car_fuel_consumption_l100' => 'Fogyasztás (l/100 km)',
            'per_person_huf' => 'Személyenként',
        ];

        foreach ($optional as $key => $label) {
            if (! isset($detail[$key]) || $detail[$key] === '' || $detail[$key] === null) {
                continue;
            }
            $value = is_numeric($detail[$key]) && str_ends_with($key, '_huf')
                ? $this->formatHuf($detail[$key])
                : (string) $detail[$key];
            $rows[] = [$label, $value];
        }

        $tableRows = '';
        foreach ($rows as [$label, $value]) {
            if ($value === '') {
                continue;
            }
            $tableRows .= '<tr><td class="label">'.e($label).'</td><td>'.e($value).'</td></tr>';
        }

        $notes = '';
        if (! empty($detail['notes']) && is_array($detail['notes'])) {
            foreach ($detail['notes'] as $note) {
                $notes .= '<li>'.e((string) $note).'</li>';
            }
        }

        $html = '<h2>Közlekedés részletei</h2><table class="kv"><tbody>'.$tableRows.'</tbody></table>';
        if ($notes !== '') {
            $html .= '<ul>'.$notes.'</ul>';
        }

        return $html;
    }

    /** @param  array<string, mixed>  $savingsPlan */
    private function buildSavingsPlanSection(array $savingsPlan): string
    {
        $rows = [
            ['Havi összeg', $this->formatHuf($savingsPlan['monthly_amount_huf'] ?? 0)],
            ['Hónapok száma', (string) ($savingsPlan['months'] ?? '')],
            ['Megjegyzés', (string) ($savingsPlan['note'] ?? '')],
        ];

        $tableRows = '';
        foreach ($rows as [$label, $value]) {
            if ($value === '') {
                continue;
            }
            $tableRows .= '<tr><td class="label">'.e($label).'</td><td>'.e($value).'</td></tr>';
        }

        return '<h2>Megtakarítási terv</h2><table class="kv"><tbody>'.$tableRows.'</tbody></table>';
    }

    /** @param  array<string, mixed>  $comparison */
    private function buildComparisonSection(array $comparison): string
    {
        $scenarios = [
            'minimum' => 'Minimum',
            'requested' => 'Kért keret',
            'comfort' => 'Komfort',
            'planned' => 'Tervezett',
        ];

        $rows = '';
        foreach ($scenarios as $key => $label) {
            $scenario = $comparison[$key] ?? null;
            if (! is_array($scenario)) {
                continue;
            }
            $rows .= '<tr>'
                .'<td>'.e($label).'</td>'
                .'<td class="num">'.e($this->formatHuf($scenario['total_huf'] ?? 0)).'</td>'
                .'<td>'.e((string) ($scenario['summary'] ?? '')).'</td>'
                .'</tr>';
        }

        if ($rows === '') {
            return '';
        }

        return '<h2>Költség összehasonlítás</h2>'
            .'<table><thead><tr><th>Szcenárió</th><th>Összeg</th><th>Összefoglaló</th></tr></thead><tbody>'
            .$rows
            .'</tbody></table>';
    }

    /** @param  array<string, mixed>  $plan */
    private function buildItinerarySection(array $plan): string
    {
        $days = $plan['daily_itinerary'] ?? [];
        if (! is_array($days) || $days === []) {
            return '';
        }

        $html = '<h2>Napi program</h2>';
        foreach ($days as $day) {
            if (! is_array($day)) {
                continue;
            }
            $title = (string) ($day['title'] ?? '');
            $dayNum = (string) ($day['day'] ?? '');
            $cost = $this->formatHuf($day['estimated_daily_cost'] ?? 0);

            $html .= '<div class="day">'
                .'<h3>'.e($dayNum.'. nap — '.$title).' <span class="muted">('.$cost.')</span></h3>';

            $activities = $day['activities'] ?? [];
            if (is_array($activities) && $activities !== []) {
                $html .= '<ul>';
                foreach ($activities as $activity) {
                    $html .= '<li>'.e((string) $activity).'</li>';
                }
                $html .= '</ul>';
            }

            $html .= '</div>';
        }

        return $html;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<int, array<string, mixed>>
     */
    private function activeCostItems(array $plan): array
    {
        $items = $plan['cost_line_items'] ?? null;
        if (is_array($items) && $items !== []) {
            return array_values(array_filter(
                $items,
                static fn (mixed $item): bool => is_array($item) && ($item['status'] ?? 'planned') !== 'excluded',
            ));
        }

        $breakdown = is_array($plan['cost_breakdown'] ?? null) ? $plan['cost_breakdown'] : [];
        $result = [];
        foreach ($breakdown as $category => $amount) {
            if (! is_numeric($amount) || (float) $amount <= 0) {
                continue;
            }
            $result[] = [
                'label' => $this->categoryLabel((string) $category),
                'category' => (string) $category,
                'amount_huf' => (float) $amount,
                'status' => 'planned',
            ];
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{total_trip_huf: float, full_total_huf: float, remaining_huf: float, paid_huf: float, has_split: bool}
     */
    private function summarizeCostItems(array $items): array
    {
        $totalTrip = 0.0;
        $fullTotal = 0.0;
        $remaining = 0.0;
        $paid = 0.0;
        $hasSplit = false;

        foreach ($items as $item) {
            $status = (string) ($item['status'] ?? 'planned');
            $fullAmount = (float) ($item['amount_huf'] ?? 0);
            $ourShare = $this->itemOurShare($item);

            if (! empty($item['split_enabled'])) {
                $hasSplit = true;
            }

            $fullTotal += $fullAmount;
            $totalTrip += $ourShare;

            if ($status === 'planned') {
                $remaining += $ourShare;
            } elseif ($status === 'paid') {
                $paid += $ourShare;
            }
        }

        return [
            'total_trip_huf' => round($totalTrip, 2),
            'full_total_huf' => round($fullTotal, 2),
            'remaining_huf' => round($remaining, 2),
            'paid_huf' => round($paid, 2),
            'has_split' => $hasSplit,
        ];
    }

    /** @param  array<string, mixed>  $item */
    private function itemOurShare(array $item): float
    {
        $amount = (float) ($item['amount_huf'] ?? 0);
        if (empty($item['split_enabled'])) {
            return $amount;
        }

        $parts = (int) ($item['split_between'] ?? 2);
        if ($parts < 2) {
            $parts = 2;
        }

        return round($amount / $parts, 2);
    }

    /** @param  array<string, mixed>  $item */
    private function formatSplitLabel(array $item): string
    {
        if (empty($item['split_enabled'])) {
            return '—';
        }

        $parts = max(2, (int) ($item['split_between'] ?? 2));

        return $parts.' fél';
    }

    private function categoryLabel(string $category): string
    {
        return self::CATEGORY_LABELS[$category] ?? $category;
    }

    private function callout(string $title, string $body): string
    {
        return '<div class="callout"><strong>'.e($title).'</strong><p>'.e($body).'</p></div>';
    }

    private function formatHuf(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_string($value)) {
            $normalized = str_replace([' ', ','], ['', '.'], $value);
            if (! is_numeric($normalized)) {
                return $value;
            }
            $value = (float) $normalized;
        }

        if (! is_numeric($value)) {
            return '—';
        }

        return number_format((float) $value, 0, ',', ' ').' Ft';
    }

    private function formatDays(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $days = is_numeric($value) ? (int) $value : (int) preg_replace('/\D/', '', (string) $value);

        return $days > 0 ? $days.' nap' : '—';
    }

    private function formatBool(mixed $value): string
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Igen' : 'Nem';
    }

    private function baseStyles(): string
    {
        return 'body{font-family:DejaVu Sans,sans-serif;font-size:11px;color:#111;line-height:1.45}
            h1{font-size:18px;margin:0 0 4px}
            h2{font-size:13px;margin:20px 0 8px;border-bottom:1px solid #ddd;padding-bottom:4px}
            h3{font-size:12px;margin:12px 0 6px}
            table{width:100%;border-collapse:collapse;margin-top:6px}
            th,td{border:1px solid #ccc;padding:6px;text-align:left;vertical-align:top}
            th{background:#e8eef4;font-weight:bold}
            table.kv td.label{width:38%;background:#f7f9fb;font-weight:bold}
            .num{text-align:right;white-space:nowrap}
            .muted{color:#555;font-size:10px}
            .callout{border:1px solid #e8c547;background:#fff9e6;padding:8px 10px;margin:10px 0;border-radius:4px}
            .callout p{margin:4px 0 0}
            .stats{display:table;width:100%;margin:8px 0 12px}
            .stat{display:table-cell;width:33%;padding:8px;border:1px solid #ddd;background:#f7f9fb;text-align:center}
            .stat-label{display:block;font-size:10px;color:#555;margin-bottom:4px}
            .badge{font-size:10px;padding:2px 6px;border-radius:3px;margin-left:6px}
            .badge.ok{background:#d4edda;color:#155724}
            .badge.warn{background:#f8d7da;color:#721c24}
            .day{margin-bottom:10px;page-break-inside:avoid}
            ul{margin:4px 0 8px 18px;padding:0}';
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

    private function timezone(): string
    {
        return (string) config('app.timezone', 'Europe/Budapest');
    }

    public function filenameForDestination(string $destination): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', mb_strtolower(trim($destination))) ?? 'utazas';
        $slug = trim($slug, '-') ?: 'utazas';

        return 'utazas-'.$slug.'-'.Carbon::now()->timezone($this->timezone())->format('Y-m-d').'.pdf';
    }
}
