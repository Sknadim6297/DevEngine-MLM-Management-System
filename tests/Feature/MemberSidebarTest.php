<?php

namespace Tests\Feature;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberSidebarTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_dashboard_renders_all_sidebar_links_without_route_errors(): void
    {
        $member = Member::create([
            'member_id' => 'STSIDEBAR1',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Sidebar Member',
            'mobile_no' => '9000000000',
            'pan_card_no' => 'SIDEBAR0001',
            'email' => 'sidebar@example.test',
            'status' => 'active',
        ]);

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertSee('Entry to Activation Wallet by Gateway')
            ->assertSee('Transfer to Other Member ROI Wallet List')
            ->assertSee('Transfer to My Salary Wallet from Other Member List')
            ->assertSee('Replied Ticket List')
            ->assertDontSee('admin.report.salary');
    }

    public function test_member_coming_soon_page_is_member_only_and_names_feature(): void
    {
        $member = Member::create([
            'member_id' => 'STSIDEBAR2',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Sidebar Member 2',
            'mobile_no' => '9000000001',
            'pan_card_no' => 'SIDEBAR0002',
            'email' => 'sidebar2@example.test',
            'status' => 'active',
        ]);

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.coming-soon', ['feature' => 'ticket-entry']))
            ->assertOk()
            ->assertSeeText('Ticket Entry')
            ->assertSeeText('Coming Soon');

        $this->withSession(['member_context_id' => null])
            ->get(route('member.coming-soon', ['feature' => 'ticket-entry']))
            ->assertForbidden();
    }
}
