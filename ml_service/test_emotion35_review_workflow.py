"""Focused tests for the private Emotion35 review-sheet workflow."""
from __future__ import annotations

import csv
import json
import subprocess
import sys
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
BUILD = ROOT / "ml_service" / "build_emotion35_review_sheet.py"
IMPORT = ROOT / "ml_service" / "import_emotion35_review_sheet.py"
TAXONOMY = ROOT / "config" / "emotion_taxonomy.json"
WORKFLOW = ROOT / "storage" / "app" / "datasets" / "emotion35_review_workflow"


class ReviewWorkflowTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls) -> None:
        subprocess.run([sys.executable, str(BUILD), "--batch-per-context", "5"], check=True, capture_output=True, text=True)

    def test_build_and_defaults(self) -> None:
        with (WORKFLOW / "review_batch_001.csv").open(encoding="utf-8-sig", newline="") as handle:
            rows = list(csv.DictReader(handle))
        self.assertEqual(len(rows), 220)
        self.assertTrue(all(row["review_status"] == "unreviewed" for row in rows))
        self.assertTrue(all(not row["reviewed_labels"] for row in rows))
        self.assertTrue(all(not row["reviewed_observed_labels"] for row in rows))

    def test_unreviewed_batch_is_not_imported(self) -> None:
        output = WORKFLOW / "_test_reviewed_import"
        output.mkdir(parents=True, exist_ok=True)
        result = subprocess.run([sys.executable, str(IMPORT), "--input", str(WORKFLOW / "review_batch_001.csv"), "--output-dir", str(output), "--taxonomy", str(TAXONOMY)], capture_output=True, text=True)
        self.assertEqual(result.returncode, 0)
        report = json.loads((output / "import_report.json").read_text(encoding="utf-8"))
        self.assertEqual(report["counts"]["rows_seen"], 220)
        self.assertFalse(report["training_ready"])
        self.assertEqual(sum(value for key, value in report["counts"].items() if key.startswith("not_imported_")), 220)


if __name__ == "__main__":
    unittest.main()
