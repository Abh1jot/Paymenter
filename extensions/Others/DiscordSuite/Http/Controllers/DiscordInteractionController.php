<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Paymenter\Extensions\Others\DiscordSuite\Services\DiscordInteractionService;

class DiscordInteractionController extends Controller
{
    public function __construct(protected DiscordInteractionService $interactionService) {}

    /**
     * Handles incoming Discord interaction webhooks.
     * Signature is pre-validated by VerifyDiscordSignature middleware.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        $response = $this->interactionService->handleInteraction($payload);

        return response()->json($response);
    }
}
