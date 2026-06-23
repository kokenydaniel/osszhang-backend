<?php

namespace App\Integrations\SumUp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SumUpClient
{
    private ?string $apiKey = null;

    public function setApiKey(string $apiKey): void
    {
        $this->apiKey = trim($apiKey);
    }

    public function listTransactionsForPeriod(string $merchantCode, string $oldestTime, string $newestTime): array
    {
        $this->assertConfigured();

        $all = [];
        $path = '/v2.1/merchants/'.rawurlencode($merchantCode).'/transactions/history';
        $query = [
            'oldest_time' => $oldestTime,
            'newest_time' => $newestTime,
            'limit' => 100,
            'order' => 'ascending',
        ];

        while ($path !== '') {
            $response = $this->request('GET', $path, $query);
            $items = $response['items'] ?? [];
            if (is_array($items)) {
                foreach ($items as $item) {
                    if (is_array($item)) {
                        $all[] = $item;
                    }
                }
            }

            $nextHref = null;
            foreach ($response['links'] ?? [] as $link) {
                if (is_array($link) && ($link['rel'] ?? '') === 'next' && ! empty($link['href'])) {
                    $nextHref = (string) $link['href'];
                    break;
                }
            }

            if ($nextHref === null) {
                break;
            }

            $path = $path.'?'.$nextHref;
            $query = [];
        }

        return $all;
    }

    public function listPayoutsForPeriod(string $merchantCode, string $startDate, string $endDate): array
    {
        $path = '/v1.0/merchants/'.rawurlencode($merchantCode).'/payouts';
        $data = $this->request('GET', $path, [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'format' => 'json',
            'order' => 'asc',
            'limit' => 9999,
        ]);

        if (array_is_list($data)) {
            return array_values(array_filter($data, 'is_array'));
        }

        foreach (['items', 'data', 'payouts'] as $key) {
            if (isset($data[$key]) && is_array($data[$key]) && array_is_list($data[$key])) {
                return array_values(array_filter($data[$key], 'is_array'));
            }
        }

        return [];
    }

    public function fetchReceipt(string $merchantCode, string $transactionId): ?array
    {
        $this->assertConfigured();

        $path = '/v1.1/receipts/'.rawurlencode($transactionId);

        try {
            return $this->request('GET', $path, ['mid' => $merchantCode]);
        } catch (\Throwable $e) {
            Log::warning('SumUp receipt fetch skipped', [
                'transaction_id' => $transactionId,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function request(string $method, string $path, array $query = []): array
    {
        $url = $this->baseUrl().$path;
        $pending = Http::withToken($this->apiKey)->acceptJson();

        $response = $method === 'GET'
            ? $pending->get($url, $query)
            : $pending->send($method, $url, ['query' => $query]);

        if ($response->failed()) {
            Log::error('SumUp API error', ['path' => $path, 'status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('SumUp API hiba: '.$response->status());
        }

        return $response->json() ?? [];
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('sumup.api_base_url'), '/');
    }

    private function assertConfigured(): void
    {
        if (! $this->apiKey) {
            throw new \RuntimeException('SumUp API kulcs nincs beállítva.');
        }
    }
}
