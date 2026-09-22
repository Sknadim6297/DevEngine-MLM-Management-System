<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminComingSoonController extends Controller
{
    public function show(Request $request, string $feature): View
    {
        $featureName = str_replace('Roi', 'ROI', Str::headline($feature));

        return view('admin.coming-soon', [
            'feature' => $featureName,
        ]);
    }
}
