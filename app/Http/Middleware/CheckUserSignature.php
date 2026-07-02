<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckUserSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Check if user has a signature uploaded
        if (!$user->signature) {
            return redirect()->back()->with('error', 'You must upload your signature before you can create a schedule. Please update your profile.');
        }

        // Check if user can be a signatory (if required)
        if (!$user->can_be_signatory) {
            return redirect()->back()->with('error', 'You must be designated as a signatory before you can create a schedule. Please contact your administrator.');
        }

        return $next($request);
    }
}