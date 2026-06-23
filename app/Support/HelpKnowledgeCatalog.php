<?php

namespace App\Support;

final class HelpKnowledgeCatalog
{
    private readonly array $chunks;

    private readonly string $appName;

    public function __construct()
    {
        $path = config_path('help_knowledge.json');
        if (! is_readable($path)) {
            $this->chunks = [];
            $this->appName = (string) config('help_assistant.app_name', 'Összhang');

            return;
        }

        $data = json_decode((string) file_get_contents($path), true);
        $this->chunks = is_array($data['chunks'] ?? null) ? $data['chunks'] : [];
        $this->appName = (string) ($data['app_name'] ?? config('help_assistant.app_name', 'Összhang'));
    }

    public function appName(): string
    {
        return $this->appName;
    }

    public function chunkCount(): int
    {
        return count($this->chunks);
    }

    public function retrieve(string $query, int $limit = 18): array
    {
        if ($this->chunks === []) {
            return [];
        }

        $tokens = $this->tokenize($query);
        if ($tokens === []) {
            return array_slice($this->coreChunks(), 0, $limit);
        }

        $scored = [];
        foreach ($this->chunks as $chunk) {
            $score = $this->scoreChunk($chunk, $tokens);
            if ($score > 0) {
                $scored[] = ['chunk' => $chunk, 'score' => $score];
            }
        }

        usort($scored, static fn (array $a, array $b) => $b['score'] <=> $a['score']);

        $selected = array_map(static fn (array $row) => $row['chunk'], array_slice($scored, 0, $limit));

        if (count($selected) < 6) {
            foreach ($this->coreChunks() as $coreChunk) {
                if (count($selected) >= $limit) {
                    break;
                }
                if (! $this->containsChunkId($selected, (string) ($coreChunk['id'] ?? ''))) {
                    $selected[] = $coreChunk;
                }
            }
        }

        return $selected;
    }

    public function formatChunksForPrompt(array $chunks): string
    {
        if ($chunks === []) {
            return 'Nincs betöltött tudásbázis. Jelezd, hogy nézze meg a /help oldalt.';
        }

        $lines = [];
        foreach ($chunks as $chunk) {
            $title = (string) ($chunk['title'] ?? $chunk['id'] ?? 'Ismeretlen');
            $area = (string) ($chunk['area'] ?? '');
            $path = (string) ($chunk['path'] ?? '');
            $moduleId = (string) ($chunk['moduleId'] ?? '');
            $body = trim((string) ($chunk['body'] ?? ''));

            $lines[] = "### {$title}";
            if ($area !== '') {
                $lines[] = "Terület: {$area}";
            }
            if ($path !== '') {
                $lines[] = "Útvonal: {$path}";
            }
            if ($moduleId !== '') {
                $lines[] = "Modul ID: {$moduleId}";
            }
            $lines[] = $body;
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    private function coreChunks(): array
    {
        $coreIds = ['core.rules', 'core.navigation', 'core.tiers'];

        return array_values(array_filter(
            $this->chunks,
            static fn (array $chunk) => in_array($chunk['id'] ?? '', $coreIds, true),
        ));
    }

    private function containsChunkId(array $chunks, string $id): bool
    {
        foreach ($chunks as $chunk) {
            if (($chunk['id'] ?? '') === $id) {
                return true;
            }
        }

        return false;
    }

    private function tokenize(string $query): array
    {
        $text = $this->normalize($query);

        return array_values(array_filter(
            preg_split('/\s+/u', $text) ?: [],
            static fn (string $token) => mb_strlen($token) >= 2,
        ));
    }

    private function scoreChunk(array $chunk, array $tokens): int
    {
        $title = $this->normalize((string) ($chunk['title'] ?? ''));
        $body = $this->normalize((string) ($chunk['body'] ?? ''));
        $keywords = array_map(fn ($keyword) => $this->normalize((string) $keyword), $chunk['keywords'] ?? []);
        $haystack = $title.' '.$body.' '.implode(' ', $keywords);

        $score = 0;
        foreach ($tokens as $token) {
            if (str_contains($title, $token)) {
                $score += 10;
            }
            foreach ($keywords as $keyword) {
                if ($keyword !== '' && str_contains($keyword, $token)) {
                    $score += 8;
                }
            }
            if (str_contains($haystack, $token)) {
                $score += 3;
            }
        }

        if (($chunk['id'] ?? '') === 'core.rules' && $score > 0) {
            $score += 5;
        }

        return $score;
    }

    private function normalize(string $value): string
    {
        $text = mb_strtolower(trim($value));

        return str_replace(
            ['á', 'é', 'í', 'ó', 'ö', 'ő', 'ú', 'ü', 'ű'],
            ['a', 'e', 'i', 'o', 'o', 'o', 'u', 'u', 'u'],
            $text,
        );
    }
}
