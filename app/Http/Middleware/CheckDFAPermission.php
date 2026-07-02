<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckDFAPermission
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Check if user has DFA role or permissions
        if (!$user->hasRole('Director of Finance') && !$user->hasPermissionTo('dfa.main')) {
            // Check if user has subordinate permission
            if ($user->hasPermissionTo('dfa.subordinate')) {
                // Allow access but restrict actions
                $request->attributes->set('dfa_subordinate', true);
                return $next($request);
            }
            
            abort(403, 'You do not have permission to access this page.');
        }

        // User has DFA main permissions
        $request->attributes->set('dfa_subordinate', false);
        return $next($request);
    }
}