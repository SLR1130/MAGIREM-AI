<?php

namespace App\Services;

/**
 * The single Laravel-side definition for the reporting taxonomy.
 *
 * This is intentionally additive: the trained model's original label and
 * confidence remain the source of truth for routing and safety decisions.
 */
class EmotionTaxonomy
{
    public function version(): string
    {
        return (string) config('emotion_taxonomy.version', '1.0.0');
    }

    public function categories(): array
    {
        return array_values(config('emotion_taxonomy.categories', []));
    }

    public function emotions(): array
    {
        return array_values(config('emotion_taxonomy.emotions', []));
    }

    public function categoryKeys(): array
    {
        return array_values(array_filter(array_map(
            static fn (array $category): ?string => $category['key'] ?? null,
            $this->categories()
        )));
    }

    public function definitionFor(?string $key): ?array
    {
        if ($key === null || $key === '') {
            return null;
        }

        foreach ($this->emotions() as $emotion) {
            if (($emotion['key'] ?? null) === $key) {
                return $emotion;
            }
        }

        return null;
    }

    /**
     * Map a known legacy/model label to a controlled detailed label.
     * Unknown labels deliberately return null; they must remain unclassified.
     */
    public function map(?string $label): ?array
    {
        $normalized = $this->normalize($label);
        if ($normalized === null) {
            return null;
        }

        foreach ($this->emotions() as $emotion) {
            $aliases = array_merge([$emotion['key'] ?? ''], $emotion['aliases'] ?? []);
            foreach ($aliases as $alias) {
                if ($normalized === $this->normalize((string) $alias)) {
                    $category = collect($this->categories())->firstWhere('key', $emotion['category_key'] ?? null);

                    return [
                        'key' => $emotion['key'],
                        'label' => $emotion['label'],
                        'category_key' => $emotion['category_key'],
                        'category_label' => $category['label'] ?? $emotion['category_key'],
                        'version' => $this->version(),
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Build persistence metadata without changing the legacy emotion field.
     */
    public function metadata(array $emotionData): array
    {
        $label = $emotionData['emotion'] ?? null;
        $mapped = $this->map(is_string($label) ? $label : null);
        $confidence = $emotionData['confidence'] ?? null;
        $confidence = is_numeric($confidence) ? (float) $confidence : null;
        if ($confidence !== null && $confidence > 1) {
            $confidence /= 100;
        }

        $uncertainty = $emotionData['uncertainty'] ?? null;
        if (! is_numeric($uncertainty)) {
            $uncertainty = ($confidence !== null && (($emotionData['uncertain'] ?? false) || $confidence < 0.55))
                ? 1 - $confidence
                : null;
        } else {
            $uncertainty = (float) $uncertainty;
            if ($uncertainty > 1) {
                $uncertainty /= 100;
            }
        }

        return [
            'detailed_emotion_key' => $mapped['key'] ?? null,
            'emotion_category_key' => $mapped['category_key'] ?? null,
            'emotion_classification_source' => $emotionData['emotion_source']
                ?? $emotionData['source']
                ?? $emotionData['method']
                ?? null,
            'emotion_taxonomy_version' => $label !== null && trim((string) $label) !== '' ? $this->version() : null,
            'emotion_uncertainty' => $uncertainty !== null ? max(0, min(1, $uncertainty)) : null,
        ];
    }

    public function normalize(?string $label): ?string
    {
        if ($label === null) {
            return null;
        }

        $label = trim(mb_strtolower($label));
        if ($label === '') {
            return null;
        }

        return preg_replace('/[\\s_]+/u', ' ', $label) ?: $label;
    }
}
