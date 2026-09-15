<?php

namespace App\Http\Controllers;

use App\Models\ActivationWalletTransaction;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActivationWalletController extends Controller
{
    public function creditEntry()
    {
        return view('admin.activation-wallet.credit-entry');
    }

    public function memberLookup(Request $request)
    {
        $memberId = trim((string) $request->query('member_id', ''));
        $member = Member::where('member_id', $memberId)->first();

        if (! $member) {
            return response()->json(['message' => 'The selected member id is invalid.'], 404);
        }

        return response()->json([
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'activation_wallet_amount' => $member->activation_wallet_amount ?? 0,
        ]);
    }

    public function storeCreditEntry(Request $request)
    {
        $validated = $request->validate([
            'member_id' => ['required', 'string', 'exists:members,member_id'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ], [
            'member_id.exists' => 'The selected member id is invalid.',
            'amount.gt' => 'Transfer amount must be greater than 0.',
        ]);

        $transaction = DB::transaction(function () use ($validated) {
            $member = Member::where('member_id', $validated['member_id'])
                ->lockForUpdate()
                ->firstOrFail();
            $amount = (float) $validated['amount'];
            $member->activation_wallet_amount = (float) ($member->activation_wallet_amount ?? 0) + $amount;
            $member->save();

            return ActivationWalletTransaction::create([
                'member_id' => $member->member_id,
                'member_name' => $member->member_name,
                'amount' => $amount,
                'reference' => 'AW-' . strtoupper(bin2hex(random_bytes(6))),
            ]);
        });

        return redirect()->route('admin.activation-wallet.credit-entry')
            ->with('success', 'Activation Wallet transfer completed successfully.')
            ->with('transaction_id', $transaction->id);
    }

    protected function transactionQuery(Request $request)
    {
        $query = ActivationWalletTransaction::query();

        if ($request->filled('member_id')) {
            $query->where('member_id', 'like', '%' . trim((string) $request->query('member_id')) . '%');
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->query('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->query('to_date'));
        }

        return $query;
    }

    protected function validateDateFilters(Request $request): void
    {
        $toDateRules = ['nullable', 'date'];

        if ($request->filled('from_date')) {
            $toDateRules[] = 'after_or_equal:from_date';
        }

        $request->validate([
            'member_id' => ['nullable', 'string'],
            'from_date' => ['nullable', 'date'],
            'to_date' => $toDateRules,
        ]);
    }

    public function listCreditEntries(Request $request)
    {
        $this->validateDateFilters($request);
        $query = $this->transactionQuery($request);

        return view('admin.activation-wallet.credit-entry-list', [
            'transactions' => $query->latest()->paginate(10)->withQueryString(),
            'totalAmount' => (clone $query)->sum('amount'),
        ]);
    }

    public function exportCreditEntries(Request $request)
    {
        $this->validateDateFilters($request);
        $transactions = $this->transactionQuery($request)->latest()->get();

        return response()->streamDownload(function () use ($transactions) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Serial No', 'To Member ID', 'To Member Name', 'Amount (USDT)', 'Transfer Date']);

            foreach ($transactions as $index => $transaction) {
                fputcsv($output, [
                    $index + 1,
                    $transaction->member_id,
                    $transaction->member_name,
                    $this->formatUsdt($transaction->amount),
                    $transaction->created_at?->format('d-M-Y H:i:s'),
                ]);
            }

            fclose($output);
        }, 'activation-wallet-transfers-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function formatUsdt($amount): string
    {
        return rtrim(rtrim(number_format((float) $amount, 4, '.', ''), '0'), '.') ?: '0';
    }
}
