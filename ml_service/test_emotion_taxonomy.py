"""Fast regression checks for the shared reporting taxonomy (no model loading)."""

import unittest

from emotion_taxonomy import classify_label, load_taxonomy


class EmotionTaxonomyTest(unittest.TestCase):
    def test_vocabulary_and_category_cardinality(self):
        taxonomy = load_taxonomy()
        self.assertEqual(len(taxonomy["categories"]), 8)
        self.assertEqual(len(taxonomy["emotions"]), 35)
        self.assertEqual(len({item["key"] for item in taxonomy["emotions"]}), 35)

    def test_existing_model_labels_are_additively_mapped(self):
        self.assertEqual(classify_label("joy")["key"], "happiness_joy")
        self.assertEqual(classify_label("sadness")["key"], "sadness")
        self.assertEqual(classify_label("grief")["key"], "grief")
        self.assertEqual(classify_label("neutral")["category_key"], "neutral")

    def test_unknown_labels_are_not_fabricated(self):
        self.assertIsNone(classify_label("model-label-not-in-taxonomy"))
        self.assertIsNone(classify_label("depressed"))

    def test_every_approved_emotion_derives_one_authoritative_category(self):
        taxonomy = load_taxonomy()
        categories = {item["key"] for item in taxonomy["categories"]}
        for emotion in taxonomy["emotions"]:
            mapped = classify_label(emotion["key"])
            self.assertIsNotNone(mapped, emotion["key"])
            self.assertEqual(mapped["key"], emotion["key"])
            self.assertEqual(mapped["category_key"], emotion["category_key"])
            self.assertIn(mapped["category_key"], categories)


if __name__ == "__main__":
    unittest.main()
