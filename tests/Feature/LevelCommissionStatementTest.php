<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LevelCommissionStatementTest extends TestCase
{
    use RefreshDatabase;

    public function test_level_commission_statement_displays_only_the_configured_rate_chart(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get(route('admin.level-commission.statement'));

        $response->assertOk()
            ->assertSeeText('Level 1')
            ->assertSeeText('1%')
            ->assertSeeText('Level 4')
            ->assertSeeText('0.3%')
            ->assertSeeText('Level 20')
            ->assertSeeText('0.25%')
            ->assertSeeText('Level 32')
            ->assertSeeText('0.20%')
            ->assertDontSeeText('Member ID')
            ->assertDontSeeText('Commission Amount')
            ->assertDontSeeText('Source Member');
    }

    public function test_all_thirty_two_levels_are_rendered(): void
    {
        $this->actingAs(User::factory()->create());

        $html = $this->get(route('admin.level-commission.statement'))->getContent();

        for ($level = 1; $level <= 32; $level++) {
            $this->assertStringContainsString('Level ' . $level, $html);
        }
    }
}
