<?php

namespace App\Services;

use App\Models\SelfAssessment;
use Illuminate\Support\Facades\Log;

class AssessmentService
{
    /**
     * Assessment questions by type
     */
    private $assessments = [
        'stress' => [
            'How often do you feel overwhelmed by tasks?',
            'Do you have difficulty sleeping due to stress?',
            'How often do you feel anxious about the future?',
            'Do you struggle to concentrate on work/studies?',
            'How often do you feel physically tense?'
        ],
        'wellbeing' => [
            'How satisfied are you with your life?',
            'Do you feel connected to others?',
            'How often do you engage in activities you enjoy?',
            'Do you feel a sense of purpose?',
            'How would you rate your overall happiness?'
        ],
        'anxiety' => [
            'How often do you experience panic or anxiety?',
            'Do you worry excessively about things?',
            'Do you avoid situations due to anxiety?',
            'How often do you feel restless or on edge?',
            'Do you experience physical symptoms of anxiety?'
        ],
        'depression' => [
            'How often do you feel sad or empty?',
            'Do you lose interest in activities you enjoy?',
            'How is your energy level?',
            'Do you have feelings of worthlessness?',
            'How often do you think about the future negatively?'
        ]
    ];

    /**
     * Get assessment questions
     */
    public function getAssessmentQuestions($type = 'general')
    {
        return $this->assessments[$type] ?? $this->assessments['wellbeing'];
    }

    /**
     * Store assessment response
     */
    public function storeAssessment($userId, $type, $answers)
    {
        $score = $this->calculateScore($answers);
        $interpretation = $this->interpretScore($type, $score);

        $assessment = SelfAssessment::create([
            'user_id' => $userId,
            'assessment_type' => $type,
            'questions' => $this->getAssessmentQuestions($type),
            'answers' => $answers,
            'score' => $score,
            'interpretation' => $interpretation
        ]);

        Log::info("Assessment stored for user {$userId}, type: {$type}, score: {$score}");

        return $assessment;
    }

    /**
     * Calculate score from answers (1-5 scale)
     */
    private function calculateScore($answers)
    {
        $total = array_sum($answers);
        $count = count($answers);
        return $count > 0 ? round(($total / $count), 2) : 0;
    }

    /**
     * Interpret score based on assessment type
     */
    private function interpretScore($type, $score)
    {
        $interpretations = [
            'stress' => [
                'low' => 'Your stress levels appear to be manageable. Keep up healthy coping strategies.',
                'moderate' => 'You may be experiencing moderate stress. Consider stress management techniques.',
                'high' => 'You appear to be experiencing high stress. Consider reaching out to a counselor.'
            ],
            'wellbeing' => [
                'low' => 'Your wellbeing could use some attention. Consider self-care activities.',
                'moderate' => 'Your wellbeing is moderate. Focus on activities that bring you joy.',
                'high' => 'Your wellbeing appears to be good. Continue nurturing positive habits.'
            ],
            'anxiety' => [
                'low' => 'Your anxiety levels appear manageable.',
                'moderate' => 'You may benefit from anxiety management techniques.',
                'high' => 'Consider speaking with a counselor about anxiety management.'
            ],
            'depression' => [
                'low' => 'Your mood appears to be stable.',
                'moderate' => 'Consider engaging in mood-boosting activities.',
                'high' => 'Please reach out to a counselor or mental health professional.'
            ]
        ];

        $level = $score < 2.5 ? 'low' : ($score < 3.5 ? 'moderate' : 'high');
        return $interpretations[$type][$level] ?? 'Thank you for completing this assessment.';
    }

    /**
     * Get user's assessment history
     */
    public function getUserAssessments($userId)
    {
        return SelfAssessment::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get assessment statistics
     */
    public function getAssessmentStats()
    {
        $stats = [];
        foreach (array_keys($this->assessments) as $type) {
            $assessments = SelfAssessment::where('assessment_type', $type)->get();
            $stats[$type] = [
                'count' => $assessments->count(),
                'average_score' => $assessments->avg('score'),
                'high_risk_count' => $assessments->where('score', '>=', 3.5)->count()
            ];
        }
        return $stats;
    }
}
