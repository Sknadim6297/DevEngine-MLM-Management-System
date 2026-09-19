<?php

namespace App\Http\Middleware;

use App\Models\Member;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMemberContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->is_admin === true && ! $request->session()->has('member_context_id')) {
            return redirect()->route('dashboard');
        }

        $memberId = $request->session()->get('member_context_id');

        abort_unless($memberId && Member::where('member_id', $memberId)->exists(), 403);

        return $next($request);
    }
}
