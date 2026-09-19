<?php

namespace Database\Seeders;

use App\Models\Member;
use App\Models\Investment;
use Illuminate\Database\Seeder;

class MemberSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $members = [
            [
                'member_id' => 'ST900001',
                'sponsor_id' => 'ST666666',
                'sponsor_name' => 'Admin',
                'member_name' => 'Test Direct Active',
                'mobile_no' => '9000000001',
                'email' => 'test.direct.active@example.com',
                'wallet_address' => 'TEST-WALLET-900001',
                'status' => 'inactive',
            ],
            [
                'member_id' => 'ST900002',
                'sponsor_id' => 'ST666666',
                'sponsor_name' => 'Admin',
                'member_name' => 'Test Direct Inactive',
                'mobile_no' => '9000000002',
                'email' => 'test.direct.inactive@example.com',
                'wallet_address' => null,
                'status' => 'inactive',
            ],
            [
                'member_id' => 'ST900003',
                'sponsor_id' => 'ST900001',
                'sponsor_name' => 'Test Direct Active',
                'member_name' => 'Test Nested Active',
                'mobile_no' => '9000000003',
                'email' => 'test.nested.active@example.com',
                'wallet_address' => 'TEST-WALLET-900003',
                'status' => 'inactive',
            ],
            [
                'member_id' => 'ST900004',
                'sponsor_id' => 'ST900001',
                'sponsor_name' => 'Test Direct Active',
                'member_name' => 'Test Nested Inactive',
                'mobile_no' => '9000000004',
                'email' => 'test.nested.inactive@example.com',
                'wallet_address' => null,
                'status' => 'inactive',
            ],
        ];

        foreach ($members as $memberData) {
            Member::updateOrCreate(
                ['member_id' => $memberData['member_id']],
                array_merge($memberData, ['password' => bcrypt('test12345')])
            );
        }

        foreach (['ST900001', 'ST900003'] as $memberId) {
            $member = Member::where('member_id', $memberId)->firstOrFail();

            Investment::updateOrCreate(
                ['investment_id' => 'SEED-' . $memberId],
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
}
