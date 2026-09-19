<?php

namespace Database\Seeders;

use App\Models\Investment;
use App\Models\Member;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TestMemberSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $root = Member::query()->orderBy('created_at')->orderBy('id')->first();

            if (! $root) {
                $root = Member::create([
                    'member_id' => 'ST100001',
                    'sponsor_id' => 'ST666666',
                    'sponsor_name' => 'Admin',
                    'member_name' => 'Test Root Member',
                    'email' => 'root@test.com',
                    'mobile_no' => '9000000001',
                    'password' => bcrypt('test12345'),
                    'status' => 'active',
                ]);
            }

            $sponsorMap = $this->sponsorMap($root->member_id);

            foreach ($sponsorMap as $memberId => $sponsorId) {
                $sponsor = Member::where('member_id', $sponsorId)->firstOrFail();
                $isActive = ((int) substr($memberId, -1)) % 3 !== 0;

                Member::firstOrCreate(
                    ['member_id' => $memberId],
                    [
                        'sponsor_id' => $sponsor->member_id,
                        'sponsor_name' => $sponsor->member_name,
                        'member_name' => 'Test Member ' . substr($memberId, -2),
                        'email' => 'testmember' . substr($memberId, -2) . '@example.com',
                        'mobile_no' => '910000' . substr($memberId, -4),
                        'wallet_address' => ((int) substr($memberId, -1)) % 2 === 0
                            ? '0xTEST' . substr($memberId, -6)
                            : null,
                        'password' => bcrypt('test12345'),
                        'status' => 'inactive',
                    ]
                );

                if ($isActive) {
                    $member = Member::where('member_id', $memberId)->firstOrFail();

                    Investment::firstOrCreate(
                        ['investment_id' => 'TEST-' . $memberId],
                        [
                            'member_id' => $member->member_id,
                            'member_name' => $member->member_name,
                            'amount' => 100,
                            'status' => 'active',
                        ]
                    );

                    $member->update(['status' => 'active']);
                }
            }
        });
    }

    private function sponsorMap(string $rootId): array
    {
        $map = [];
        $levelOne = [];
        $levelTwo = [];
        $levelThree = [];
        $levelFour = [];
        $levelFive = [];

        for ($number = 1; $number <= 6; $number++) {
            $memberId = 'ST1000' . str_pad((string) $number, 2, '0', STR_PAD_LEFT);
            $levelOne[] = $memberId;
            $map[$memberId] = $rootId;
        }

        for ($number = 7; $number <= 18; $number++) {
            $memberId = 'ST1000' . str_pad((string) $number, 2, '0', STR_PAD_LEFT);
            $levelTwo[] = $memberId;
            $map[$memberId] = $levelOne[intdiv($number - 7, 2)];
        }

        for ($number = 19; $number <= 30; $number++) {
            $memberId = 'ST1000' . str_pad((string) $number, 2, '0', STR_PAD_LEFT);
            $levelThree[] = $memberId;
            $map[$memberId] = $levelTwo[$number - 19];
        }

        for ($number = 31; $number <= 40; $number++) {
            $memberId = 'ST1000' . str_pad((string) $number, 2, '0', STR_PAD_LEFT);
            $levelFour[] = $memberId;
            $map[$memberId] = $levelThree[$number - 31];
        }

        for ($number = 41; $number <= 49; $number++) {
            $memberId = 'ST1000' . str_pad((string) $number, 2, '0', STR_PAD_LEFT);
            $levelFive[] = $memberId;
            $map[$memberId] = $levelFour[$number - 41];
        }

        for ($number = 50; $number <= 51; $number++) {
            $memberId = 'ST1000' . str_pad((string) $number, 2, '0', STR_PAD_LEFT);
            $map[$memberId] = $levelFive[$number - 50];
        }

        return $map;
    }
}
