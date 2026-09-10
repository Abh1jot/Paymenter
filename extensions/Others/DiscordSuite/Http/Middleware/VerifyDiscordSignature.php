<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Http\Middleware;

use App\Helpers\ExtensionHelper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyDiscordSignature
{
    /**
     * Handle an incoming request from Discord.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $extension = ExtensionHelper::getExtension('other', 'DiscordSuite');
        $publicKey = $extension?->config('public_key');

        if (!$publicKey) {
            return response()->json(['error' => 'Discord Public Key is not configured'], 500);
        }

        $signature = $request->header('X-Signature-Ed25519');
        $timestamp = $request->header('X-Signature-Timestamp');

        if (!$signature || !$timestamp) {
            return response()->json(['error' => 'Missing interaction signature headers'], 401);
        }

        $body = $request->getContent();
        $message = $timestamp . $body;

        try {
            $binarySignature = hex2bin($signature);
            $binaryKey = hex2bin($publicKey);

            if (!function_exists('sodium_crypto_sign_verify_detached')) {
                // Ext-sodium fallback or log
                return response()->json(['error' => 'Sodium extension not available'], 500);
            }

            $valid = sodium_crypto_sign_verify_detached($binarySignature, $message, $binaryKey);

            if (!$valid) {
                return response()->json(['error' => 'Invalid interaction signature'], 401);
            }
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Signature verification failed: ' . $e->getMessage()], 401);
        }

        return $next($request);
    }
}
