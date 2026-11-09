<?php

namespace Tests\Unit\Services;

use App\Services\EmotionTaxonomy;
use Tests\TestCase;

class EmotionTaxonomyTest extends TestCase
{
    public function test_taxonomy_contains_thirty_five_unique_emotions_in_eight_categories(): void
    {
        $taxonomy = app(EmotionTaxonomy::class);

        $this->assertSame('1.0.0', $taxonomy->version());
        $this->assertCount(8, $taxonomy->categories());
        $this->assertCount(35, $taxonomy->emotions());
        $this->assertCount(35, collect($taxonomy->emotions())->pluck('key')->unique());
        $this->assertSame(
            [11, 7, 4, 4, 3, 2, 1, 3],
            collect($taxonomy->categories())->map(fn (array $category) => collect($taxonomy->emotions())->where('category_key', $category['key'])->count())->values()->all()
        );
    }

    public function test_legacy_model_labels_map_without_changing_the_original_label(): void
    {
        $taxonomy = app(EmotionTaxonomy::class);

        $this->assertSame('happiness_joy', $taxonomy->map('joy')['key']);
        $this->assertSame('sadness', $taxonomy->map('sad')['key']);
        $this->assertSame('anxiety', $taxonomy->map('anxiety')['key']);
        $this->assertSame('neutral', $taxonomy->map('neutral')['key']);
        $this->assertSame('sadness_disconnection', $taxonomy->map('grief')['category_key']);
    }

    public function test_unsupported_or_clinical_sounding_labels_remain_unclassified(): void
    {
        $taxonomy = app(EmotionTaxonomy::class);

        $this->assertNull($taxonomy->map('unsupported-model-label'));
        $this->assertNull($taxonomy->map('depressed'));
        $this->assertNull($taxonomy->map(null));
    }

    public function test_metadata_preserves_uncertainty_for_low_confidence_predictions(): void
    {
        $metadata = app(EmotionTaxonomy::class)->metadata([
            'emotion' => 'joy',
            'confidence' => 0.42,
            'source' => 'trained_model',
        ]);

        $this->assertSame('happiness_joy', $metadata['detailed_emotion_key']);
        $this->assertSame('positive_wellbeing', $metadata['emotion_category_key']);
        $this->assertSame('trained_model', $metadata['emotion_classification_source']);
        $this->assertEqualsWithDelta(0.58, $metadata['emotion_uncertainty'], 0.0001);
    }
}
