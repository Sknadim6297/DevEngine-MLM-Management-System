<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSidebarTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_renders_all_requested_sidebar_sections(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Transfer to Activation Wallet from Admin List')
            ->assertSeeText('Force Credit to ROI Wallet Entry')
            ->assertSeeText('Leaderwise Working Wallet List')
            ->assertSeeText('Member Salary Wallet to Salary Wallet Transfer List')
            ->assertSeeText('Replied Ticket List')
            ->assertDontSee('admin2/', false);
    }

    public function test_admin_coming_soon_is_admin_only_and_names_feature(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.coming-soon', ['feature' => 'roi-wallet-pending-list']))
            ->assertOk()
            ->assertSeeText('ROI Wallet Pending List')
            ->assertSeeText('Coming Soon');

        auth()->logout();

        $this->get(route('admin.coming-soon', ['feature' => 'roi-wallet-pending-list']))
            ->assertRedirect(route('login'));
    }
}
