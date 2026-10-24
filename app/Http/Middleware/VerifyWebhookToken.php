<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protects inbound lead webhooks with the shared token configured in Settings.
 * Accepts the token as a route segment, an "X-Api-Key" header or a "token" query param.
 */
class VerifyWebhookToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) setting('webhook_token');
        $given = (string) ($request->route('token') ?? $request->header('X-Api-Key') ?? $request->query('token'));

        if ($expected === '' || ! hash_equals($expected, $given)) {
            return response()->json(['message' => 'Invalid token.'], 401);
        }

        return $next($request);
    }
}
