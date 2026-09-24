<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Investment;
use App\Models\LevelCommissionTransaction;
use App\Models\Member;
use App\Models\RankAchievement;
use App\Models\RoiTransaction;
use App\Services\RankService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function roiReport(Request $request): View
    {
        $member = $this->currentMember($request);
        $query = $this->roiQuery($member, $request);
        $totalAmount = (clone $query)->sum('income_amount');

        return view('member.reports.roi-report', [
            'member' => $member,
            'transactions' => $query->latest()->paginate(10)->withQueryString(),
            'totalAmount' => $totalAmount,
        ]);
    }

    public function exportRoiReport(Request $request): Response
    {
        $transactions = $this->roiQuery($this->currentMember($request), $request)->latest()->get();

        return $this->csvResponse('roi-report-' . now()->format('Y-m-d') . '.csv', [
            'Serial No', 'Form Investment ID', 'Income Amount (USDT)', 'On Investment Amount (USDT)', 'Date',
        ], $transactions, function (RoiTransaction $transaction, int $index): array {
            return [
                $index + 1,
                $transaction->investment_id,
                $this->formatUsdt($transaction->income_amount),
                $this->formatUsdt($transaction->on_amount),
                $transaction->created_at?->format('d-M-Y'),
            ];
        });
    }

    public function levelIncomeReport(Request $request, RankService $rankService): View
    {
        $member = $this->currentMember($request);
        $query = $this->levelIncomeQuery($member, $request);
        $totalAmount = (clone $query)->sum('income_amount');
        $levels = LevelCommissionTransaction::query()
            ->where('member_id', $member->member_id)
            ->whereNotNull('level')
            ->distinct()
            ->orderBy('level')
            ->pluck('level');
        $rankData = $rankService->calculateForMember($member);
        $levelAccess = collect(range(1, 32))->map(fn (int $level): array => [
            'level' => $level,
            'unlocked' => $level <= $rankData['unlocked_levels'],
        ]);

        return view('member.reports.level-income', [
            'member' => $member,
            'transactions' => $query->latest()->paginate(10)->withQueryString(),
            'totalAmount' => $totalAmount,
            'levels' => $levels,
            'rankData' => $rankData,
            'levelAccess' => $levelAccess,
        ]);
    }

    public function exportLevelIncomeReport(Request $request): Response
    {
        $transactions = $this->levelIncomeQuery($this->currentMember($request), $request)->latest()->get();

        return $this->csvResponse('level-commission-report-' . now()->format('Y-m-d') . '.csv', [
            'Serial No', 'Income Amount (USDT)', 'On Amount (USDT)', 'From Member ID', 'From Level', 'Date',
        ], $transactions, function (LevelCommissionTransaction $transaction, int $index): array {
            return [
                $index + 1,
                $this->formatUsdt($transaction->income_amount),
                $this->formatUsdt($transaction->on_amount),
                $transaction->from_member_id,
                $transaction->level,
                $transaction->created_at?->format('d-M-Y'),
            ];
        });
    }

    public function rankAchievementReport(Request $request, RankService $rankService): View
    {
        $member = $this->currentMember($request);
        $query = $this->rankAchievementQuery($member, $request);
        $rankData = $rankService->calculateForMember(
            $member,
            $request->query('from_date'),
            $request->query('to_date')
        );
        $ranks = \App\Models\Rank::query()->where('is_active', true)->orderBy('sort_order')->get();

        return view('member.reports.rank-achievement', [
            'member' => $member,
            'achievements' => $query->latest('achieved_at')->paginate(10)->withQueryString(),
            'totalAmount' => $rankData['full_team_business'],
            'ranks' => $ranks,
        ]);
    }

    private function roiQuery(Member $member, Request $request)
    {
        $query = RoiTransaction::query()
            ->where('member_id', $member->member_id)
            ->whereHas('investment', function ($investmentQuery) use ($member) {
                $investmentQuery->where('member_id', $member->member_id);
            });

        $this->applyDateFilters($query, $request);

        return $query;
    }

    private function levelIncomeQuery(Member $member, Request $request)
    {
        $query = LevelCommissionTransaction::query()->where('member_id', $member->member_id);

        $this->applyDateFilters($query, $request);

        if ($request->filled('level')) {
            $query->where('level', $request->query('level'));
        }

        return $query;
    }

    private function rankAchievementQuery(Member $member, Request $request)
    {
        $query = RankAchievement::query()
            ->where('member_id', $member->member_id)
            ->with('rank');

        if ($request->filled('member_id')) {
            $memberId = trim((string) $request->query('member_id'));
            $query->where('member_id', 'like', '%' . $memberId . '%');
        }

        if ($request->filled('rank_id')) {
            $rankId = trim((string) $request->query('rank_id'));
            if ($rankId !== '') {
                $query->where('rank_id', $rankId);
            }
        }

        if ($request->filled('from_date')) {
            $query->whereDate('achieved_at', '>=', $request->query('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('achieved_at', '<=', $request->query('to_date'));
        }

        return $query;
    }

    private function applyDateFilters($query, Request $request): void
    {
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->query('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->query('to_date'));
        }
    }

    private function currentMember(Request $request): Member
    {
        return Member::where('member_id', $request->session()->get('member_context_id'))->firstOrFail();
    }

    private function csvResponse(string $filename, array $headers, Collection $records, callable $rowFormatter): Response
    {
        return response()->streamDownload(function () use ($headers, $records, $rowFormatter) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);

            foreach ($records as $index => $record) {
                fputcsv($output, $rowFormatter($record, $index));
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function formatUsdt($amount): string
    {
        return rtrim(rtrim(number_format((float) $amount, 4, '.', ''), '0'), '.') ?: '0';
    }
}
