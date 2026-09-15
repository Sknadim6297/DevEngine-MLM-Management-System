<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreeViewTest extends TestCase
{
    use RefreshDatabase;

    private function seedMembers(): void
    {
        Member::create([
            'member_id' => 'ST666666',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Admin',
            'wallet_address' => null,
            'activation_wallet_amount' => 0,
            'mobile_no' => '9000000000',
            'pan_card_no' => 'AAAAA0000A',
            'email' => 'admin@example.com',
            'status' => 'active',
        ]);

        Member::create([
            'member_id' => 'ST100001',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'SK ABU SALEH',
            'wallet_address' => null,
            'activation_wallet_amount' => 0,
            'mobile_no' => '9000000001',
            'pan_card_no' => 'AAAAA0000B',
            'email' => 'member1@example.com',
            'status' => 'active',
        ]);

        Member::create([
            'member_id' => 'ST100002',
            'sponsor_id' => 'ST100001',
            'sponsor_name' => 'SK ABU SALEH',
            'member_name' => 'Member A',
            'wallet_address' => null,
            'activation_wallet_amount' => 0,
            'mobile_no' => '9000000002',
            'pan_card_no' => 'AAAAA0000C',
            'email' => 'member2@example.com',
            'status' => 'active',
        ]);

        Member::create([
            'member_id' => 'ST100003',
            'sponsor_id' => 'ST100001',
            'sponsor_name' => 'SK ABU SALEH',
            'member_name' => 'Member B',
            'wallet_address' => null,
            'activation_wallet_amount' => 0,
            'mobile_no' => '9000000003',
            'pan_card_no' => 'AAAAA0000D',
            'email' => 'member3@example.com',
            'status' => 'active',
        ]);

        Member::create([
            'member_id' => 'ST100004',
            'sponsor_id' => 'ST100002',
            'sponsor_name' => 'Member A',
            'member_name' => 'Member A1',
            'wallet_address' => null,
            'activation_wallet_amount' => 0,
            'mobile_no' => '9000000004',
            'pan_card_no' => 'AAAAA0000E',
            'email' => 'member4@example.com',
            'status' => 'active',
        ]);
    }

    public function test_empty_member_id_shows_all_available_hierarchy(): void
    {
        $this->actingAs(User::factory()->create());
        $this->seedMembers();

        $this->get(route('admin.genealogy.tree-view'))
            ->assertOk()
            ->assertSee('Admin')
            ->assertSee('SK ABU SALEH')
            ->assertSee('Member A')
            ->assertSee('Member A1');
    }

    public function test_selected_member_id_shows_that_member_tree(): void
    {
        $this->actingAs(User::factory()->create());
        $this->seedMembers();

        $this->get(route('admin.genealogy.tree-view', ['member_id' => 'ST100001']))
            ->assertOk()
            ->assertSee('SK ABU SALEH')
            ->assertSee('Member A')
            ->assertSee('Member B')
            ->assertSee('Member A1');
    }

    public function test_invalid_member_id_shows_not_found_state_without_default_member_fallback(): void
    {
        $this->actingAs(User::factory()->create());
        $this->seedMembers();

        $this->get(route('admin.genealogy.tree-view', ['member_id' => 'ST999999']))
            ->assertOk()
            ->assertSee('Member not found.')
            ->assertDontSee('ST100001');
    }
}
