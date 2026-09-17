<?php

namespace Database\Seeders;

use App\Models\Investment;
use App\Models\Member;
use App\Services\LevelCommissionGenerationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RoiDevelopmentSeeder extends Seeder
{
    public function run(LevelCommissionGenerationService $levelCommissionGenerationService): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('RoiDevelopmentSeeder can only run in local or testing environments.');
        }

        $members = [];
        $previousMember = null;
        $memberNames = array_map(
            static fn (string $letter): string => 'Roi Test Member ' . $letter,
            range('A', 'Z')
        );
        $memberNames[] = 'Roi Test Member AA';
        $memberNames[] = 'Roi Test Member AB';
        $memberNames[] = 'Roi Test Member AC';
        $memberNames[] = 'Roi Test Member AD';
        $memberNames[] = 'Roi Test Member AE';
        $memberNames[] = 'Roi Test Member AF';

        DB::transaction(function () use (&$members, &$previousMember, $memberNames) {
            foreach (range(1, 32) as $level) {
                $email = sprintf('roi-dev-level-%02d@example.test', $level);
                $member = Member::where('email', $email)->first();

                if (! $member) {
                    $member = new Member([
                        'member_id' => $this->generateMemberId(),
                        'email' => $email,
                    ]);
                }

                $member->fill([
                    'sponsor_id' => $previousMember?->member_id ?? 'ST666666',
                    'sponsor_name' => $previousMember?->member_name ?? 'Admin',
                    'member_name' => $memberNames[$level - 1],
                    'mobile_no' => (string) (9100000000 + $level),
                    'pan_card_no' => sprintf('DEVROI%03dA', $level),
                    'status' => 'inactive',
                ]);
                $member->save();

                $members[$level] = $member->fresh();
                $previousMember = $members[$level];
            }
        });

        foreach ($members as $level => $member) {
            DB::transaction(function () use ($member, $level, $levelCommissionGenerationService) {
                $investment = Investment::where('member_id', $member->member_id)->first();

                if (! $investment) {
                    $investment = Investment::create([
                        'investment_id' => $this->generateInvestmentId(),
                        'member_id' => $member->member_id,
                        'member_name' => $member->member_name,
                        'amount' => '100.0000',
                        'status' => 'active',
                    ]);

                    $investmentDate = CarbonImmutable::now('Asia/Kolkata')
                        ->subDays(2)
                        ->setTimezone('UTC');
                    $investment->forceFill([
                        'created_at' => $investmentDate,
                        'updated_at' => $investmentDate,
                    ])->save();
                } else {
                    $investment->update([
                        'member_name' => $member->member_name,
                        'amount' => '100.0000',
                        'status' => 'active',
                        'closed_at' => null,
                    ]);
                }

                $member->update(['status' => 'active']);
                $levelCommissionGenerationService->generateForInvestment($investment->fresh());
            });

            $this->command?->info(sprintf(
                'Level %02d: %s -> %s -> %s',
                $level,
                $member->member_id,
                $member->sponsor_id,
                $member->member_name
            ));
        }

        $this->command?->info('Created or verified 32 ROI development members and investments.');
    }

    protected function generateMemberId(): string
    {
        for ($counter = 100000; $counter <= 999999; $counter++) {
            $candidate = 'ST' . $counter;

            if (! Member::where('member_id', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new RuntimeException('No available Member ID found.');
    }

    protected function generateInvestmentId(): string
    {
        do {
            $investmentId = 'INV' . random_int(100000, 999999);
        } while (Investment::where('investment_id', $investmentId)->exists());

        return $investmentId;
    }
}
