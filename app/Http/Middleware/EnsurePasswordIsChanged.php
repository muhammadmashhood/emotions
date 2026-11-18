<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Users created by an admin with a temporary password must choose their own before seeing anything else.
 * The Livewire update endpoint is let through so the set-password page itself can work.
 */
class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->must_change_password && ! $request->routeIs('password.force') && ! $request->is('livewire/*')) {
            return redirect()->route('password.force');
        }

        return $next($request);
    }
}
