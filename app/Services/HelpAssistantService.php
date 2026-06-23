<?php

namespace App\Services;

use App\Integrations\OpenAI\OpenAiChatClient;
use App\Models\Household;
use App\Models\User;
use App\Support\AccessControl;
use App\Support\AiUsageContext;
use App\Support\HelpKnowledgeCatalog;
use App\Support\PlatformModules;

class HelpAssistantService
{
    public function __construct(
        private readonly OpenAiChatClient $client,
        private readonly AiTokenUsageService $tokenUsage,
        private readonly HelpKnowledgeCatalog $knowledge,
    ) {}

    public function chat(User $user, string $message, array $history = []): array
    {
        $household = $user->household;
        $usageContext = new AiUsageContext(
            $household?->id,
            $user->id,
            'help_assistant',
        );

        if ($household !== null) {
            $this->assertHelpAssistantAllowed($household);
        }

        $systemPrompt = $this->buildSystemPrompt($message);
        $relevantChunks = $this->knowledge->retrieve($message);
        $userPayload = json_encode([
            'user_context' => $this->buildUserContext($user),
            'conversation_history' => $this->normalizeHistory($history),
            'question' => $message,
            'knowledge_chunks_used' => count($relevantChunks),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPayload],
        ];

        $result = $this->client->chatJson($messages, ['temperature' => 0.25]);
        $this->tokenUsage->record($usageContext, $result['model'], $result['usage']);

        $response = $this->normalizeResponse(is_array($result['content']) ? $result['content'] : []);

        return $this->recoverRejectedAppQuestion($message, $response);
    }

    private function recoverRejectedAppQuestion(string $message, array $response): array
    {
        if (($response['status'] ?? '') !== 'rejected') {
            return $response;
        }

        if (! $this->isAppRelatedQuestion($message)) {
            return $response;
        }

        return [
            'status' => 'answered',
            'message' => 'Ez az alkalmazással kapcsolatos kérdés. A releváns részletek a tudásbázisban vannak — '
                .'fogalmazd újra konkrétabban (melyik modul, mit szeretnél csinálni), vagy nézd meg a /help oldalt.',
            'links' => [
                ['label' => 'Súgó oldal', 'path' => '/help', 'kind' => 'help'],
            ],
        ];
    }

    private function isAppRelatedQuestion(string $message): bool
    {
        if ($this->knowledge->retrieve($message, 1) !== []) {
            return true;
        }

        $text = $this->normalizeForMatch($message);

        $appTerms = [
            'osszhang',
            'összhang',
            'penzpilot',
            'app',
            'alkalmazas',
            'alkalmazás',
            'modul',
            'koltsegvet',
            'költségvet',
            'budget',
            'rezsi',
            'megtakarit',
            'megtakarít',
            'tartoz',
            'elofizet',
            'előfizet',
            'csomag',
            'pro',
            'premium',
            'beallit',
            'beállít',
            'kassza',
            'wallet',
            'utaz',
            'vallalkoz',
            'vállalkoz',
            'shopify',
            'webshop',
            'hol van',
            'hogyan',
            'lehetseges',
            'lehetséges',
            'elerheto',
            'elérhető',
            'funkcio',
            'funkció',
        ];

        foreach ($appTerms as $term) {
            if (str_contains($text, $term)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeForMatch(string $value): string
    {
        $text = mb_strtolower(trim($value));

        return str_replace(
            ['á', 'é', 'í', 'ó', 'ö', 'ő', 'ú', 'ü', 'ű'],
            ['a', 'e', 'i', 'o', 'o', 'o', 'u', 'u', 'u'],
            $text,
        );
    }

    private function buildSystemPrompt(string $message): string
    {
        $appName = $this->knowledge->appName();
        $chunks = $this->knowledge->retrieve($message);
        $knowledge = $this->knowledge->formatChunksForPrompt($chunks);
        $chunkCount = $this->knowledge->chunkCount();

        return <<<PROMPT
Te az {$appName} alkalmazás hivatalos súgó asszisztense vagy.

A KNOWLEDGE_BASE az app teljes, hivatalos dokumentációjából származik ({$chunkCount} tudás-blokk összesen).
A kérdéshez releváns részeket kaptad — EZ A FORRÁS, ne találj ki mást.

SZABÁLYOK:
1. Csak az alkalmazás használatára válaszolj. Általános tudás / programozás / más app → status "rejected".
2. „Lehetséges-e”, „van-e”, „hogyan” kérdések az appról → MINDIG status "answered".
3. Ha funkció nincs az appban: mondd meg egyértelműen (answered), ne utasítsd vissza.
4. Személyre szabás: user_context (csomag, modul bekapcsolva, jogosultság, admin-e).
5. Admin-only műveletek (pl. kategória, modul kapcsoló): jelezd, ha a user nem admin.
6. Ne találj ki funkciót vagy menüpontot — csak a KNOWLEDGE_BASE és user_context alapján.
7. Ne elemezd a felhasználó konkrét pénzügyi adatait.
8. Ha nem vagy biztos: irányítsd a /help oldalra, ne hallucinálj.

Válaszformátum: CSAK érvényes JSON:
{"status":"answered|rejected","message":"...","links":[{"label":"...","path":"/...","kind":"module|settings|pricing|help"}]}

KNOWLEDGE_BASE (releváns részletek):
{$knowledge}
PROMPT;
    }

    private function buildUserContext(User $user): array
    {
        $household = $user->household;
        $effectiveTier = AccessControl::effectiveTier($user);
        $paths = config('help_assistant.module_paths', []);
        $labels = config('help_assistant.module_labels', []);
        $moduleTiers = config('help_assistant.module_tiers', []);

        $modules = [];
        foreach (AccessControl::MODULES as $moduleId) {
            $tierRequired = $moduleTiers[$moduleId] ?? null;
            $modules[$moduleId] = [
                'label' => $labels[$moduleId] ?? $moduleId,
                'path' => $paths[$moduleId] ?? null,
                'tier_required' => $tierRequired,
                'tier_access' => AccessControl::canAccessModuleByTier($user, $moduleId),
                'enabled_in_household' => AccessControl::isHouseholdModuleEnabled($household, $moduleId),
                'released' => PlatformModules::isReleased($moduleId),
                'user_can_access' => AccessControl::canAccessModule($user, $moduleId),
            ];
        }

        $features = [];
        foreach (AccessControl::PREMIUM_FEATURES as $featureId) {
            $features[$featureId] = [
                'label' => config("help_assistant.feature_labels.{$featureId}", $featureId),
                'tier_required' => config("help_assistant.feature_tiers.{$featureId}", 'premium'),
                'available' => AccessControl::canUseFeature($user, $featureId),
            ];
        }
        foreach (AccessControl::PRO_FEATURES as $featureId) {
            $features[$featureId] = [
                'label' => config("help_assistant.feature_labels.{$featureId}", $featureId),
                'tier_required' => config("help_assistant.feature_tiers.{$featureId}", 'pro'),
                'available' => AccessControl::canUseFeature($user, $featureId),
            ];
        }

        return [
            'user' => [
                'first_name' => $user->first_name,
                'role' => $user->role,
                'is_admin' => $user->role === 'admin' || $user->lifetime_admin,
                'permissions' => $user->permissions ?? [],
                'lifetime_admin' => (bool) $user->lifetime_admin,
            ],
            'household' => [
                'name' => $household?->name,
                'effective_tier' => $effectiveTier,
                'billing_tier' => AccessControl::billingTier($user),
                'business_name' => $household?->business_name,
            ],
            'modules' => $modules,
            'features' => $features,
            'private_wallet_available' => AccessControl::canCreatePrivateWallet($user),
            'max_wallets' => AccessControl::maxWallets($user),
            'routes' => [
                'pricing' => '/pricing',
                'help' => '/help',
                'settings' => '/settings',
                'settings_modules' => '/settings?tab=modules',
            ],
        ];
    }

    private function normalizeHistory(array $history): array
    {
        $normalized = [];
        foreach (array_slice($history, -12) as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $role = $entry['role'] ?? null;
            $content = trim((string) ($entry['content'] ?? ''));
            if (! in_array($role, ['user', 'assistant'], true) || $content === '') {
                continue;
            }
            $normalized[] = ['role' => $role, 'content' => mb_substr($content, 0, 2000)];
        }

        return $normalized;
    }

    private function normalizeResponse(array $payload): array
    {
        $status = ($payload['status'] ?? '') === 'rejected' ? 'rejected' : 'answered';
        $message = trim((string) ($payload['message'] ?? ''));
        if ($message === '') {
            $message = $status === 'rejected'
                ? 'Csak az alkalmazás használatával kapcsolatos kérdésekre tudok válaszolni. Próbáld újra egy modullal vagy funkcióval!'
                : 'Sajnos most nem tudok pontos választ adni. Nézd meg a részletes Súgó oldalt (/help), vagy fogalmazd újra a kérdést.';
        }

        $links = [];
        foreach ($payload['links'] ?? [] as $link) {
            if (! is_array($link)) {
                continue;
            }
            $path = trim((string) ($link['path'] ?? ''));
            $label = trim((string) ($link['label'] ?? ''));
            if ($path === '' || $label === '') {
                continue;
            }
            if (! str_starts_with($path, '/')) {
                continue;
            }
            $kind = (string) ($link['kind'] ?? 'module');
            if (! in_array($kind, ['module', 'settings', 'pricing', 'help'], true)) {
                $kind = 'module';
            }
            $links[] = ['label' => $label, 'path' => $path, 'kind' => $kind];
        }

        return [
            'status' => $status,
            'message' => $message,
            'links' => $links,
        ];
    }

    private function assertHelpAssistantAllowed(Household $household): void
    {
        if ($household->ai_usage_blocked) {
            abort(response()->json([
                'message' => 'Az AI funkciók ehhez a háztartáshoz admin által le vannak tiltva, így a súgó asszisztens sem használható.',
                'code' => 'AI_USAGE_BLOCKED',
            ], 403));
        }
    }
}
