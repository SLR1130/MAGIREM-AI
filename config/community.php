<?php

return [
    'categories' => [
        'academic' => ['label' => 'Academic Stress', 'icon' => 'book-open'],
        'mental-health' => ['label' => 'Mental Wellness', 'icon' => 'brain'],
        'relationships' => ['label' => 'Friendship and Relationships', 'icon' => 'heart'],
        'motivation' => ['label' => 'Motivation and Personal Growth', 'icon' => 'sprout'],
        'general' => ['label' => 'General Discussions', 'icon' => 'message-circle'],
    ],
    'support_labels' => [
        'advice' => 'Looking for Advice',
        'encouragement' => 'Need Encouragement',
        'sharing' => 'Just Sharing',
        'listen' => 'Someone to Listen',
    ],
    'reaction_types' => [
        // Legacy supportive reactions remain valid for historical records.
        'support' => 'Support',
        'heard' => "You're Heard",
        'helpful' => 'Helpful',
        'encouragement' => 'Encouragement',
        // The simple social feed exposes these as distinct primary actions.
        'like' => 'Like',
        'heart' => 'Heart',
    ],
    'report_reasons' => [
        'harassment' => 'Harassment or bullying',
        'discrimination' => 'Discrimination or hateful behavior',
        'harmful' => 'Inappropriate or harmful content',
        'privacy' => 'Privacy violation',
        'spam' => 'Spam',
        'other' => 'Other concerns',
    ],
    'limits' => [
        'title' => 140,
        'message' => 2000,
        'comment' => 1000,
    ],
];
