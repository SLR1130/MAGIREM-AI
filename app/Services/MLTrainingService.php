<?php

namespace App\Services;

use App\Models\MLTrainingData;
use App\Models\EmotionalEntry;
use Illuminate\Support\Facades\Log;

class MLTrainingService
{
    /**
     * Store ML prediction for training
     *
     * @param EmotionalEntry $entry
     * @param array $mlResult
     * @return MLTrainingData
     */
    public function storePrediction(EmotionalEntry $entry, array $mlResult): MLTrainingData
    {
        return MLTrainingData::create([
            'emotional_entry_id' => $entry->id,
            'text_input' => $entry->entry_text,
            'predicted_emotion' => $mlResult['emotion'],
            'prediction_confidence' => $mlResult['confidence'],
            'predicted_crisis_level' => $mlResult['crisis_level'] ?? 'none',
            'predicted_contexts' => $mlResult['contexts'] ?? [],
            'model_version' => $mlResult['method'] ?? 'v1.0',
        ]);
    }

    /**
     * Validate/correct a prediction (by counselor)
     *
     * @param int $trainingDataId
     * @param string $actualEmotion
     * @param string|null $actualCrisisLevel
     * @param array|null $actualContexts
     * @param int $validatorId
     * @return MLTrainingData
     */
    public function validatePrediction(
        int $trainingDataId,
        string $actualEmotion,
        ?string $actualCrisisLevel = null,
        ?array $actualContexts = null,
        int $validatorId
    ): MLTrainingData {
        $trainingData = MLTrainingData::findOrFail($trainingDataId);
        
        $trainingData->update([
            'actual_emotion' => $actualEmotion,
            'actual_crisis_level' => $actualCrisisLevel,
            'actual_contexts' => $actualContexts,
            'is_validated' => true,
            'validated_by' => $validatorId,
            'validated_at' => now(),
        ]);

        Log::info('ML prediction validated', [
            'training_data_id' => $trainingDataId,
            'predicted' => $trainingData->predicted_emotion,
            'actual' => $actualEmotion,
            'correct' => $trainingData->isPredictionCorrect(),
        ]);

        return $trainingData->fresh();
    }

    /**
     * Export training data to CSV for model training
     *
     * @param bool $validatedOnly
     * @return string Path to CSV file
     */
    public function exportTrainingData(bool $validatedOnly = true): string
    {
        $query = MLTrainingData::query();
        
        if ($validatedOnly) {
            $query->where('is_validated', true);
        }
        
        $data = $query->get();
        
        $filename = storage_path('app/ml_training/training_data_' . date('Y-m-d_His') . '.csv');
        
        // Create directory if it doesn't exist
        if (!file_exists(dirname($filename))) {
            mkdir(dirname($filename), 0755, true);
        }
        
        $file = fopen($filename, 'w');
        
        // Write header
        fputcsv($file, [
            'text',
            'emotion',
            'crisis_level',
            'contexts',
            'confidence',
            'is_correct'
        ]);
        
        // Write data
        foreach ($data as $row) {
            $emotion = $row->is_validated ? $row->actual_emotion : $row->predicted_emotion;
            $crisisLevel = $row->is_validated ? $row->actual_crisis_level : $row->predicted_crisis_level;
            $contexts = $row->is_validated ? $row->actual_contexts : $row->predicted_contexts;
            
            fputcsv($file, [
                $row->text_input,
                $emotion,
                $crisisLevel,
                json_encode($contexts),
                $row->prediction_confidence,
                $row->isPredictionCorrect() ? 'yes' : 'no'
            ]);
        }
        
        fclose($file);
        
        Log::info('Training data exported', [
            'filename' => $filename,
            'rows' => $data->count(),
            'validated_only' => $validatedOnly
        ]);
        
        return $filename;
    }

    /**
     * Get training statistics
     *
     * @return array
     */
    public function getStatistics(): array
    {
        $total = MLTrainingData::count();
        $validated = MLTrainingData::where('is_validated', true)->count();
        $usedForTraining = MLTrainingData::where('used_for_training', true)->count();
        
        $accuracy = MLTrainingData::getAccuracy();
        
        // Emotion distribution
        $emotionDistribution = MLTrainingData::where('is_validated', true)
            ->selectRaw('actual_emotion, COUNT(*) as count')
            ->groupBy('actual_emotion')
            ->pluck('count', 'actual_emotion')
            ->toArray();
        
        // Crisis level distribution
        $crisisDistribution = MLTrainingData::where('is_validated', true)
            ->selectRaw('actual_crisis_level, COUNT(*) as count')
            ->groupBy('actual_crisis_level')
            ->pluck('count', 'actual_crisis_level')
            ->toArray();
        
        return [
            'total_samples' => $total,
            'validated_samples' => $validated,
            'used_for_training' => $usedForTraining,
            'pending_validation' => $total - $validated,
            'accuracy' => $accuracy,
            'emotion_distribution' => $emotionDistribution,
            'crisis_distribution' => $crisisDistribution,
        ];
    }

    /**
     * Mark data as used for training
     *
     * @param array $ids
     * @return int Number of records updated
     */
    public function markAsUsedForTraining(array $ids): int
    {
        return MLTrainingData::whereIn('id', $ids)
            ->update(['used_for_training' => true]);
    }
}
