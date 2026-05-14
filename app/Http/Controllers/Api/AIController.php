<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OpenAIService;
use Illuminate\Http\Request;

class AIController extends Controller
{
    protected $ai;

    public function __construct(OpenAIService $ai)
    {
        $this->ai = $ai;
    }

    /**
     * Handle generic financial AI queries.
     */
    public function query(Request $request)
    {
        $request->validate([
            'prompt' => 'required|string|max:1000',
            'include_context' => 'boolean'
        ]);

        $context = [];
        if ($request->include_context) {
            $user = $request->user();
            // Basic context for AI
            $context = [
                'user_name' => $user->first_name,
                'household' => $user->household->name,
                'current_month' => date('F'),
                // Here we could add summarized budget data, etc.
            ];
        }

        $response = $this->ai->ask($request->prompt, $context);

        return response()->json([
            'answer' => $response
        ]);
    }
}
