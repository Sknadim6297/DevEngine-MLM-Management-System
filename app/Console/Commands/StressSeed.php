<?php

namespace App\Console\Commands;

use App\Models\Investment;
use App\Models\Member;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StressSeed extends Command
{
    protected $signature = 'stress:seed
        {--date= : Business date (YYYY-MM-DD)}
        {--additional= : Add this many members to the existing stress dataset}
        {--run-real-flow : Run the existing scoped ROI, commission, and rank commands after seeding}';

    protected $description = 'Create the controlled 10,000-member Aisha downline stress dataset.';

    public const DATASET_ID = 'devengine-aisha-10000-v1';
    public const ROOT_MEMBER_ID = 'ST123457';
    public const MEMBER_PREFIX = 'STRESS-';
    public const INVESTMENT_PREFIX = 'INV-STRESS-';
    public const MEMBER_COUNT = 10000;

    private const LEVEL_COUNTS = [50, 200, 600, 1200, 1800, 1800, 1500, 1200, 1000, 650];
    private const AMOUNTS = [100, 200, 300, 400, 500, 750, 1000, 1250, 1500, 2000, 2500, 3000, 3500, 4000, 4500, 5000];

    public function handle(): int
    {
        $root = Member::query()->where('member_id', self::ROOT_MEMBER_ID)->first();
        if (! $root) {
            $this->error('Root member ST123457 (Aisha Sharma) was not found.');
            return self::FAILURE;
        }

        $registry = DB::table('stress_test_datasets')->where('dataset_id', self::DATASET_ID)->first();
        $existingMembers = Member::query()->whereBetween('member_id', ['STRESS-000001', 'STRESS-010000'])->count();
        $existingInvestments = Investment::query()->whereBetween('investment_id', [self::INVESTMENT_PREFIX . '20260925-00001', self::INVESTMENT_PREFIX . '20260925-10000'])->count();

        if ($this->option('additional') !== null) {
            return $this->addMembers((int) $this->option('additional'), $registry, $existingMembers, $existingInvestments);
        }

        if ($registry || $existingMembers > 0 || $existingInvestments > 0) {
            $this->warn(sprintf(
                'Stress dataset already exists or is partially present: registry=%s, members=%d, investments=%d. Skipping creation.',
                $registry ? 'yes' : 'no', $existingMembers, $existingInvestments
            ));
            $this->printSummary();
            return ($existingMembers === self::MEMBER_COUNT && $existingInvestments === self::MEMBER_COUNT) ? self::SUCCESS : self::FAILURE;
        }

        $startedAt = microtime(true);
        $now = CarbonImmutable::now('Asia/Kolkata')->subDays(30)->startOfDay();
        $password = Hash::make('StressMember!2026');
        $levels = $this->buildMembers($root, $now, $password);
        $members = array_merge(...array_values($levels));
        $investments = $this->buildInvestments($members, $now);

        DB::transaction(function () use ($members, $investments): void {
            foreach (array_chunk($members, 500) as $chunk) {
                DB::table('members')->insert($chunk);
            }
            foreach (array_chunk($investments, 500) as $chunk) {
                DB::table('investments')->insert($chunk);
            }
            DB::table('stress_test_datasets')->insert([
                'dataset_id' => self::DATASET_ID,
                'root_member_id' => self::ROOT_MEMBER_ID,
                'member_count' => count($members),
                'investment_count' => count($investments),
                'status' => 'seeded',
                'metadata' => json_encode(['levels' => self::LEVEL_COUNTS, 'member_prefix' => self::MEMBER_PREFIX, 'investment_prefix' => self::INVESTMENT_PREFIX], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->info(sprintf('Seeded %d members and %d investments in %.3f seconds.', count($members), count($investments), microtime(true) - $startedAt));
        $this->printSummary();

        if ($this->option('run-real-flow')) {
            $date = $this->option('date') ?: CarbonImmutable::now('Asia/Kolkata')->toDateString();
            $this->runRealFlow($date);
        }

        return self::SUCCESS;
    }

    private function addMembers(int $additional, ?object $registry, int $existingMembers, int $existingInvestments): int
    {
        if ($additional !== 10000) {
            $this->error('Only --additional=10000 is supported for the second controlled cohort.');
            return self::FAILURE;
        }

        if (! $registry || $existingMembers !== self::MEMBER_COUNT || $existingInvestments !== self::MEMBER_COUNT) {
            $this->error(sprintf(
                'The original cohort must be complete before adding cohort two. registry=%s, members=%d, investments=%d.',
                $registry ? 'yes' : 'no', $existingMembers, $existingInvestments
            ));
            return self::FAILURE;
        }

        $secondCohortMembers = Member::query()
            ->whereBetween('member_id', ['STRESS-009351', 'STRESS-010000'])
            ->orderBy('member_id')
            ->limit(50)
            ->get(['member_id', 'member_name'])
            ->mapWithKeys(fn (Member $member): array => [$member->member_id => $member->member_name])
            ->all();

        if (count($secondCohortMembers) !== 50) {
            $this->error('Could not find the 50 existing sponsors required for cohort two.');
            return self::FAILURE;
        }

        $newMemberCount = Member::query()->whereBetween('member_id', ['STRESS-010001', 'STRESS-020000'])->count();
        $newInvestmentCount = Investment::query()->whereBetween('investment_id', ['INV-STRESS-20260925-10001', 'INV-STRESS-20260925-20000'])->count();
        if ($newMemberCount > 0 || $newInvestmentCount > 0) {
            $this->warn(sprintf(
                'Cohort two already exists or is partial: members=%d, investments=%d. No records were changed.',
                $newMemberCount, $newInvestmentCount
            ));
            $this->printSummary();
            return ($newMemberCount === 10000 && $newInvestmentCount === 10000) ? self::SUCCESS : self::FAILURE;
        }

        $startedAt = microtime(true);
        $createdAt = CarbonImmutable::now('Asia/Kolkata')->subDays(30)->startOfDay();
        $password = Hash::make('StressMember!2026');
        $levels = $this->buildAdditionalMembers($secondCohortMembers, $createdAt, $password);
        $members = array_merge(...array_values($levels));
        $investments = $this->buildInvestments($members, $createdAt, self::MEMBER_COUNT + 1);

        if (count($members) !== 10000 || count($investments) !== 10000) {
            $this->error('Generated cohort size was not exactly 10,000 members and 10,000 investments.');
            return self::FAILURE;
        }

        $this->assertAdditionalUniqueness($members, $investments);

        DB::transaction(function () use ($members, $investments, $registry): void {
            foreach (array_chunk($members, 500) as $chunk) {
                DB::table('members')->insert($chunk);
            }
            foreach (array_chunk($investments, 500) as $chunk) {
                DB::table('investments')->insert($chunk);
            }

            DB::table('stress_test_datasets')
                ->where('dataset_id', self::DATASET_ID)
                ->update([
                    'member_count' => 20000,
                    'investment_count' => 20000,
                    'status' => 'seeded-expanded',
                    'metadata' => json_encode([
                        'levels' => self::LEVEL_COUNTS,
                        'cohorts' => [10000, 10000],
                        'member_prefix' => self::MEMBER_PREFIX,
                        'investment_prefix' => self::INVESTMENT_PREFIX,
                    ], JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);
        });

        $this->info(sprintf('Added %d members and %d investments in %.3f seconds.', count($members), count($investments), microtime(true) - $startedAt));
        $this->line('Cohort two member range: STRESS-010001 through STRESS-020000');
        $this->line('Cohort two investment range: INV-STRESS-20260925-10001 through INV-STRESS-20260925-20000');
        $this->printSummary();

        return self::SUCCESS;
    }

    private function buildAdditionalMembers(array $sponsors, CarbonImmutable $createdAt, string $password): array
    {
        $firstNames = ['Ashok', 'Lata', 'Nitin', 'Rekha', 'Sanjay', 'Kiran', 'Mohan', 'Sunita', 'Ravi', 'Geeta', 'Prakash', 'Leela', 'Anil', 'Usha', 'Vivek', 'Ritu', 'Harish', 'Shalini', 'Dinesh', 'Komal', 'Ramesh', 'Seema', 'Naveen', 'Pallavi', 'Gopal', 'Jyoti', 'Mahesh', 'Indira', 'Sahil', 'Madhuri', 'Naresh', 'Kusum', 'Tarun', 'Preeti', 'Omkar', 'Rachna', 'Bharat', 'Suman', 'Vikas', 'Alka'];
        $middleNames = ['Kumar', 'Devi', 'Prasad', 'Lal', 'Nath', 'Rani', 'Babu', 'Chandra', 'Mohan', 'Jyoti'];
        $lastNames = ['Sharma', 'Khan', 'Das', 'Singh', 'Sheikh', 'Patel', 'Iyer', 'Nair', 'Joshi', 'Kapoor', 'Malhotra', 'Mehta', 'Verma', 'Gupta', 'Reddy', 'Jain', 'Mishra', 'Chopra', 'Bose', 'Saxena', 'Qureshi', 'Bansal', 'Yadav', 'Sethi', 'Ansari'];
        $levels = [];
        $previous = $sponsors;
        $number = self::MEMBER_COUNT;

        foreach (self::LEVEL_COUNTS as $level => $count) {
            $rows = [];
            $previousIds = array_keys($previous);
            for ($index = 0; $index < $count; $index++) {
                $number++;
                $memberId = self::MEMBER_PREFIX . str_pad((string) $number, 6, '0', STR_PAD_LEFT);
                $first = $firstNames[($number - self::MEMBER_COUNT - 1) % count($firstNames)];
                $middle = $middleNames[intdiv($number - self::MEMBER_COUNT - 1, count($firstNames)) % count($middleNames)];
                $last = $lastNames[intdiv($number - self::MEMBER_COUNT - 1, count($firstNames) * count($middleNames)) % count($lastNames)];
                $sponsorId = $previousIds[$index % count($previousIds)];
                $created = $createdAt->addMinutes($number);
                $rows[] = [
                    'member_id' => $memberId,
                    'sponsor_id' => $sponsorId,
                    'sponsor_name' => $previous[$sponsorId],
                    'member_name' => "$first $middle $last",
                    'wallet_address' => '0x' . str_pad(dechex(1000000000 + $number), 40, '0', STR_PAD_LEFT),
                    'activation_wallet_amount' => '0.0000',
                    'working_wallet_amount' => '0.0000',
                    'roi_wallet_amount' => '0.0000',
                    'mobile_no' => (string) (9000000000 + $number),
                    'pan_card_no' => 'STP' . str_pad((string) $number, 7, '0', STR_PAD_LEFT),
                    'email' => strtolower("$first.$middle.$last.$number@load.devengine.example"),
                    'password' => $password,
                    'status' => 'active',
                    'created_at' => $created,
                    'updated_at' => $created,
                ];
            }
            $levels[$level + 1] = $rows;
            $previous = array_column($rows, 'member_name', 'member_id');
        }

        return $levels;
    }

    private function assertAdditionalUniqueness(array $members, array $investments): void
    {
        $memberIds = array_column($members, 'member_id');
        $emails = array_column($members, 'email');
        $mobiles = array_column($members, 'mobile_no');
        $pans = array_column($members, 'pan_card_no');
        $wallets = array_column($members, 'wallet_address');
        $investmentIds = array_column($investments, 'investment_id');
        if (count(array_unique($memberIds)) !== 10000 || count(array_unique($emails)) !== 10000 || count(array_unique($mobiles)) !== 10000 || count(array_unique($pans)) !== 10000 || count(array_unique($wallets)) !== 10000 || count(array_unique($investmentIds)) !== 10000) {
            throw new \RuntimeException('The additional cohort contains duplicate identifiers.');
        }

        if (Member::query()->whereIn('member_id', $memberIds)->exists() || Investment::query()->whereIn('investment_id', $investmentIds)->exists()) {
            throw new \RuntimeException('The additional cohort collides with existing database identifiers.');
        }
    }

    private function buildMembers(Member $root, CarbonImmutable $createdAt, string $password): array
    {
        $firstNames = ['Ram', 'Sita', 'Nadeem', 'Rahul', 'Priya', 'Arif', 'Neha', 'Amit', 'Sana', 'Imran', 'Kavya', 'Vikram', 'Anjali', 'Rohan', 'Meera', 'Farhan', 'Pooja', 'Karan', 'Ayesha', 'Manish', 'Divya', 'Aditya', 'Nisha', 'Sameer', 'Ishita', 'Mohit', 'Zoya', 'Rajesh', 'Shreya', 'Varun', 'Fatima', 'Ankit', 'Tanya', 'Yusuf', 'Simran', 'Deepak', 'Hina', 'Suresh', 'Maya', 'Irfan'];
        $middleNames = ['Kumar', 'Devi', 'Prasad', 'Lal', 'Nath', 'Rani', 'Babu', 'Chandra', 'Mohan', 'Jyoti'];
        $lastNames = ['Sharma', 'Khan', 'Das', 'Singh', 'Sheikh', 'Patel', 'Iyer', 'Nair', 'Joshi', 'Kapoor', 'Malhotra', 'Mehta', 'Verma', 'Gupta', 'Reddy', 'Jain', 'Mishra', 'Chopra', 'Bose', 'Saxena', 'Qureshi', 'Bansal', 'Yadav', 'Sethi', 'Ansari'];
        $levels = [];
        $previous = [self::ROOT_MEMBER_ID => $root->member_name];
        $number = 0;

        foreach (self::LEVEL_COUNTS as $level => $count) {
            $levelNumber = $level + 1;
            $rows = [];
            $previousIds = array_keys($previous);
            for ($index = 0; $index < $count; $index++) {
                $number++;
                $memberId = self::MEMBER_PREFIX . str_pad((string) $number, 6, '0', STR_PAD_LEFT);
                $first = $firstNames[($number - 1) % count($firstNames)];
                $middle = $middleNames[intdiv($number - 1, count($firstNames)) % count($middleNames)];
                $last = $lastNames[intdiv($number - 1, count($firstNames) * count($middleNames)) % count($lastNames)];
                $sponsorId = $previousIds[$index % count($previousIds)];
                $rows[] = [
                    'member_id' => $memberId,
                    'sponsor_id' => $sponsorId,
                    'sponsor_name' => $previous[$sponsorId],
                    'member_name' => "$first $middle $last",
                    'activation_wallet_amount' => '0.0000',
                    'working_wallet_amount' => '0.0000',
                    'roi_wallet_amount' => '0.0000',
                    'mobile_no' => (string) (9000000000 + $number),
                    'email' => strtolower("$first.$middle.$last.$number@load.devengine.example"),
                    'password' => $password,
                    'status' => 'active',
                    'created_at' => $createdAt->addMinutes($number),
                    'updated_at' => $createdAt->addMinutes($number),
                ];
            }
            $levels[$levelNumber] = $rows;
            $previous = array_column($rows, 'member_name', 'member_id');
        }

        return $levels;
    }

    private function buildInvestments(array $members, CarbonImmutable $createdAt, int $startNumber = 1): array
    {
        return array_map(function (array $member, int $index) use ($createdAt, $startNumber): array {
            $number = $startNumber + $index;
            $amount = self::AMOUNTS[$index % count(self::AMOUNTS)];
            $date = $createdAt->addMinutes($number);
            return [
                'investment_id' => self::INVESTMENT_PREFIX . '20260925-' . str_pad((string) $number, 5, '0', STR_PAD_LEFT),
                'member_id' => $member['member_id'],
                'member_name' => $member['member_name'],
                'amount' => number_format($amount, 4, '.', ''),
                'status' => 'active',
                'created_at' => $date,
                'updated_at' => $date,
            ];
        }, $members, array_keys($members));
    }

    private function printSummary(): void
    {
        $query = Investment::query()->where('investment_id', 'like', self::INVESTMENT_PREFIX . '%');
        $this->line('Total members: ' . Member::query()->where('member_id', 'like', self::MEMBER_PREFIX . '%')->count());
        $this->line('Total investments: ' . $query->count());
        $this->line('Minimum investment: ' . ($query->min('amount') ?? '0.0000'));
        $this->line('Maximum investment: ' . ($query->max('amount') ?? '0.0000'));
        $this->line('Total investment business: ' . ($query->sum('amount') ?: '0.0000'));
        $this->line('Investment count by amount: ' . json_encode($query->select('amount')->selectRaw('COUNT(*) as count')->groupBy('amount')->orderBy('amount')->get()->mapWithKeys(fn ($row) => [$row->amount => $row->count])->all()));
        foreach (self::LEVEL_COUNTS as $level => $count) {
            $this->line('Level ' . ($level + 1) . ': ' . $count);
        }
    }

    private function runRealFlow(string $date): void
    {
        foreach ([
            ['roi:generate', ['--date' => $date, '--investment-prefix' => self::INVESTMENT_PREFIX]],
            ['commission:generate-level', ['--date' => $date, '--investment-prefix' => self::INVESTMENT_PREFIX]],
            ['rank:advance', ['--member-prefix' => self::MEMBER_PREFIX]],
        ] as [$command, $arguments]) {
            $started = microtime(true);
            $exitCode = $this->call($command, $arguments);
            $this->line(sprintf('PERFORMANCE %s | started=%s | duration=%.3fs | exit=%d', $command, now()->toIso8601String(), microtime(true) - $started, $exitCode));
        }
    }
}