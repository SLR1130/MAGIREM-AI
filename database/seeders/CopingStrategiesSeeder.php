<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CopingStrategiesSeeder extends Seeder
{
    public function run(): void
    {
        $strategies = [
            // Breathing Techniques
            [
                'name' => '4-7-8 Breathing',
                'description' => 'A calming breathing technique that helps reduce anxiety and improve focus.',
                'category' => 'breathing',
                'instructions' => 'Inhale for 4 counts, hold for 7 counts, exhale for 8 counts. Repeat 4 times.',
                'duration_minutes' => 5,
                'difficulty' => 'easy',
                'is_active' => true,
            ],
            [
                'name' => 'Box Breathing',
                'description' => 'Equal-count breathing that promotes calmness and reduces stress.',
                'category' => 'breathing',
                'instructions' => 'Inhale for 4, hold 4, exhale 4, hold 4. Repeat for several minutes.',
                'duration_minutes' => 5,
                'difficulty' => 'easy',
                'is_active' => true,
            ],
            [
                'name' => 'Deep Belly Breathing',
                'description' => 'Focuses on deep abdominal breathing to activate the parasympathetic nervous system.',
                'category' => 'breathing',
                'instructions' => 'Place hand on belly. Breathe in through nose (belly expands), out through mouth.',
                'duration_minutes' => 3,
                'difficulty' => 'easy',
                'is_active' => true,
            ],
            
            // Mindfulness
            [
                'name' => 'Body Scan Meditation',
                'description' => 'Systematically relax your body from head to toe while building awareness.',
                'category' => 'mindfulness',
                'instructions' => 'Lie down and mentally scan your body from head to feet, relaxing each part.',
                'duration_minutes' => 15,
                'difficulty' => 'easy',
                'is_active' => true,
            ],
            [
                'name' => 'Mindful Observation',
                'description' => 'Focus intently on an object to anchor your attention in the present moment.',
                'category' => 'mindfulness',
                'instructions' => 'Choose an object. Observe it for 5 minutes, noticing every detail without judgment.',
                'duration_minutes' => 5,
                'difficulty' => 'easy',
                'is_active' => true,
            ],
            [
                'name' => '5-Minute Meditation',
                'description' => 'A quick guided meditation to calm your mind and reduce stress.',
                'category' => 'mindfulness',
                'instructions' => 'Find a quiet space, close your eyes, and focus on your breath for 5 minutes.',
                'duration_minutes' => 5,
                'difficulty' => 'easy',
                'is_active' => true,
            ],
            
            // Grounding Techniques
            [
                'name' => '5-4-3-2-1 Grounding',
                'description' => 'Engage your senses to connect with the present moment and reduce anxiety.',
                'category' => 'grounding',
                'instructions' => 'Notice 5 things you see, 4 you hear, 3 you touch, 2 you smell, 1 you taste.',
                'duration_minutes' => 5,
                'difficulty' => 'easy',
                'is_active' => true,
            ],
            [
                'name' => 'Feet on Ground',
                'description' => 'A quick grounding technique using physical sensation to anchor yourself.',
                'category' => 'grounding',
                'instructions' => 'Press your feet firmly into the ground. Notice the sensation for 2 minutes.',
                'duration_minutes' => 2,
                'difficulty' => 'easy',
                'is_active' => true,
            ],
            [
                'name' => 'Cold Water Splash',
                'description' => 'Use the shock of cold water to ground yourself immediately.',
                'category' => 'grounding',
                'instructions' => 'Splash your face with cold water or hold ice cubes in your hands.',
                'duration_minutes' => 1,
                'difficulty' => 'easy',
                'is_active' => true,
            ],
            
            // Stress Management
            [
                'name' => 'Progressive Muscle Relaxation',
                'description' => 'Tense and release muscle groups to release physical tension.',
                'category' => 'stress_management',
                'instructions' => 'Tense each muscle group for 5 seconds, then release. Work from toes to head.',
                'duration_minutes' => 10,
                'difficulty' => 'medium',
                'is_active' => true,
            ],
            [
                'name' => 'Journaling',
                'description' => 'Write down your thoughts and feelings to process emotions and gain clarity.',
                'category' => 'stress_management',
                'instructions' => 'Spend 10 minutes writing whatever comes to mind without judgment.',
                'duration_minutes' => 10,
                'difficulty' => 'easy',
                'is_active' => true,
            ],
            [
                'name' => 'Take a Walk',
                'description' => 'Physical movement combined with fresh air to reduce stress and anxiety.',
                'category' => 'stress_management',
                'instructions' => 'Go for a 20-30 minute walk at a comfortable pace, focusing on your surroundings.',
                'duration_minutes' => 30,
                'difficulty' => 'easy',
                'is_active' => true,
            ],
        ];

        foreach ($strategies as $strategy) {
            DB::table('coping_strategies')->updateOrInsert(
                ['name' => $strategy['name']],
                $strategy
            );
        }
    }
}
