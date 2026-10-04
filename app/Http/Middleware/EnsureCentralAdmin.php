<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCentralAdmin
{
    /**
     * Handle an incoming request.
     *
     * Ensures only authorized roles (master, superadmin, or central branch staff)
     * can access central goods receipt operations.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if (! $user->isMaster() && ! $user->isBranchAdmin()) {
            abort(403, 'Akses terbatas hanya untuk Master Administrator atau Staf Pengelola Gudang.');
        }

        return $next($request);
    }
}
