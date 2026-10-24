<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware: "access:leads" allows users whose role grants the "leads" module (config/crm.php).
 */
class EnsureCanAccess
{
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $user = $request->user();

        if (! $user || ! collect($abilities)->contains(fn ($a) => $user->canAccess($a))) {
            abort(403, 'You do not have access to this section.');
        }

        return $next($request);
    }
}
