<?php

namespace Database\Seeders;

use App\Models\ActivationWalletTransaction;
use App\Models\Investment;
use App\Models\InvestmentWithdrawal;
use App\Models\LevelCommissionTransaction;
use App\Models\Member;
use App\Models\RoiTransaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CleanProductionSeeder extends Seeder
{
    private const ADMIN_ID = 'ST666666';

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->clearApplicationData();
            $this->createAdmin();

            $members = $this->createMembers();
            $this->createInvestmentsAndTransactions($members);
        });
    }

    private function clearApplicationData(): void
    {
        LevelCommissionTransaction::query()->delete();
        RoiTransaction::query()->delete();
        InvestmentWithdrawal::query()->delete();
        ActivationWalletTransaction::query()->delete();
        Investment::query()->delete();
        Member::query()->delete();
        User::query()->delete();
    }

    private function createAdmin(): void
    {
        User::create([
            'name' => 'Bright Stars Administrator',
            'email' => 'admin@gmail.com',
            'password' => bcrypt('admin123'),
            'is_admin' => true,
        ]);
    }

    private function createMembers(): array
    {
        $firstNames = [
            'Aarav', 'Aisha', 'Amit', 'Ananya', 'Arif', 'Bhavna', 'Deepak', 'Farhan',
            'Ishita', 'Kabir', 'Kavita', 'Kiran', 'Meera', 'Naveen', 'Neha', 'Pooja',
            'Rahul', 'Rakesh', 'Samir', 'Sneha',
        ];
        $lastNames = ['Sharma', 'Khan', 'Das', 'Rahman', 'Patel'];
        $members = [];

        foreach (range(1, 100) as $number) {
            $memberId = 'ST' . str_pad((string) (123455 + $number), 6, '0', STR_PAD_LEFT);
            $parentNumber = $number <= 4 ? null : intdiv($number - 1, 2);
            $sponsorId = $parentNumber === null
                ? self::ADMIN_ID
                : 'ST' . str_pad((string) (123455 + $parentNumber), 6, '0', STR_PAD_LEFT);
            $sponsorName = $parentNumber === null
                ? 'Bright Stars Administrator'
                : $members[$parentNumber]['member_name'];
            $memberName = $firstNames[($number - 1) % count($firstNames)] . ' ' . $lastNames[intdiv($number - 1, count($firstNames))];
            $activationBalance = 250 + (($number * 37) % 1750);
            $workingBalance = 100 + (($number * 23) % 900);
            $roiBalance = 50 + (($number * 19) % 650);

            $members[$number] = [
                'member_id' => $memberId,
                'sponsor_id' => $sponsorId,
                'sponsor_name' => $sponsorName,
                'member_name' => $memberName,
                'wallet_address' => '0x' . str_pad(strtolower(dechex(10000000 + $number)), 12, '0', STR_PAD_LEFT),
                'activation_wallet_amount' => number_format($activationBalance, 4, '.', ''),
                'working_wallet_amount' => number_format($workingBalance, 4, '.', ''),
                'roi_wallet_amount' => number_format($roiBalance, 4, '.', ''),
                'mobile_no' => (string) (7000000000 + $number),
                'pan_card_no' => 'ABCP' . str_pad((string) (1000 + $number), 4, '0', STR_PAD_LEFT) . 'K',
                'email' => 'member' . str_pad((string) $number, 3, '0', STR_PAD_LEFT) . '@brightstars.example',
                'password' => bcrypt('member12345'),
                'status' => $number <= 70 ? 'active' : 'inactive',
            ];

            Member::create($members[$number]);
        }

        return $members;
    }

    private function createInvestmentsAndTransactions(array $members): void
    {
        $baseDate = CarbonImmutable::parse('2026-08-01', 'Asia/Kolkata')->setTimezone('UTC');

        foreach (range(1, 100) as $number) {
            $member = $members[$number];
            $amount = 100 + (($number - 1) % 10) * 50;
            $isClosed = $number > 70;
            $investmentDate = $baseDate->addDays(($number - 1) % 20);
            $closedAt = $isClosed ? $investmentDate->addDays(30) : null;
            $investmentId = 'INV' . str_pad((string) (230000 + $number), 6, '0', STR_PAD_LEFT);
            $closingAmount = $isClosed ? $amount * 3 : null;

            Investment::create([
                'investment_id' => $investmentId,
                'member_id' => $member['member_id'],
                'member_name' => $member['member_name'],
                'amount' => number_format($amount, 4, '.', ''),
                'closing_amount' => $closingAmount === null ? null : number_format($closingAmount, 4, '.', ''),
                'status' => $isClosed ? 'expired' : 'active',
                'closed_at' => $closedAt,
                'created_at' => $investmentDate,
                'updated_at' => $closedAt ?? $investmentDate,
            ]);

            ActivationWalletTransaction::create([
                'member_id' => $member['member_id'],
                'member_name' => $member['member_name'],
                'amount' => number_format(100 + ($number % 5) * 25, 4, '.', ''),
                'type' => 'credit',
                'remarks' => 'Initial activation wallet funding',
                'reference' => 'AW-' . $investmentId,
                'created_at' => $investmentDate,
                'updated_at' => $investmentDate,
            ]);

            $roiAmount = $isClosed ? $amount * 2 : round($amount / 600, 4);
            RoiTransaction::create([
                'reference' => 'ROI-' . $investmentId,
                'investment_id' => $investmentId,
                'member_id' => $member['member_id'],
                'member_name' => $member['member_name'],
                'on_amount' => number_format($amount, 4, '.', ''),
                'rate_percentage' => '5.000',
                'income_amount' => number_format($roiAmount, 4, '.', ''),
                'roi_date' => $investmentDate->toDateString(),
                'status' => 'generated',
                'withdrawable_on' => $investmentDate->addMonth()->toDateString(),
                'created_at' => $investmentDate,
                'updated_at' => $investmentDate,
            ]);

            if ($member['sponsor_id'] !== self::ADMIN_ID) {
                $commissionAmount = $isClosed ? $amount : round($amount * 0.01, 4);
                LevelCommissionTransaction::create([
                    'reference' => 'LC-' . $investmentId,
                    'investment_id' => $investmentId,
                    'member_id' => $member['sponsor_id'],
                    'member_name' => $member['sponsor_name'],
                    'from_member_id' => $member['member_id'],
                    'from_member_name' => $member['member_name'],
                    'level' => 1,
                    'business_date' => $investmentDate->toDateString(),
                    'on_amount' => number_format($amount, 4, '.', ''),
                    'rate_percentage' => '1.000',
                    'income_amount' => number_format($commissionAmount, 4, '.', ''),
                    'created_at' => $investmentDate,
                    'updated_at' => $investmentDate,
                ]);
            }

            if ($isClosed && $number % 3 === 0) {
                InvestmentWithdrawal::create([
                    'withdrawal_id' => 'WD' . str_pad((string) (340000 + $number), 6, '0', STR_PAD_LEFT),
                    'member_id' => $member['member_id'],
                    'member_name' => $member['member_name'],
                    'investment_id' => $investmentId,
                    'investment_amount' => number_format($amount, 4, '.', ''),
                    'withdrawal_amount' => number_format($amount * 3, 4, '.', ''),
                    'status' => 'paid',
                    'withdrawn_at' => $closedAt,
                    'created_at' => $closedAt,
                    'updated_at' => $closedAt,
                ]);
            }
        }
    }
}
