<?php

namespace App\Http\Controllers;

use App\Models\LevelCommissionTransaction;
use App\Models\Member;
use App\Models\RankAchievement;
use App\Models\RoiTransaction;
use App\Services\RankService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function roiReport(Request $request)
    {
        $query = $this->roiQuery($request);
        $totalAmount = (clone $query)->sum('income_amount');
        $transactions = $query->latest()->paginate(10)->withQueryString();

        return view('admin.reports.roi-report', [
            'transactions' => $transactions,
            'totalAmount' => $totalAmount,
        ]);
    }

    public function exportRoiReport(Request $request)
    {
        $transactions = $this->roiQuery($request)->latest()->get();

        return response()->streamDownload(function () use ($transactions) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Serial No', 'Member ID', 'Name', 'Investment ID', 'Income Amount (USDT)', 'On Amount (USDT)', 'Date']);

            foreach ($transactions as $index => $transaction) {
                fputcsv($output, [
                    $index + 1,
                    $transaction->member_id,
                    $transaction->member_name,
                    $transaction->investment_id,
                    $this->formatUsdt($transaction->income_amount),
                    $this->formatUsdt($transaction->on_amount),
                    $transaction->created_at?->format('d-M-Y'),
                ]);
            }

            fclose($output);
        }, 'roi-report-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function levelIncomeReport(Request $request)
    {
        $query = $this->levelIncomeQuery($request);
        $totalAmount = (clone $query)->sum('income_amount');
        $transactions = $query->latest()->paginate(10)->withQueryString();
        $levels = LevelCommissionTransaction::query()
            ->whereNotNull('level')
            ->distinct()
            ->orderBy('level')
            ->pluck('level');

        return view('admin.reports.level-income', [
            'transactions' => $transactions,
            'totalAmount' => $totalAmount,
            'levels' => $levels,
        ]);
    }

    public function exportLevelIncomeReport(Request $request)
    {
        $transactions = $this->levelIncomeQuery($request)->latest()->get();

        return response()->streamDownload(function () use ($transactions) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Serial No', 'Member ID', 'Name', 'Income Amount (USDT)', 'On Amount (USDT)', 'From Member ID', 'From Level', 'Date']);

            foreach ($transactions as $index => $transaction) {
                fputcsv($output, [
                    $index + 1,
                    $transaction->member_id,
                    $transaction->member_name,
                    $this->formatUsdt($transaction->income_amount),
                    $this->formatUsdt($transaction->on_amount),
                    $transaction->from_member_id,
                    $transaction->level,
                    $transaction->created_at?->format('d-M-Y'),
                ]);
            }

            fclose($output);
        }, 'level-commission-report-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function salaryReport(Request $request)
    {
        return view('admin.reports.salary');
    }

    public function rankAchievementReport(Request $request, RankService $rankService)
    {
        $rankService->syncMemberRankAchievements(Member::query()->pluck('member_id')->all());

        $query = $this->rankAchievementQuery($request);
        $totalAmount = (clone $query)->sum('qualifying_business_amount');
        $achievements = $query->latest('achieved_at')->paginate(10)->withQueryString();

        return view('admin.reports.rank-achievement', [
            'achievements' => $achievements,
            'totalAmount' => $totalAmount,
        ]);
    }

    protected function roiQuery(Request $request)
    {
        $query = RoiTransaction::query();

        $this->applyCommonFilters($query, $request);

        return $query;
    }

    protected function levelIncomeQuery(Request $request)
    {
        $query = LevelCommissionTransaction::query();

        $this->applyCommonFilters($query, $request);

        if ($request->filled('level')) {
            $query->where('level', $request->query('level'));
        }

        return $query;
    }

    protected function rankAchievementQuery(Request $request)
    {
        $query = RankAchievement::query()->with('rank');

        if ($request->filled('member_id')) {
            $memberId = trim((string) $request->query('member_id'));
            $query->where('member_id', 'like', '%' . $memberId . '%');
        }

        if ($request->filled('from_date')) {
            $query->whereDate('achieved_at', '>=', $request->query('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('achieved_at', '<=', $request->query('to_date'));
        }

        return $query;
    }

    protected function applyCommonFilters($query, Request $request): void
    {
        if ($request->filled('member_id')) {
            $memberId = trim((string) $request->query('member_id'));
            $query->where('member_id', 'like', '%' . $memberId . '%');
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->query('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->query('to_date'));
        }
    }

    protected function formatUsdt($amount): string
    {
        return rtrim(rtrim(number_format((float) $amount, 4, '.', ''), '0'), '.') ?: '0';
    }
}
