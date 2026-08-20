<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use ValueError;

class EnsureRole
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        try {
            $allowed = array_map(fn (string $role): Role => Role::from($role), $roles);
        } catch (ValueError) {
            abort(500, 'Invalid role middleware configuration.');
        }

        abort_unless($user->hasRole(...$allowed), 403);

        return $next($request);
    }
}
