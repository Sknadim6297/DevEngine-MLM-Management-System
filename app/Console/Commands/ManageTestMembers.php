<?php

namespace App\Console\Commands;

use App\Models\Member;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ManageTestMembers extends Command
{
    protected $signature = 'members:test-data {--remove : Remove only the reserved test members}';

    protected $description = 'Create or remove the Member List test data under ST100045.';

    private const SPONSOR_ID = 'ST100045';

    public function handle(): int
    {
        if ($this->option('remove')) {
            return $this->removeMembers();
        }

        return $this->createMembers();
    }

    private function createMembers(): int
    {
        $sponsor = Member::where('member_id', self::SPONSOR_ID)->first();

        if (! $sponsor) {
            $this->error('Sponsor ST100045 was not found. No test members were created.');

            return self::FAILURE;
        }

        $members = $this->members();
        $memberIds = array_column($members, 'member_id');
        $existingIds = Member::whereIn('member_id', $memberIds)
            ->pluck('member_id')
            ->all();

        if ($existingIds !== []) {
            $this->error('Reserved test member IDs already exist: ' . implode(', ', $existingIds));
            $this->line('No existing records were changed.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($members, $sponsor): void {
            $sponsorNames = [self::SPONSOR_ID => $sponsor->member_name];

            foreach ($members as $member) {
                $testMember = Member::create([
                    'member_id' => $member['member_id'],
                    'member_name' => $member['member_name'],
                    'email' => $member['email'],
                    'mobile_no' => $member['mobile_no'],
                    'status' => $member['status'],
                    'sponsor_id' => $member['sponsor_id'],
                    'sponsor_name' => $sponsorNames[$member['sponsor_id']],
                    'password' => bcrypt('test12345'),
                ]);

                $testMember->created_at = $member['created_at'];
                $testMember->updated_at = $member['created_at'];
                $testMember->save();
                $sponsorNames[$member['member_id']] = $member['member_name'];
            }
        });

        $this->info('Created 30 test members under ST100045 (10 direct and 20 second-level).');

        return self::SUCCESS;
    }

    private function removeMembers(): int
    {
        $removed = Member::whereIn('member_id', $this->memberIds())->delete();

        $this->info("Removed {$removed} reserved test member record(s).");

        return self::SUCCESS;
    }

    private function memberIds(): array
    {
        return array_map(
            fn (int $number): string => 'TEST' . (100000 + $number),
            range(1, 30)
        );
    }

    private function members(): array
    {
        $names = [
            'Alice Morgan', 'Daniel Brooks', 'Priya Shah', 'Ethan Carter', 'Sofia Bennett',
            'Lucas Reed', 'Maya Collins', 'Noah Mitchell', 'Ava Turner', 'Leo Parker',
            'Grace Wilson', 'Oliver Hayes', 'Nisha Kapoor', 'Henry Foster', 'Chloe Evans',
            'Arjun Mehta', 'Emma Sullivan', 'Liam Cooper', 'Zara Khan', 'James Murphy',
            'Mia Richardson', 'Owen Bailey', 'Anika Patel', 'William Ross', 'Ella Hughes',
            'Rohan Desai', 'Isla Morris', 'Benjamin Ward', 'Neha Malhotra', 'Jack Taylor',
        ];

        return array_map(function (string $name, int $index): array {
            $number = $index + 1;
            $memberId = 'TEST' . (100000 + $number);
            $sponsorNumber = $number <= 10
                ? null
                : intdiv($number - 11, 2) + 1;

            return [
                'member_id' => $memberId,
                'member_name' => 'Test Member ' . $name,
                'email' => 'test.member.' . strtolower(str_replace(' ', '.', $name)) . '@example.com',
                'mobile_no' => '91987654' . str_pad((string) $number, 4, '0', STR_PAD_LEFT),
                'status' => $number % 4 === 0 ? 'inactive' : 'active',
                'sponsor_id' => $sponsorNumber === null
                    ? self::SPONSOR_ID
                    : 'TEST' . (100000 + $sponsorNumber),
                'created_at' => CarbonImmutable::parse('2026-08-23 09:00:00')->addDays($index),
            ];
        }, $names, array_keys($names));
    }
}