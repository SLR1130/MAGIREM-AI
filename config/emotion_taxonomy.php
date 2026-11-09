<?php

$taxonomyPath = base_path('config/emotion_taxonomy.json');

if (! is_file($taxonomyPath)) {
    return [
        'version' => '1.0.0',
        'categories' => [],
        'emotions' => [],
    ];
}

$taxonomy = json_decode((string) file_get_contents($taxonomyPath), true);

return is_array($taxonomy) ? $taxonomy : [
    'version' => '1.0.0',
    'categories' => [],
    'emotions' => [],
];
