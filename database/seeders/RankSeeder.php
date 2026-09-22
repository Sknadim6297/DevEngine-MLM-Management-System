<?php

namespace Database\Seeders;

use App\Models\Rank;
use Illuminate\Database\Seeder;

class RankSeeder extends Seeder
{
    public function run(): void
    {
        $ranks = [
            ['name' => 'Silver', 'required_full_team_business' => '6000.0000', 'unlocked_levels' => 4, 'is_active' => true, 'sort_order' => 1],
            ['name' => 'Gold', 'required_full_team_business' => '12000.0000', 'unlocked_levels' => 8, 'is_active' => true, 'sort_order' => 2],
            ['name' => 'Platinum', 'required_full_team_business' => '25000.0000', 'unlocked_levels' => 12, 'is_active' => true, 'sort_order' => 3],
            ['name' => 'Ruby', 'required_full_team_business' => '50000.0000', 'unlocked_levels' => 16, 'is_active' => true, 'sort_order' => 4],
            ['name' => 'Ruby Club', 'required_full_team_business' => '100000.0000', 'unlocked_levels' => 20, 'is_active' => true, 'sort_order' => 5],
            ['name' => 'Diamond', 'required_full_team_business' => '250000.0000', 'unlocked_levels' => 24, 'is_active' => true, 'sort_order' => 6],
            ['name' => 'Blue Diamond', 'required_full_team_business' => '600000.0000', 'unlocked_levels' => 28, 'is_active' => true, 'sort_order' => 7],
            ['name' => 'Master Blaster', 'required_full_team_business' => '1500000.0000', 'unlocked_levels' => 32, 'is_active' => true, 'sort_order' => 8],
        ];

        foreach ($ranks as $rank) {
            Rank::updateOrCreate(['sort_order' => $rank['sort_order']], $rank);
        }
    }
}
