<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Help\HelpAssistantChatRequest;
use App\Services\HelpAssistantService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class HelpAssistantController extends Controller
{
    public function __construct(private readonly HelpAssistantService $helpAssistant) {}

    public function chat(HelpAssistantChatRequest $request)
    {
        try {
            return response()->json(
                $this->helpAssistant->chat(
                    $request->user(),
                    $request->input('message'),
                    $request->input('history', []),
                ),
            );
        } catch (AuthorizationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        } catch (HttpException $exception) {
            $payload = $exception->getResponse()?->getContent();
            $decoded = is_string($payload) ? json_decode($payload, true) : null;
            if (is_array($decoded) && isset($decoded['message'])) {
                return response()->json($decoded, $exception->getStatusCode());
            }

            return response()->json(['message' => $exception->getMessage()], $exception->getStatusCode());
        } catch (\Throwable $exception) {
            Log::error('Help assistant chat failed', [
                'user_id' => $request->user()?->id,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'A súgó asszisztens átmenetileg nem érhető el. Próbáld újra később, vagy nézd meg a /help oldalt.',
            ], 503);
        }
    }
}
