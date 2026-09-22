<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ComingSoonController extends Controller
{
    public function show(Request $request, string $feature): View
    {
        return view('member.coming-soon', [
            'member' => Member::where('member_id', $request->session()->get('member_context_id'))->firstOrFail(),
            'feature' => Str::headline($feature),
        ]);
    }
}
