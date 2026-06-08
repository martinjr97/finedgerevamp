<?php

namespace Database\Seeders;

use App\Models\GroupMemberTitle;
use Illuminate\Database\Seeder;

class GroupMemberTitleSeeder extends Seeder
{
    public function run(): void
    {
        $titles = [
            [
                'name' => 'Leader',
                'description' => 'Primary lead member responsible for group coordination.',
            ],
            [
                'name' => 'Coordinator',
                'description' => 'Supports the leader and coordinates member activities.',
            ],
            [
                'name' => 'Member',
                'description' => 'Standard group member.',
            ],
        ];

        foreach ($titles as $title) {
            GroupMemberTitle::updateOrCreate(
                ['name' => $title['name']],
                [
                    'description' => $title['description'],
                    'is_active' => true,
                ]
            );
        }
    }
}
