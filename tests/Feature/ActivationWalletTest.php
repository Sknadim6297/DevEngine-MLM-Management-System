<?php

namespace Tests\Feature;

use App\Models\ActivationWalletTransaction;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivationWalletTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function member(string $id, float $balance = 0): Member
    {
        return Member::create([
            'member_id' => $id,
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Member ' . $id,
            'wallet_address' => null,
            'activation_wallet_amount' => $balance,
            'mobile_no' => '9876543210',
            'pan_card_no' => 'ABCDE1234F',
            'email' => strtolower($id) . '@example.com',
            'status' => 'active',
        ]);
    }

    public function test_lookup_returns_member_and_current_wallet_balance(): void
    {
        $member = $this->member('ST100001', 200);

        $this->getJson(route('admin.activation-wallet.credit-entry.member-lookup', ['member_id' => $member->member_id]))
            ->assertOk()
            ->assertJson([
                'member_id' => 'ST100001',
                'member_name' => 'Member ST100001',
                'activation_wallet_amount' => 200,
            ]);
    }

    public function test_transfer_adds_to_latest_balance_and_creates_history(): void
    {
        $member = $this->member('ST100001', 200);

        $this->post(route('admin.activation-wallet.store-credit-entry'), [
            'member_id' => $member->member_id,
            'amount' => 500,
        ])->assertRedirect(route('admin.activation-wallet.credit-entry'));

        $this->assertDatabaseHas('members', [
            'member_id' => $member->member_id,
            'activation_wallet_amount' => 700,
        ]);
        $this->assertDatabaseHas('activation_wallet_transactions', [
            'member_id' => $member->member_id,
            'member_name' => 'Member ST100001',
            'amount' => 500,
        ]);
    }

    public function test_invalid_transfer_amounts_do_not_change_wallet(): void
    {
        $member = $this->member('ST100001', 200);

        foreach (['', '0', '-1', 'abc'] as $amount) {
            $this->post(route('admin.activation-wallet.store-credit-entry'), [
                'member_id' => $member->member_id,
                'amount' => $amount,
            ])->assertSessionHasErrors('amount');
        }

        $this->assertDatabaseHas('members', [
            'member_id' => $member->member_id,
            'activation_wallet_amount' => 200,
        ]);
        $this->assertDatabaseCount('activation_wallet_transactions', 0);
    }

    public function test_history_search_date_filter_and_total_use_complete_filtered_dataset(): void
    {
        $member = $this->member('ST100001');
        $other = $this->member('ST100002');

        ActivationWalletTransaction::create(['member_id' => $member->member_id, 'member_name' => $member->member_name, 'amount' => 500, 'reference' => 'AW-ONE', 'created_at' => '2026-08-10 12:00:00', 'updated_at' => '2026-08-10 12:00:00']);
        ActivationWalletTransaction::create(['member_id' => $member->member_id, 'member_name' => $member->member_name, 'amount' => 750, 'reference' => 'AW-TWO', 'created_at' => '2026-08-20 12:00:00', 'updated_at' => '2026-08-20 12:00:00']);
        ActivationWalletTransaction::create(['member_id' => $other->member_id, 'member_name' => $other->member_name, 'amount' => 1000, 'reference' => 'AW-THREE', 'created_at' => '2026-09-01 12:00:00', 'updated_at' => '2026-09-01 12:00:00']);

        $this->get(route('admin.activation-wallet.credit-entry.list', [
            'member_id' => $member->member_id,
            'from_date' => '2026-08-01',
            'to_date' => '2026-08-31',
        ]))
            ->assertOk()
            ->assertSeeText('1250')
            ->assertSeeText('ST100001')
            ->assertSeeText('Member ST100001');
    }

    public function test_invalid_date_range_is_rejected(): void
    {
        $this->get(route('admin.activation-wallet.credit-entry.list', [
            'from_date' => '2026-09-20',
            'to_date' => '2026-09-01',
        ]))->assertSessionHasErrors('to_date');
    }

    public function test_filtered_export_contains_only_matching_records(): void
    {
        $member = $this->member('ST100001');
        $other = $this->member('ST100002');
        ActivationWalletTransaction::create(['member_id' => $member->member_id, 'member_name' => $member->member_name, 'amount' => 500, 'reference' => 'AW-EXPORT-1', 'created_at' => '2026-08-10 12:00:00', 'updated_at' => '2026-08-10 12:00:00']);
        ActivationWalletTransaction::create(['member_id' => $other->member_id, 'member_name' => $other->member_name, 'amount' => 750, 'reference' => 'AW-EXPORT-2', 'created_at' => '2026-08-10 12:00:00', 'updated_at' => '2026-08-10 12:00:00']);

        $this->get(route('admin.activation-wallet.credit-entry.export', ['member_id' => $member->member_id]))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
