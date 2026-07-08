<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * مثال استفاده در routes: ->middleware('role:admin')
     * یا برای چند نقش: ->middleware('role:admin,specialist')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            return response()->json([
                'message' => 'شما دسترسی لازم برای این بخش را ندارید.',
            ], 403);
        }

        return $next($request);
    }
}
