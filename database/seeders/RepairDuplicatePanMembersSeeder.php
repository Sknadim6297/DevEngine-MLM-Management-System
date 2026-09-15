<?php

namespace Database\Seeders;

use App\Models\Member;
use Illuminate\Database\Seeder;

class RepairDuplicatePanMembersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $members = Member::query()
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $grouped = [];

        foreach ($members as $member) {
            $pan = strtoupper(trim((string) $member->pan_card_no));

            if ($pan === '') {
                continue;
            }

            $grouped[$pan][] = $member;
        }

        foreach ($grouped as $pan => $records) {
            if (count($records) <= 3) {
                continue;
            }

            foreach (array_slice($records, 3) as $extraMember) {
                $extraMember->delete();
            }
        }
    }
}
