<?php

namespace Tests\Feature;

use App\Models\Investment;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDatasetCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seed_command_creates_reserved_dataset_without_touching_existing_data(): void
    {
        Member::create([
            'member_id' => 'REAL-USER',
            'sponsor_id' => 'ADMIN',
            'sponsor_name' => 'Admin',
            'member_name' => 'Real User',
            'mobile_no' => '9999999999',
            'email' => 'real.user@example.test',
            'status' => 'active',
        ]);

        Investment::create([
            'investment_id' => 'INV-REAL-0001',
            'member_id' => 'REAL-USER',
            'member_name' => 'Real User',
            'amount' => '1250.0000',
            'status' => 'active',
        ]);

        $beforeDemoMembers = Member::where('member_id', 'like', 'DEMO-%')->count();

        $this->artisan('demo:seed')->assertExitCode(0);

        $afterDemoMembers = Member::where('member_id', 'like', 'DEMO-%')->count();
        $this->assertGreaterThan($beforeDemoMembers, $afterDemoMembers);
        $this->assertDatabaseHas('members', ['member_id' => 'REAL-USER', 'member_name' => 'Real User']);
        $this->assertDatabaseHas('members', ['member_id' => 'DEMO-ROOT']);
        $this->assertGreaterThan(0, Member::where('member_id', 'like', 'DEMO-%')->whereNotNull('rank_id')->count());
        $this->assertGreaterThan(0, \DB::table('roi_transactions')->count());
        $this->assertGreaterThan(0, \DB::table('level_commission_transactions')->count());

        $this->artisan('demo:seed')->assertExitCode(0);

        $this->assertSame($afterDemoMembers, Member::where('member_id', 'like', 'DEMO-%')->count());
    }
}
