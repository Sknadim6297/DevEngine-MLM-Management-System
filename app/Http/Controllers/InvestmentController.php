<?php

namespace App\Http\Controllers;

use App\Models\Investment;
use App\Models\Member;
use App\Services\LevelCommissionGenerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InvestmentController extends Controller
{
    public function __construct(
        private readonly LevelCommissionGenerationService $levelCommissionGenerationService
    ) {
    }

    public function investmentEntry(Request $request)
    {
        $investmentId = trim((string) $request->old('investment_id', ''));

        if ($investmentId === '') {
            $investmentId = trim((string) $request->session()->get('generated_investment_id', ''));
        }

        if ($investmentId === '') {
            $investmentId = $this->generateInvestmentId();
            $request->session()->put('generated_investment_id', $investmentId);
        }

        return view('admin.invesment.investment-entry', [
            'investmentId' => $investmentId,
        ]);
    }

    public function storeInvestment(Request $request)
    {
        $investmentId = trim((string) $request->input('investment_id', ''));

        if ($investmentId === '') {
            $investmentId = trim((string) $request->session()->get('generated_investment_id', ''));
        }

        if ($investmentId === '') {
            $investmentId = $this->generateInvestmentId();
        }

        $validatedData = $request->validate([
            'investment_id' => [
                'required',
                'string',
                'max:255',
                Rule::unique('investments', 'investment_id'),
            ],
            'member_id' => ['required', 'string', 'exists:members,member_id'],
            'amount' => ['required', 'numeric', 'min:100'],
        ], [
            'member_id.exists' => 'The selected member id is invalid.',
            'amount.min' => 'Investment Amount must be at least 100 USDT.',
        ]);

        $validatedData['investment_id'] = $investmentId;

        DB::transaction(function () use ($validatedData) {
            $member = Member::where('member_id', $validatedData['member_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $investment = Investment::create([
                'investment_id' => $validatedData['investment_id'],
                'member_id' => $member->member_id,
                'member_name' => $member->member_name,
                'amount' => $validatedData['amount'],
                'status' => 'active',
            ]);

            if ($member->status !== 'active') {
                $member->update(['status' => 'active']);
            }

            $this->levelCommissionGenerationService->generateForInvestment($investment);
        });

        $request->session()->forget('generated_investment_id');

        return redirect()->route('admin.investments.entry')->with('success', 'Investment entry created successfully.');
    }

    public function memberLookup(Request $request)
    {
        $memberId = trim((string) $request->query('member_id', ''));
        $member = Member::where('member_id', $memberId)->first();

        if (! $member) {
            return response()->json([
                'message' => 'The selected member id is invalid.',
            ], 404);
        }

        return response()->json([
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
        ]);
    }

    public function activeInvestments(Request $request)
    {
        $query = Investment::with('member')
            ->where('status', 'active')
            ->where('amount', '>=', 100);

        if ($request->filled('member_id')) {
            $memberSearch = trim((string) $request->query('member_id'));
            $query->where(function ($investmentQuery) use ($memberSearch) {
                $investmentQuery->where('member_id', 'like', '%' . $memberSearch . '%')
                    ->orWhere('member_name', 'like', '%' . $memberSearch . '%');
            });
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->query('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->query('to_date'));
        }

        $totalAmount = (clone $query)->sum('amount');

        return view('admin.invesment.active-invesment-list', [
            'investments' => $query->latest()->paginate(10)->withQueryString(),
            'totalAmount' => $totalAmount,
        ]);
    }

    public function exportActiveInvestments(Request $request)
    {
        $query = Investment::where('status', 'active')
            ->where('amount', '>=', 100);

        if ($request->filled('member_id')) {
            $memberSearch = trim((string) $request->query('member_id'));
            $query->where(function ($investmentQuery) use ($memberSearch) {
                $investmentQuery->where('member_id', 'like', '%' . $memberSearch . '%')
                    ->orWhere('member_name', 'like', '%' . $memberSearch . '%');
            });
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->query('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->query('to_date'));
        }

        $investments = $query->latest()->get();

        return response()->streamDownload(function () use ($investments) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Serial No', 'Member ID', 'Name', 'Investment ID', 'Investment Amount (USDT)', 'Investment Date']);

            foreach ($investments as $index => $investment) {
                fputcsv($output, [
                    $index + 1,
                    $investment->member_id,
                    $investment->member_name,
                    $investment->investment_id,
                    $this->formatUsdt($investment->amount),
                    $investment->created_at?->format('d-m-Y'),
                ]);
            }

            fclose($output);
        }, 'active-investments-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function generateInvestmentId(): string
    {
        do {
            $investmentId = 'INV' . random_int(100000, 999999);
        } while (Investment::where('investment_id', $investmentId)->exists());

        return $investmentId;
    }

    protected function formatUsdt($amount): string
    {
        return rtrim(rtrim(number_format((float) $amount, 4, '.', ''), '0'), '.') ?: '0';
    }
    public function closedInvestments(Request $request)
    {
        $query = Investment::where('status', 'closed')
            ->where('amount', '>=', 100);

        if ($request->filled('member_id')) {
            $memberSearch = trim((string) $request->query('member_id'));
            $query->where(function ($investmentQuery) use ($memberSearch) {
                $investmentQuery->where('member_id', 'like', '%' . $memberSearch . '%')
                    ->orWhere('member_name', 'like', '%' . $memberSearch . '%');
            });
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->query('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->query('to_date'));
        }

        $totalAmount = $query->sum('amount');

        return view('admin.invesment.closed-investment-list', [
            'investments' => $query->latest()->paginate(10)->withQueryString(),
            'totalAmount' => $totalAmount,
        ]);
    }
    public function investmentWithdrawalEntry()
    {
        return view('admin.invesment.investment-withdrawal-entry');
    }

    public function investmentWithdrawalList()
    {
        return view('admin.invesment.investment-withdrawal-list');
    }

}