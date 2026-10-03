<?php

namespace Tests\Feature;

use App\Models\Investment;
use App\Models\Member;
use App\Models\RoiTransaction;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_database_seeder_preserves_existing_financial_history(): void
    {
        $member = Member::query()->create([
            'member_id' => 'SAFE-SEED-MEMBER',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Existing Financial Member',
            'mobile_no' => '9876500001',
            'pan_card_no' => 'SAFE00001',
            'email' => 'safe-seed@example.test',
            'status' => 'active',
        ]);
        $investment = Investment::query()->create([
            'investment_id' => 'SAFE-SEED-INVESTMENT',
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'amount' => '100.0000',
            'status' => 'active',
        ]);
        RoiTransaction::query()->create([
            'reference' => 'SAFE-SEED-ROI',
            'investment_id' => $investment->investment_id,
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'on_amount' => '100.0000',
            'rate_percentage' => '5.000',
            'income_amount' => '0.1666',
            'roi_date' => '2026-01-02',
            'status' => 'generated',
            'withdrawable_on' => '2026-02-01',
        ]);

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('roi_transactions', ['reference' => 'SAFE-SEED-ROI']);
        $this->assertDatabaseHas('investments', ['investment_id' => 'SAFE-SEED-INVESTMENT']);
        $this->assertDatabaseHas('members', ['member_id' => 'SAFE-SEED-MEMBER']);
    }

    public function test_default_seeder_does_not_create_a_hard_coded_administrator(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('ranks', 8);
    }
}
