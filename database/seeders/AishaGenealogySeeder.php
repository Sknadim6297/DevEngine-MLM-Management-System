<?php

namespace Database\Seeders;

use App\Models\Investment;
use App\Models\Member;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AishaGenealogySeeder extends Seeder
{
    private const ROOT_MEMBER_ID = 'ST123458'; // Amit Sharma

    /**
    * Exactly 10 members:
    * 1 member at each level from Amit Sharma.
     */
    private const MEMBERS = [
        // Level 1
        [
            'level' => 1,
            'member_id' => 'ST876101',
            'name' => 'Ram Sharma',
            'mobile' => '9810000101',
            'sponsor_id' => self::ROOT_MEMBER_ID,
        ],
    
        // Level 2
        [
            'level' => 2,
            'member_id' => 'ST876103',
            'name' => 'Nadeem Khan',
            'mobile' => '9810000103',
            'sponsor_id' => 'ST876101',
        ],
    
        // Level 3
        [
            'level' => 3,
            'member_id' => 'ST876105',
            'name' => 'Priya Singh',
            'mobile' => '9810000105',
            'sponsor_id' => 'ST876103',
        ],

        // Level 4
        [
            'level' => 4,
            'member_id' => 'ST876107',
            'name' => 'Neha Sharma',
            'mobile' => '9810000107',
            'sponsor_id' => 'ST876105',
        ],
        // Level 5
        [
            'level' => 5,
            'member_id' => 'ST876109',
            'name' => 'Sana Khan',
            'mobile' => '9810000109',
            'sponsor_id' => 'ST876107',
        ],
    
        // Level 6
        [
            'level' => 6,
            'member_id' => 'ST876111',
            'name' => 'Ravi Kumar',
            'mobile' => '9810000111',
            'sponsor_id' => 'ST876109',
        ],
       

        // Level 7
        [
            'level' => 7,
            'member_id' => 'ST876113',
            'name' => 'Sameer Ali',
            'mobile' => '9810000113',
            'sponsor_id' => 'ST876111',
        ],
       
        // Level 8
        [
            'level' => 8,
            'member_id' => 'ST876115',
            'name' => 'Kabir Khan',
            'mobile' => '9810000115',
            'sponsor_id' => 'ST876113',
        ],
        // Level 9
        [
            'level' => 9,
            'member_id' => 'ST876117',
            'name' => 'Adil Hussain',
            'mobile' => '9810000117',
            'sponsor_id' => 'ST876115',
        ],
        // Level 10
        [
            'level' => 10,
            'member_id' => 'ST876119',
            'name' => 'Farhan Ahmed',
            'mobile' => '9810000119',
            'sponsor_id' => 'ST876117',
        ],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'AishaGenealogySeeder can only run in local or testing environments.'
            );
        }

        $root = Member::query()
            ->where('member_id', self::ROOT_MEMBER_ID)
            ->first();

        if (! $root || $root->member_name !== 'Amit Sharma') {
            throw new RuntimeException(
                'Amit Sharma (ST123458) must already exist before running this seeder.'
            );
        }

        DB::transaction(function () use ($root): void {
            $members = [];

            foreach (self::MEMBERS as $data) {
                $sponsor = Member::query()
                    ->where('member_id', $data['sponsor_id'])
                    ->first();

                if (! $sponsor) {
                    throw new RuntimeException(
                        "Sponsor {$data['sponsor_id']} not found for {$data['member_id']}."
                    );
                }

                $member = Member::query()->updateOrCreate(
                    [
                        'member_id' => $data['member_id'],
                    ],
                    [
                        'sponsor_id' => $data['sponsor_id'],
                        'sponsor_name' => $sponsor->member_name,
                        'member_name' => $data['name'],
                        'mobile_no' => $data['mobile'],
                        'email' => strtolower(
                            str_replace(' ', '.', $data['name'])
                        ) . '.' . $data['member_id'] . '@example.test',
                        'pan_card_no' => sprintf(
                            'AISHDEV%03d',
                            (int) substr($data['member_id'], -3)
                        ),
                        'password' => bcrypt('member12345'),
                        'status' => 'active',
                    ]
                );

                $members[$data['level']][] = $member->fresh();

                Investment::query()->updateOrCreate(
                    [
                        'investment_id' => sprintf(
                            'INV876%03d',
                            (int) substr($data['member_id'], -3)
                        ),
                    ],
                    [
                        'member_id' => $member->member_id,
                        'member_name' => $member->member_name,
                        'amount' => '100.0000',
                        'status' => 'active',
                        'closed_at' => null,
                    ]
                );
            }

            $this->reportResults($members);
        });
    }

    private function reportResults(array $members): void
    {
        $rows = [];

        for ($level = 1; $level <= 10; $level++) {
            $levelMembers = $members[$level] ?? [];

            $rows[] = [
                $level,
                count($levelMembers),
                collect($levelMembers)
                    ->map(fn (Member $member) => $member->member_id)
                    ->implode(', '),
                '100.0000 USDT',
            ];
        }

        $this->command?->table(
            ['Level', 'Members', 'Member IDs', 'Investment'],
            $rows
        );

        $this->command?->info('Total members created/updated: 10');
        $this->command?->info('Total active investment: 1,000.0000 USDT');
        $this->command?->info(
            'Level Commission is NOT generated by this seeder. Use the real scheduler.'
        );
    }
}