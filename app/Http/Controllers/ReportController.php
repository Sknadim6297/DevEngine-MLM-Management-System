<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function roiReport(Request $request)
    {
        return view('admin.reports.roi-report');
    }

    public function levelIncomeReport(Request $request)
    {
        return view('admin.reports.level-income');
    }

    public function salaryReport(Request $request)
    {
        return view('admin.reports.salary');
    }

    public function rankAchievementReport(Request $request)
    {
        return view('admin.reports.rank-achievement');
    }
}
