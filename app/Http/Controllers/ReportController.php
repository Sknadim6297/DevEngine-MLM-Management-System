<?php

namespace App\Http\Controllers;

use App\Models\LevelCommissionTransaction;
use App\Models\Member;
use App\Models\RankAchievement;
use App\Models\RoiTransaction;
use App\Services\RankService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ReportController extends Controller
{
    public function roiReport(Request $request)
    {
        $query = $this->roiQuery($request);

        $summary = (clone $query)
            ->selectRaw('COALESCE(SUM(income_amount), 0) as total_amount, COUNT(*) as total_rows')
            ->first();

        $totalAmount = (float) ($summary->total_amount ?? 0);
        $totalRows = (int) ($summary->total_rows ?? 0);
        $perPage = max(1, min(100, (int) $request->query('per_page', 10)));
        $lastPage = max(1, (int) ceil($totalRows / $perPage));
        $page = max(1, min((int) $request->query('page', 1), $lastPage));

        $baseQuery = $query
            ->select(['id', 'member_id', 'member_name', 'investment_id', 'income_amount', 'on_amount', 'created_at'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $transactions = $this->paginateWithCursor($baseQuery, $page, $perPage, $totalRows, $request);

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

        $aggregate = (clone $query)
            ->selectRaw('SUM(income_amount) as total_amount, COUNT(*) as total_rows')
            ->first();

        $totalAmount = (float) ($aggregate->total_amount ?? 0);
        $perPage = max(1, min(100, (int) $request->query('per_page', 50)));
        $totalRows = (int) ($aggregate->total_rows ?? 0);
        $lastPage = max(1, (int) ceil($totalRows / $perPage));
        $page = max(1, min(max(1, (int) $request->query('page', 1)), $lastPage));

        $baseQuery = $query
            ->select(['id', 'member_id', 'member_name', 'from_member_id', 'level', 'income_amount', 'on_amount', 'created_at'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $transactions = $this->paginateWithCursor($baseQuery, $page, $perPage, $totalRows, $request);

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
        $transactions = $this->levelIncomeQuery($request)
            ->select(['id', 'member_id', 'member_name', 'from_member_id', 'level', 'income_amount', 'on_amount', 'created_at'])
            ->latest('created_at')
            ->orderByDesc('id');

        return response()->streamDownload(function () use ($transactions) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Serial No', 'Member ID', 'Name', 'Income Amount (USDT)', 'On Amount (USDT)', 'From Member ID', 'From Level', 'Date']);

            $serial = 0;
            $lastCreatedAt = null;
            $lastId = null;

            do {
                $batch = (clone $transactions)
                    ->when($lastCreatedAt !== null, function ($query) use ($lastCreatedAt, $lastId): void {
                        $query->where(function ($cursor) use ($lastCreatedAt, $lastId): void {
                            $cursor->where('created_at', '<', $lastCreatedAt)
                                ->orWhere(function ($tie) use ($lastCreatedAt, $lastId): void {
                                    $tie->where('created_at', $lastCreatedAt)
                                        ->where('id', '<', $lastId);
                                });
                        });
                    })
                    ->limit(1000)
                    ->get();

                foreach ($batch as $transaction) {
                    fputcsv($output, [
                        ++$serial,
                        $transaction->member_id,
                        $transaction->member_name,
                        $this->formatUsdt($transaction->income_amount),
                        $this->formatUsdt($transaction->on_amount),
                        $transaction->from_member_id,
                        $transaction->level,
                        $transaction->created_at?->format('d-M-Y'),
                    ]);

                    $lastCreatedAt = $transaction->created_at;
                    $lastId = $transaction->id;
                }
            } while ($batch->count() === 1000);

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
        $query = $this->rankAchievementQuery($request);
        $totalAmount = $this->rankAchievementTotal($request, $rankService);
        $achievements = $query->latest('achieved_at')->paginate(10)->withQueryString();
        $ranks = \App\Models\Rank::query()->where('is_active', true)->orderBy('sort_order')->get();

        return view('admin.reports.rank-achievement', [
            'achievements' => $achievements,
            'totalAmount' => $totalAmount,
            'ranks' => $ranks,
        ]);
    }

    protected function rankAchievementTotal(Request $request, RankService $rankService): string
    {
        if (! $request->filled('member_id')) {
            return (string) RankAchievement::query()
                ->sum('qualifying_business_amount');
        }

        $memberIds = Member::query()
            ->where('member_id', 'like', '%' . trim((string) $request->query('member_id')) . '%')
            ->pluck('member_id')
            ->all();

        if ($memberIds === []) {
            return '0.0000';
        }

        $calculations = $rankService->calculateForMembers(
            $memberIds,
            $request->query('from_date'),
            $request->query('to_date')
        );

        $total = '0.0000';
        foreach ($calculations as $calculation) {
            $total = bcadd($total, (string) $calculation['full_team_business'], 4);
        }

        return $total;
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
        $query = RankAchievement::query()
            ->select(['id', 'member_id', 'member_name', 'rank_id', 'achieved_at'])
            ->with('rank:id,name');

        if ($request->filled('member_id')) {
            $memberId = trim((string) $request->query('member_id'));
            if ($memberId !== '') {
                $query->where('member_id', 'like', $memberId . '%');
            }
        }

        if ($request->filled('rank_id')) {
            $rankId = trim((string) $request->query('rank_id'));
            if ($rankId !== '') {
                $query->where('rank_id', $rankId);
            }
        }

        if ($request->filled('from_date')) {
            $query->where('achieved_at', '>=', CarbonImmutable::parse($request->query('from_date'))->startOfDay());
        }

        if ($request->filled('to_date')) {
            $query->where('achieved_at', '<', CarbonImmutable::parse($request->query('to_date'))->addDay()->startOfDay());
        }

        return $query;
    }

    protected function applyCommonFilters($query, Request $request): void
    {
        if ($request->filled('member_id')) {
            $memberId = trim((string) $request->query('member_id'));
            if ($memberId !== '') {
                $nextMemberId = $memberId . chr(255);

                $query->where(function ($memberQuery) use ($memberId, $nextMemberId): void {
                    $memberQuery->where('member_id', '>=', $memberId)
                        ->where('member_id', '<', $nextMemberId);
                });
            }
        }

        if ($request->filled('from_date')) {
            $query->where('created_at', '>=', CarbonImmutable::parse($request->query('from_date'))->startOfDay());
        }

        if ($request->filled('to_date')) {
            $query->where('created_at', '<', CarbonImmutable::parse($request->query('to_date'))->addDay()->startOfDay());
        }
    }

    protected function paginateWithCursor($query, int $page, int $perPage, int $totalRows, Request $request): LengthAwarePaginator
    {
        if ($page > 1) {
            $previousPageOffset = max(0, (($page - 1) * $perPage) - 1);
            $cursor = (clone $query)
                ->select(['created_at', 'id'])
                ->offset($previousPageOffset)
                ->limit(1)
                ->first();

            if ($cursor) {
                $query->where(function ($where) use ($cursor): void {
                    $where->where('created_at', '<', $cursor->created_at)
                        ->orWhere(function ($orWhere) use ($cursor): void {
                            $orWhere->where('created_at', $cursor->created_at)
                                ->where('id', '<', $cursor->id);
                        });
                });
            }
        }

        $items = $query
            ->limit($perPage)
            ->get();

        return new LengthAwarePaginator(
            $items,
            $totalRows,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }

    protected function formatUsdt($amount): string
    {
        return rtrim(rtrim(number_format((float) $amount, 4, '.', ''), '0'), '.') ?: '0';
    }
}
