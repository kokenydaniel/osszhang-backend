<?php

namespace App\Services;

use App\Models\Household;
use App\Models\PocketMoneyEntry;
use App\Models\User;
use App\Support\PocketMoneySettings;
use Illuminate\Auth\Access\AuthorizationException;

class PocketMoneyService
{

    public function listForUser(User $user, ?int $year = null, ?int $month = null): array
    {
        $household = $this->requireHousehold($user);
        $this->assertModuleEnabled($household);
        $settings = PocketMoneySettings::resolve($household->pocket_money_settings);
        $defaultCurrency = (string) $settings['default_currency'];

        if ($year === null || $month === null) {
            $entries = PocketMoneyEntry::query()
                ->where('household_id', $household->id)
                ->orderByDesc('entry_date')
                ->orderByDesc('id')
                ->get()
                ->map(fn (PocketMoneyEntry $e) => $this->formatEntry($e))
                ->all();

            return [
                'entries' => $entries,
                'members' => $this->buildMemberSummaries($entries, $defaultCurrency, $settings, null, null),
            ];
        }

        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = date('Y-m-t', strtotime($start));

        $monthEntries = PocketMoneyEntry::query()
            ->where('household_id', $household->id)
            ->whereBetween('entry_date', [$start, $end])
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (PocketMoneyEntry $e) => $this->formatEntry($e))
            ->all();

        $cumulativeEntries = PocketMoneyEntry::query()
            ->where('household_id', $household->id)
            ->where('entry_date', '<=', $end)
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get()
            ->map(fn (PocketMoneyEntry $e) => $this->formatEntry($e))
            ->all();

        return [
            'entries' => $monthEntries,
            'members' => $this->buildMemberSummaries(
                $cumulativeEntries,
                $defaultCurrency,
                $settings,
                $year,
                $month,
                $monthEntries,
            ),
        ];
    }

    public function applyMonthInterest(User $user, int $year, int $month): array
    {
        $household = $this->requireHousehold($user);
        $this->assertModuleEnabled($household);
        $settings = PocketMoneySettings::resolve($household->pocket_money_settings);

        abort_unless($settings['interest_enabled'] ?? false, 422, 'A kamatozás nincs bekapcsolva a beállításokban.');

        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = date('Y-m-t', strtotime($start));

        $list = $this->listForUser($user, $year, $month);
        $created = [];

        foreach ($list['members'] as $member) {
            $interest = $member['interest'] ?? null;
            if (! is_array($interest) || ! ($interest['eligible'] ?? false) || ($interest['applied'] ?? false)) {
                continue;
            }
            $amount = (float) ($interest['previewAmount'] ?? 0);
            if ($amount < 0.01) {
                continue;
            }

            $entry = PocketMoneyEntry::create([
                'household_id' => $household->id,
                'member_user_id' => $member['memberUserId'] ?? null,
                'member_label' => (string) $member['memberLabel'],
                'entry_type' => 'adjustment',
                'amount' => $amount,
                'currency' => (string) ($member['currency'] ?? $settings['default_currency']),
                'entry_date' => $end,
                'note' => $this->interestNote($year, $month),
            ]);

            $created[] = $this->formatEntry($entry);
        }

        return ['applied' => count($created), 'entries' => $created];
    }

    public function create(User $user, array $validated): array
    {
        $household = $this->requireHousehold($user);
        $this->assertModuleEnabled($household);

        $memberUserId = isset($validated['memberUserId']) ? (int) $validated['memberUserId'] : null;
        if ($memberUserId !== null) {
            $this->assertMemberInHousehold($household, $memberUserId);
        }

        $settings = PocketMoneySettings::resolve($household->pocket_money_settings);
        $currency = strtoupper(trim((string) ($validated['currency'] ?? $settings['default_currency'])));
        if (! in_array($currency, $settings['currencies'], true)) {
            $currency = $settings['default_currency'];
        }

        $entry = PocketMoneyEntry::create([
            'household_id' => $household->id,
            'member_user_id' => $memberUserId,
            'member_label' => trim((string) $validated['memberLabel']),
            'entry_type' => $validated['entryType'],
            'amount' => (float) $validated['amount'],
            'currency' => $currency,
            'entry_date' => $validated['entryDate'],
            'note' => isset($validated['note']) ? trim((string) $validated['note']) : null,
        ]);

        return $this->formatEntry($entry);
    }

    public function update(User $user, int $id, array $validated): array
    {
        $household = $this->requireHousehold($user);
        $this->assertModuleEnabled($household);
        $entry = $this->findEntry($household, $id);

        if (array_key_exists('memberUserId', $validated)) {
            $memberUserId = $validated['memberUserId'] !== null ? (int) $validated['memberUserId'] : null;
            if ($memberUserId !== null) {
                $this->assertMemberInHousehold($household, $memberUserId);
            }
            $entry->member_user_id = $memberUserId;
        }
        if (array_key_exists('memberLabel', $validated)) {
            $entry->member_label = trim((string) $validated['memberLabel']);
        }
        if (array_key_exists('entryType', $validated)) {
            $entry->entry_type = $validated['entryType'];
        }
        if (array_key_exists('amount', $validated)) {
            $entry->amount = (float) $validated['amount'];
        }
        if (array_key_exists('currency', $validated)) {
            $settings = PocketMoneySettings::resolve($household->pocket_money_settings);
            $currency = strtoupper(trim((string) $validated['currency']));
            $entry->currency = in_array($currency, $settings['currencies'], true)
                ? $currency
                : $settings['default_currency'];
        }
        if (array_key_exists('entryDate', $validated)) {
            $entry->entry_date = $validated['entryDate'];
        }
        if (array_key_exists('note', $validated)) {
            $entry->note = $validated['note'] !== null ? trim((string) $validated['note']) : null;
        }

        $entry->save();

        return $this->formatEntry($entry);
    }

    public function delete(User $user, int $id): void
    {
        $household = $this->requireHousehold($user);
        $this->assertModuleEnabled($household);
        $this->findEntry($household, $id)->delete();
    }

    private function buildMemberSummaries(
        array $cumulativeEntries,
        string $defaultCurrency,
        array $settings,
        ?int $year,
        ?int $month,
        ?array $monthEntries = null,
    ): array {
        $defaultCurrency = strtoupper(trim($defaultCurrency)) ?: 'HUF';
        $monthEntries ??= $cumulativeEntries;

        $byKey = [];

        foreach ($cumulativeEntries as $row) {
            $key = $this->memberKey($row['memberUserId'] ?? null, (string) $row['memberLabel']);
            if (! isset($byKey[$key])) {
                $byKey[$key] = $this->emptyMemberBucket($key, $row);
            }
            $this->accumulateEntry($byKey[$key], $row, $defaultCurrency, false);
        }

        foreach ($monthEntries as $row) {
            $key = $this->memberKey($row['memberUserId'] ?? null, (string) $row['memberLabel']);
            if (! isset($byKey[$key])) {
                $byKey[$key] = $this->emptyMemberBucket($key, $row);
            }
            $this->accumulateEntry($byKey[$key], $row, $defaultCurrency, true);
        }

        if ($year !== null && $month !== null) {
            $start = sprintf('%04d-%02d-01', $year, $month);
            foreach ($cumulativeEntries as $row) {
                if ((string) ($row['entryDate'] ?? '') >= $start) {
                    continue;
                }
                $key = $this->memberKey($row['memberUserId'] ?? null, (string) $row['memberLabel']);
                if (! isset($byKey[$key])) {
                    continue;
                }
                $this->accumulateEntry($byKey[$key], $row, $defaultCurrency, false, 'opening');
            }
        }

        $members = [];
        foreach ($byKey as $bucket) {
            $balance = $bucket['allowance'] + $bucket['adjustment'] - $bucket['expense'];
            $opening = (float) ($bucket['openingAllowance'] + $bucket['openingAdjustment'] - $bucket['openingExpense']);
            $monthExpense = (float) $bucket['monthExpense'];
            $closingBeforeInterest = $opening
                + (float) $bucket['monthAllowance']
                + (float) $bucket['monthAdjustmentNonInterest']
                - $monthExpense;

            $interest = $this->buildInterestMeta(
                $settings,
                $year,
                $month,
                $monthExpense,
                $closingBeforeInterest,
                (float) $bucket['monthAllowance'],
                (bool) ($bucket['interestApplied'] ?? false),
            );

            $members[] = [
                'memberKey' => $bucket['memberKey'],
                'memberLabel' => $bucket['memberLabel'],
                'memberUserId' => $bucket['memberUserId'],
                'currency' => $defaultCurrency,
                'balance' => round($balance, 2),
                'openingBalance' => round($opening, 2),
                'allowanceTotal' => round((float) $bucket['monthAllowance'], 2),
                'expenseTotal' => round($monthExpense, 2),
                'adjustmentTotal' => round((float) $bucket['monthAdjustment'], 2),
                'interestTotal' => round((float) $bucket['monthInterest'], 2),
                'interest' => $interest,
            ];
        }

        usort($members, fn ($a, $b) => strcmp($a['memberLabel'], $b['memberLabel']));

        return $members;
    }

    private function accumulateEntry(
        array &$bucket,
        array $row,
        string $defaultCurrency,
        bool $forMonth,
        string $target = 'main',
    ): void {
        $amount = $this->toDefaultCurrency(
            (float) ($row['amount'] ?? 0),
            (string) ($row['currency'] ?? 'HUF'),
            $defaultCurrency,
        );
        $type = (string) ($row['entryType'] ?? '');
        $isInterest = $this->isInterestNote((string) ($row['note'] ?? ''));

        $prefix = $target === 'opening' ? 'opening' : ($forMonth ? 'month' : '');
        if ($prefix === 'opening') {
            match ($type) {
                'allowance' => $bucket['openingAllowance'] += $amount,
                'expense' => $bucket['openingExpense'] += $amount,
                'adjustment' => $bucket['openingAdjustment'] += $amount,
                default => null,
            };

            return;
        }

        if ($forMonth) {
            if ($isInterest) {
                $bucket['monthInterest'] += $amount;
                $bucket['interestApplied'] = true;
            }
            match ($type) {
                'allowance' => $bucket['monthAllowance'] += $amount,
                'expense' => $bucket['monthExpense'] += $amount,
                'adjustment' => $bucket['monthAdjustment'] += $amount,
                default => null,
            };
            if ($type === 'adjustment' && ! $isInterest) {
                $bucket['monthAdjustmentNonInterest'] += $amount;
            }

            return;
        }

        match ($type) {
            'allowance' => $bucket['allowance'] += $amount,
            'expense' => $bucket['expense'] += $amount,
            'adjustment' => $bucket['adjustment'] += $amount,
            default => null,
        };
    }

    private function emptyMemberBucket(string $key, array $row): array
    {
        return [
            'memberKey' => $key,
            'memberLabel' => (string) $row['memberLabel'],
            'memberUserId' => $row['memberUserId'] ?? null,
            'allowance' => 0.0,
            'expense' => 0.0,
            'adjustment' => 0.0,
            'openingAllowance' => 0.0,
            'openingExpense' => 0.0,
            'openingAdjustment' => 0.0,
            'monthAllowance' => 0.0,
            'monthExpense' => 0.0,
            'monthAdjustment' => 0.0,
            'monthAdjustmentNonInterest' => 0.0,
            'monthInterest' => 0.0,
            'interestApplied' => false,
        ];
    }

    private function buildInterestMeta(
        array $settings,
        ?int $year,
        ?int $month,
        float $monthExpense,
        float $closingBeforeInterest,
        float $monthAllowance,
        bool $applied,
    ): ?array {
        if ($year === null || $month === null) {
            return null;
        }

        $enabled = (bool) ($settings['interest_enabled'] ?? false);
        $rate = (float) ($settings['interest_rate_percent'] ?? 0);
        $rule = (string) ($settings['interest_basis'] ?? 'no_expense');
        $on = (string) ($settings['interest_on'] ?? 'balance');
        if (! in_array($on, ['balance', 'month_allowance'], true)) {
            $on = 'balance';
        }

        $preview = 0.0;
        $eligible = false;
        $reason = '';

        if ($enabled && $rate > 0) {
            $principal = $this->interestPrincipalAmount(
                $on,
                $rule,
                $monthExpense,
                $closingBeforeInterest,
                $monthAllowance,
            );

            if ($rule === 'no_expense') {
                if ($monthExpense >= 0.01) {
                    $reason = 'Költés volt ebben a hónapban — ez a szabály nem ad kamatot.';
                } elseif ($principal < 0.01) {
                    $reason = $on === 'month_allowance'
                        ? 'Nincs kiosztott zsebpénz ebben a hónapban.'
                        : 'Nincs kamatosítható egyenleg.';
                } else {
                    $preview = round($principal * $rate / 100, 2);
                    $eligible = $preview >= 0.01;
                    $reason = $on === 'month_allowance'
                        ? 'Nem költött — kamat a hónapban kiosztott zsebpénzre.'
                        : 'Nem költött — kamat a teljes egyenlegre (hónap végén).';
                }
            } elseif ($principal < 0.01) {
                $reason = $on === 'month_allowance'
                    ? 'Nincs el nem költött zsebpénz ebben a hónapban.'
                    : 'Nincs bent maradt egyenleg kamatra.';
            } else {
                $preview = round($principal * $rate / 100, 2);
                $eligible = $preview >= 0.01;
                $reason = $on === 'month_allowance'
                    ? 'Kamat a hónapban kiosztott, de el nem költött zsebpénzre.'
                    : 'Kamat a hónap végén bent maradt teljes egyenlegre.';
            }
        }

        return [
            'enabled' => $enabled,
            'ratePercent' => $rate,
            'on' => $on,
            'basis' => $rule,
            'previewAmount' => $preview,
            'eligible' => $eligible && ! $applied,
            'applied' => $applied,
            'reason' => $reason,
        ];
    }

    private function interestPrincipalAmount(
        string $on,
        string $rule,
        float $monthExpense,
        float $closingBeforeInterest,
        float $monthAllowance,
    ): float {
        if ($on === 'month_allowance') {
            if ($rule === 'remaining') {
                return max(0, $monthAllowance - $monthExpense);
            }

            return max(0, $monthAllowance);
        }

        if ($rule === 'remaining') {
            return max(0, $closingBeforeInterest);
        }

        return max(0, $closingBeforeInterest);
    }

    private function interestNote(int $year, int $month): string
    {
        return sprintf('Kamat (%04d-%02d)', $year, $month);
    }

    private function isInterestNote(string $note): bool
    {
        return str_starts_with($note, 'Kamat (');
    }

    private function toDefaultCurrency(float $amount, string $currency, string $defaultCurrency): float
    {
        $currency = strtoupper(trim($currency)) ?: 'HUF';
        $defaultCurrency = strtoupper(trim($defaultCurrency)) ?: 'HUF';

        $rates = config('exchange_rates.huf_per_unit', ['HUF' => 1, 'EUR' => 395, 'USD' => 365]);

        $huf = $amount;
        if ($currency !== 'HUF') {
            $perUnit = (float) ($rates[$currency] ?? 0);
            $huf = $perUnit > 0 ? $amount * $perUnit : $amount;
        }

        if ($defaultCurrency === 'HUF') {
            return $huf;
        }

        $targetPerUnit = (float) ($rates[$defaultCurrency] ?? 0);

        return $targetPerUnit > 0 ? $huf / $targetPerUnit : $huf;
    }

    private function memberKey(?int $memberUserId, string $memberLabel): string
    {
        if ($memberUserId !== null) {
            return 'user:'.$memberUserId;
        }

        return 'label:'.mb_strtolower(trim($memberLabel));
    }

    private function formatEntry(PocketMoneyEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'memberLabel' => $entry->member_label,
            'memberUserId' => $entry->member_user_id,
            'entryType' => $entry->entry_type,
            'amount' => (float) $entry->amount,
            'currency' => $entry->currency,
            'entryDate' => $entry->entry_date?->toDateString(),
            'note' => $entry->note,
            'createdAt' => $entry->created_at?->toIso8601String(),
        ];
    }

    private function findEntry(Household $household, int $id): PocketMoneyEntry
    {
        return PocketMoneyEntry::query()
            ->where('household_id', $household->id)
            ->whereKey($id)
            ->firstOrFail();
    }

    private function assertMemberInHousehold(Household $household, int $userId): void
    {
        $exists = User::query()
            ->where('household_id', $household->id)
            ->whereKey($userId)
            ->exists();

        abort_unless($exists, 422, 'A kiválasztott tag nem tartozik ehhez a háztartáshoz.');
    }

    private function assertModuleEnabled(Household $household): void
    {
        abort_unless($household->pocket_money_enabled, 403, 'A zsebpénz modul nincs bekapcsolva.');
    }

    private function requireHousehold(User $user): Household
    {
        $household = $user->household;
        if (! $household) {
            throw new AuthorizationException('Nincs háztartás.');
        }

        return $household;
    }
}
