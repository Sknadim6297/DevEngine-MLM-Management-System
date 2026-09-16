<?php

namespace Tests\Feature;

use App\Models\LevelCommission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
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
            ->assertSee('value="1"', false)
            ->assertSeeText('Level 4')
            ->assertSee('value="0.3"', false)
            ->assertSeeText('Level 20')
            ->assertSee('value="0.25"', false)
            ->assertSeeText('Level 32')
            ->assertSee('value="0.2"', false)
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

        $this->assertSame(32, substr_count($html, 'Are you sure to update this?'));
    }

    public function test_statement_uses_updated_database_percentage(): void
    {
        $this->actingAs(User::factory()->create());

        LevelCommission::where('level', 1)->update(['percentage' => '2.7500']);

        $this->get(route('admin.level-commission.statement'))
            ->assertOk()
            ->assertSee('value="2.75"', false)
            ->assertDontSee('value="1"', false);
    }

    public function test_inactive_level_is_not_displayed(): void
    {
        $this->actingAs(User::factory()->create());

        LevelCommission::where('level', 32)->update(['is_active' => false]);

        $this->get(route('admin.level-commission.statement'))
            ->assertOk()
            ->assertDontSeeText('Level 32');
    }

    public function test_duplicate_levels_are_rejected_by_the_database(): void
    {
        $this->expectException(QueryException::class);

        LevelCommission::create([
            'level' => 1,
            'percentage' => '1.0000',
            'is_active' => true,
        ]);
    }

    public function test_admin_percentage_update_is_reflected_in_statement(): void
    {
        $this->actingAs(User::factory()->create());
        $levelOne = LevelCommission::where('level', 1)->firstOrFail();

        $this->post(route('admin.level-commission.statement.update', $levelOne), [
            'percentage' => '2.7500',
        ])->assertRedirect(route('admin.level-commission.statement'));

        $this->assertSame('2.7500', LevelCommission::findOrFail($levelOne->id)->percentage);
        $this->get(route('admin.level-commission.statement'))
            ->assertOk()
            ->assertSee('value="2.75"', false);
    }

    public function test_invalid_percentage_is_rejected_without_updating_configuration(): void
    {
        $this->actingAs(User::factory()->create());
        $levelOne = LevelCommission::where('level', 1)->firstOrFail();

        $this->post(route('admin.level-commission.statement.update', $levelOne), [
            'percentage' => '100.0001',
        ])->assertSessionHasErrors('percentage');

        $this->assertSame('1.0000', LevelCommission::findOrFail($levelOne->id)->percentage);
    }
}
