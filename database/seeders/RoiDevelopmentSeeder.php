<?php

namespace Database\Seeders;

use App\Models\Investment;
use App\Models\Member;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use RuntimeException;

class RoiDevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('RoiDevelopmentSeeder can only run in local or testing environments.');
        }

        $members = [
            ['member_id' => 'DEVROI001', 'sponsor_id' => 'ST666666', 'sponsor_name' => 'Admin', 'member_name' => 'Rahul', 'mobile_no' => '9100000001', 'pan_card_no' => 'DEVROI001A', 'email' => 'dev-roi-rahul@example.test'],
            ['member_id' => 'DEVROI002', 'sponsor_id' => 'DEVROI001', 'sponsor_name' => 'Rahul', 'member_name' => 'Suman', 'mobile_no' => '9100000002', 'pan_card_no' => 'DEVROI002A', 'email' => 'dev-roi-suman@example.test'],
            ['member_id' => 'DEVROI003', 'sponsor_id' => 'DEVROI002', 'sponsor_name' => 'Suman', 'member_name' => 'Arif', 'mobile_no' => '9100000003', 'pan_card_no' => 'DEVROI003A', 'email' => 'dev-roi-arif@example.test'],
        ];

        foreach ($members as $attributes) {
            Member::firstOrCreate(
                ['member_id' => $attributes['member_id']],
                $attributes + ['status' => 'active']
            );
        }

        $investments = [
            ['investment_id' => 'DEVINV001', 'member_id' => 'DEVROI001', 'amount' => '100.0000'],
            ['investment_id' => 'DEVINV002', 'member_id' => 'DEVROI002', 'amount' => '2405.0000'],
            ['investment_id' => 'DEVINV003', 'member_id' => 'DEVROI003', 'amount' => '500.0000'],
        ];

        foreach ($investments as $attributes) {
            $member = Member::where('member_id', $attributes['member_id'])->firstOrFail();
            $investment = Investment::firstOrCreate(
                ['investment_id' => $attributes['investment_id']],
                $attributes + [
                    'member_name' => $member->member_name,
                    'status' => 'active',
                ]
            );

            if ($investment->wasRecentlyCreated) {
                $investment->forceFill([
                    'created_at' => CarbonImmutable::now('Asia/Kolkata')->subDays(2)->setTimezone('UTC'),
                    'updated_at' => CarbonImmutable::now('Asia/Kolkata')->subDays(2)->setTimezone('UTC'),
                ])->save();
            }
        }
    }
}
