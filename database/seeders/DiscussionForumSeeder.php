<?php

namespace Database\Seeders;

use App\Models\DiscussionForum;
use Illuminate\Database\Seeder;

class DiscussionForumSeeder extends Seeder
{
    public function run(): void
    {
        $forums = [
            [
                'title' => 'Academic Stress',
                'slug' => 'academic-stress',
                'description' => 'Exam anxiety, workload management, study tips',
                'category' => 'academic',
                'icon' => '📚',
                'is_active' => true,
            ],
            [
                'title' => 'Anxiety & Stress',
                'slug' => 'anxiety-stress',
                'description' => 'Managing anxiety, coping strategies, support',
                'category' => 'anxiety',
                'icon' => '😰',
                'is_active' => true,
            ],
            [
                'title' => 'Relationships',
                'slug' => 'relationships',
                'description' => 'Dating, friendships, family, social skills',
                'category' => 'relationships',
                'icon' => '💔',
                'is_active' => true,
            ],
            [
                'title' => 'Mental Health',
                'slug' => 'mental-health',
                'description' => 'Depression, self-esteem, personal growth',
                'category' => 'mental-health',
                'icon' => '🧠',
                'is_active' => true,
            ],
            [
                'title' => 'General Support',
                'slug' => 'general-support',
                'description' => 'General discussions, advice, encouragement',
                'category' => 'general',
                'icon' => '👥',
                'is_active' => true,
            ],
        ];

        foreach ($forums as $forumData) {
            DiscussionForum::firstOrCreate(
                ['slug' => $forumData['slug']],
                $forumData
            );
        }
    }
}
