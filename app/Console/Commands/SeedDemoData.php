<?php

namespace App\Console\Commands;

use App\Models\Investment;
use App\Models\Member;
use App\Services\LevelCommissionGenerationService;
use App\Services\RankService;
use App\Services\RoiGenerationService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SeedDemoData extends Command
{
    protected $signature = 'demo:seed {--date= : Business date override (YYYY-MM-DD)}';

    protected $description = 'Create a safe, idempotent demo dataset under reserved DEMO- prefixed records.';

    public function handle(
        RankService $rankService,
        RoiGenerationService $roiGenerationService,
        LevelCommissionGenerationService $levelCommissionGenerationService,
    ): int {
        $businessDate = $this->option('date')
            ? CarbonImmutable::parse($this->option('date'), RoiGenerationService::TIMEZONE)->startOfDay()
            : CarbonImmutable::now(RoiGenerationService::TIMEZONE)->startOfDay();

        if (! app()->environment(['local', 'testing'])) {
            $this->error('The demo dataset command is reserved for local and testing environments only.');

            return self::FAILURE;
        }

        $result = $this->seedDataset($businessDate, $rankService, $roiGenerationService, $levelCommissionGenerationService);

        $this->info(sprintf(
            'Created %d demo members, %d demo investments, and generated %d ROI records for %s.',
            $result['members'],
            $result['investments'],
            $result['roi_records'],
            $businessDate->toDateString()
        ));

        return self::SUCCESS;
    }

    public function seedDataset(
        CarbonImmutable $businessDate,
        ?RankService $rankService = null,
        ?RoiGenerationService $roiGenerationService = null,
        ?LevelCommissionGenerationService $levelCommissionGenerationService = null,
    ): array {
        if (! app()->environment(['local', 'testing'])) {
            return ['members' => 0, 'investments' => 0, 'roi_records' => 0];
        }

        $rankService ??= app(RankService::class);
        $roiGenerationService ??= app(RoiGenerationService::class);
        $levelCommissionGenerationService ??= app(LevelCommissionGenerationService::class);

        $memberPlan = $this->memberPlan();
        $investmentPlan = $this->investmentPlan();

        $createdMembers = 0;
        $createdInvestments = 0;
        $memberMap = [];

        foreach ($memberPlan as $memberData) {
            $member = Member::query()->firstOrNew(['member_id' => $memberData['member_id']]);
            $member->fill([
                'member_id' => $memberData['member_id'],
                'sponsor_id' => $memberData['sponsor_id'],
                'sponsor_name' => $memberData['sponsor_name'],
                'member_name' => $memberData['member_name'],
                'mobile_no' => $memberData['mobile_no'],
                'email' => $memberData['email'],
                'password' => $member->password ?: Hash::make('demo12345'),
                'status' => 'active',
            ]);

            $member->save();
            $memberMap[$memberData['member_id']] = $member->fresh();
            if ($member->wasRecentlyCreated) {
                $createdMembers++;
            }
        }

        foreach ($investmentPlan as $investmentData) {
            $investment = Investment::query()->firstOrNew(['investment_id' => $investmentData['investment_id']]);
            $investment->fill([
                'investment_id' => $investmentData['investment_id'],
                'member_id' => $investmentData['member_id'],
                'member_name' => $investmentData['member_name'],
                'amount' => $investmentData['amount'],
                'status' => 'active',
                'closed_at' => null,
            ]);

            if (! $investment->exists) {
                $investment->created_at = $investmentData['created_at'];
                $investment->updated_at = $investmentData['created_at'];
            }

            $investment->save();
            if ($investment->wasRecentlyCreated) {
                $createdInvestments++;
            }
        }

        foreach ($memberMap as $member) {
            $result = $rankService->calculateForMember($member);
            $member->update(['rank_id' => $result['current_rank']?->id]);
        }

        $investmentRecords = Investment::query()->where('investment_id', 'like', 'INV-DEMO-%')->get();

        foreach ($investmentRecords as $investment) {
            $levelCommissionGenerationService->generateForInvestment($investment->fresh(), $businessDate);
        }

        $roiRecords = 0;
        foreach (range(0, 4) as $offset) {
            $referenceDate = $businessDate->copy()->subDays($offset);
            $result = $roiGenerationService->generateForDate($referenceDate);
            $roiRecords += (int) $result['generated'] + (int) $result['expired'];
        }

        $result = [
            'members' => $createdMembers,
            'investments' => $createdInvestments,
            'roi_records' => $roiRecords,
        ];

        return $result;
    }

    private function memberPlan(): array
    {
        return [
            ['member_id' => 'DEMO-ROOT', 'sponsor_id' => 'DEMO-ROOT', 'sponsor_name' => 'Demo Root Sponsor', 'member_name' => 'Demo Root Sponsor', 'mobile_no' => '9100000001', 'email' => 'demo.root@example.test'],
            ['member_id' => 'DEMO-101', 'sponsor_id' => 'DEMO-ROOT', 'sponsor_name' => 'Demo Root Sponsor', 'member_name' => 'Demo Aisha Patel', 'mobile_no' => '9100000101', 'email' => 'demo.aisha@example.test'],
            ['member_id' => 'DEMO-102', 'sponsor_id' => 'DEMO-ROOT', 'sponsor_name' => 'Demo Root Sponsor', 'member_name' => 'Demo Ravi Shah', 'mobile_no' => '9100000102', 'email' => 'demo.ravi@example.test'],
            ['member_id' => 'DEMO-103', 'sponsor_id' => 'DEMO-ROOT', 'sponsor_name' => 'Demo Root Sponsor', 'member_name' => 'Demo Lucy Nguyen', 'mobile_no' => '9100000103', 'email' => 'demo.lucy@example.test'],
            ['member_id' => 'DEMO-104', 'sponsor_id' => 'DEMO-ROOT', 'sponsor_name' => 'Demo Root Sponsor', 'member_name' => 'Demo Daniel Cruz', 'mobile_no' => '9100000104', 'email' => 'demo.daniel@example.test'],
            ['member_id' => 'DEMO-201', 'sponsor_id' => 'DEMO-101', 'sponsor_name' => 'Demo Aisha Patel', 'member_name' => 'Demo Zara Khan', 'mobile_no' => '9100000201', 'email' => 'demo.zara@example.test'],
            ['member_id' => 'DEMO-202', 'sponsor_id' => 'DEMO-101', 'sponsor_name' => 'Demo Aisha Patel', 'member_name' => 'Demo Farhan Ali', 'mobile_no' => '9100000202', 'email' => 'demo.farhan@example.test'],
            ['member_id' => 'DEMO-203', 'sponsor_id' => 'DEMO-102', 'sponsor_name' => 'Demo Ravi Shah', 'member_name' => 'Demo Meera Iyer', 'mobile_no' => '9100000203', 'email' => 'demo.meera@example.test'],
            ['member_id' => 'DEMO-204', 'sponsor_id' => 'DEMO-102', 'sponsor_name' => 'Demo Ravi Shah', 'member_name' => 'Demo Liam Brooks', 'mobile_no' => '9100000204', 'email' => 'demo.liam@example.test'],
            ['member_id' => 'DEMO-205', 'sponsor_id' => 'DEMO-103', 'sponsor_name' => 'Demo Lucy Nguyen', 'member_name' => 'Demo Naina Joshi', 'mobile_no' => '9100000205', 'email' => 'demo.naina@example.test'],
            ['member_id' => 'DEMO-206', 'sponsor_id' => 'DEMO-103', 'sponsor_name' => 'Demo Lucy Nguyen', 'member_name' => 'Demo Omar Hassan', 'mobile_no' => '9100000206', 'email' => 'demo.omar@example.test'],
            ['member_id' => 'DEMO-207', 'sponsor_id' => 'DEMO-104', 'sponsor_name' => 'Demo Daniel Cruz', 'member_name' => 'Demo Sofia Kim', 'mobile_no' => '9100000207', 'email' => 'demo.sofia@example.test'],
            ['member_id' => 'DEMO-301', 'sponsor_id' => 'DEMO-201', 'sponsor_name' => 'Demo Zara Khan', 'member_name' => 'Demo Karan Singh', 'mobile_no' => '9100000301', 'email' => 'demo.karan@example.test'],
            ['member_id' => 'DEMO-302', 'sponsor_id' => 'DEMO-201', 'sponsor_name' => 'Demo Zara Khan', 'member_name' => 'Demo Elise Wong', 'mobile_no' => '9100000302', 'email' => 'demo.elise@example.test'],
            ['member_id' => 'DEMO-303', 'sponsor_id' => 'DEMO-203', 'sponsor_name' => 'Demo Meera Iyer', 'member_name' => 'Demo Theo Martin', 'mobile_no' => '9100000303', 'email' => 'demo.theo@example.test'],
            ['member_id' => 'DEMO-304', 'sponsor_id' => 'DEMO-204', 'sponsor_name' => 'Demo Liam Brooks', 'member_name' => 'Demo Priya Nair', 'mobile_no' => '9100000304', 'email' => 'demo.priya@example.test'],
            ['member_id' => 'DEMO-305', 'sponsor_id' => 'DEMO-205', 'sponsor_name' => 'Demo Naina Joshi', 'member_name' => 'Demo Noah Parker', 'mobile_no' => '9100000305', 'email' => 'demo.noah@example.test'],
            ['member_id' => 'DEMO-306', 'sponsor_id' => 'DEMO-207', 'sponsor_name' => 'Demo Sofia Kim', 'member_name' => 'Demo Emma Lopez', 'mobile_no' => '9100000306', 'email' => 'demo.emma@example.test'],
        ];
    }

    private function investmentPlan(): array
    {
        $baseDate = CarbonImmutable::now(RoiGenerationService::TIMEZONE)->subDays(45)->startOfDay();

        return [
            ['investment_id' => 'INV-DEMO-ROOT', 'member_id' => 'DEMO-ROOT', 'member_name' => 'Demo Root Sponsor', 'amount' => '300000.0000', 'created_at' => $baseDate->subDays(8)->toDateTimeString()],
            ['investment_id' => 'INV-DEMO-101', 'member_id' => 'DEMO-101', 'member_name' => 'Demo Aisha Patel', 'amount' => '180000.0000', 'created_at' => $baseDate->subDays(20)->toDateTimeString()],
            ['investment_id' => 'INV-DEMO-102', 'member_id' => 'DEMO-102', 'member_name' => 'Demo Ravi Shah', 'amount' => '240000.0000', 'created_at' => $baseDate->subDays(16)->toDateTimeString()],
            ['investment_id' => 'INV-DEMO-103', 'member_id' => 'DEMO-103', 'member_name' => 'Demo Lucy Nguyen', 'amount' => '210000.0000', 'created_at' => $baseDate->subDays(14)->toDateTimeString()],
            ['investment_id' => 'INV-DEMO-104', 'member_id' => 'DEMO-104', 'member_name' => 'Demo Daniel Cruz', 'amount' => '220000.0000', 'created_at' => $baseDate->subDays(12)->toDateTimeString()],
            ['investment_id' => 'INV-DEMO-201', 'member_id' => 'DEMO-201', 'member_name' => 'Demo Zara Khan', 'amount' => '90000.0000', 'created_at' => $baseDate->subDays(18)->toDateTimeString()],
            ['investment_id' => 'INV-DEMO-202', 'member_id' => 'DEMO-202', 'member_name' => 'Demo Farhan Ali', 'amount' => '100000.0000', 'created_at' => $baseDate->subDays(15)->toDateTimeString()],
            ['investment_id' => 'INV-DEMO-203', 'member_id' => 'DEMO-203', 'member_name' => 'Demo Meera Iyer', 'amount' => '120000.0000', 'created_at' => $baseDate->subDays(11)->toDateTimeString()],
            ['investment_id' => 'INV-DEMO-204', 'member_id' => 'DEMO-204', 'member_name' => 'Demo Liam Brooks', 'amount' => '85000.0000', 'created_at' => $baseDate->subDays(13)->toDateTimeString()],
            ['investment_id' => 'INV-DEMO-205', 'member_id' => 'DEMO-205', 'member_name' => 'Demo Naina Joshi', 'amount' => '90000.0000', 'created_at' => $baseDate->subDays(10)->toDateTimeString()],
            ['investment_id' => 'INV-DEMO-206', 'member_id' => 'DEMO-206', 'member_name' => 'Demo Omar Hassan', 'amount' => '80000.0000', 'created_at' => $baseDate->subDays(9)->toDateTimeString()],
            ['investment_id' => 'INV-DEMO-207', 'member_id' => 'DEMO-207', 'member_name' => 'Demo Sofia Kim', 'amount' => '95000.0000', 'created_at' => $baseDate->subDays(7)->toDateTimeString()],
            ['investment_id' => 'INV-DEMO-301', 'member_id' => 'DEMO-301', 'member_name' => 'Demo Karan Singh', 'amount' => '45000.0000', 'created_at' => $baseDate->subDays(6)->toDateTimeString()],
            ['investment_id' => 'INV-DEMO-302', 'member_id' => 'DEMO-302', 'member_name' => 'Demo Elise Wong', 'amount' => '50000.0000', 'created_at' => $baseDate->subDays(5)->toDateTimeString()],
            ['investment_id' => 'INV-DEMO-303', 'member_id' => 'DEMO-303', 'member_name' => 'Demo Theo Martin', 'amount' => '55000.0000', 'created_at' => $baseDate->subDays(4)->toDateTimeString()],
            ['investment_id' => 'INV-DEMO-304', 'member_id' => 'DEMO-304', 'member_name' => 'Demo Priya Nair', 'amount' => '60000.0000', 'created_at' => $baseDate->subDays(3)->toDateTimeString()],
            ['investment_id' => 'INV-DEMO-305', 'member_id' => 'DEMO-305', 'member_name' => 'Demo Noah Parker', 'amount' => '42000.0000', 'created_at' => $baseDate->subDays(2)->toDateTimeString()],
            ['investment_id' => 'INV-DEMO-306', 'member_id' => 'DEMO-306', 'member_name' => 'Demo Emma Lopez', 'amount' => '47000.0000', 'created_at' => $baseDate->subDays(1)->toDateTimeString()],
        ];
    }
}
