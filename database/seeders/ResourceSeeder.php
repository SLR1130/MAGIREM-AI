<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Resource;

class ResourceSeeder extends Seeder
{
    public function run(): void
    {
        $resources = [
            [
                'title' => 'Understanding Anxiety',
                'description' => 'Learn about anxiety symptoms, causes, and coping strategies',
                'category' => 'coping',
                'content' => 'Anxiety is a normal response to stress, but when it becomes overwhelming...',
                'url' => null,
                'is_active' => true
            ],
            [
                'title' => 'Stress Management Techniques',
                'description' => 'Practical techniques to manage daily stress',
                'category' => 'coping',
                'content' => 'Effective stress management includes breathing exercises, time management...',
                'url' => null,
                'is_active' => true
            ],
            [
                'title' => 'Sleep Hygiene Guide',
                'description' => 'Tips for better sleep quality',
                'category' => 'sleep',
                'content' => 'Good sleep hygiene involves maintaining a consistent sleep schedule...',
                'url' => null,
                'is_active' => true
            ],
            [
                'title' => 'Mindfulness Meditation',
                'description' => 'Introduction to mindfulness practices',
                'category' => 'meditation',
                'content' => 'Mindfulness meditation helps you stay present and reduce anxiety...',
                'url' => null,
                'is_active' => true
            ],
            [
                'title' => 'Building Resilience',
                'description' => 'Develop emotional resilience and coping skills',
                'category' => 'coping',
                'content' => 'Resilience is the ability to bounce back from challenges...',
                'url' => null,
                'is_active' => true
            ],
            [
                'title' => 'Healthy Relationships',
                'description' => 'Building and maintaining healthy relationships',
                'category' => 'general',
                'content' => 'Healthy relationships are built on trust, communication, and respect...',
                'url' => null,
                'is_active' => true
            ],
            [
                'title' => 'Depression Support',
                'description' => 'Understanding and managing depression',
                'category' => 'coping',
                'content' => 'Depression is more than just feeling sad. It affects how you think and feel...',
                'url' => null,
                'is_active' => true
            ],
            [
                'title' => 'Time Management for Students',
                'description' => 'Effective time management strategies',
                'category' => 'general',
                'content' => 'Balancing academics, social life, and self-care requires good time management...',
                'url' => null,
                'is_active' => true
            ]
        ];

        foreach ($resources as $resource) {
            Resource::create($resource);
        }
    }
}
