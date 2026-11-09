import sys
import unittest
from pathlib import Path

# The service uses local absolute imports when launched from ml_service/.
sys.path.insert(0, str(Path(__file__).resolve().parent))

from hybrid_response_engine import HybridResponseEngine


class FinancialNumericValidatorTests(unittest.TestCase):
    def setUp(self):
        self.engine = HybridResponseEngine()
        self.history = [
            {
                "user": "My weekly income is \u20b11,000. My monthly rent is \u20b11,000, utilities are \u20b11,000, transportation is \u20b150 for 20 workdays, and food costs \u20b11,500 per month.",
                "ai": "synthetic",
            },
            {
                "user": "What if I reduce my food expenses by \u20b1200 monthly?",
                "ai": "Food is \u20b11,300; total is \u20b14,300; deficit is \u20b1300.",
            },
        ]
        self.message = "Now increase that food budget by \u20b1100."

    def test_rejects_chained_category_and_total_contradiction(self):
        response = ("Food: \u20b11,600. Rent: \u20b11,000. Utilities: \u20b11,000. "
                    "Transportation: \u20b1800. Total expenses: \u20b14,400. Deficit: \u20b1400.")
        self.assertEqual(self.engine._numeric_quality_retry_reason(self.message, response, self.history), "numeric_category_amount")

    def test_rejects_wrong_total_even_when_categories_are_omitted(self):
        response = "Your revised food budget is \u20b11,400. Total expenses are \u20b14,900, leaving a \u20b1400 deficit."
        self.assertEqual(self.engine._numeric_quality_retry_reason(self.message, response, self.history), "numeric_expense_total")

    def test_accepts_verified_chained_values(self):
        response = ("Food: \u20b11,400. Rent: \u20b11,000. Utilities: \u20b11,000. "
                    "Transportation: \u20b11,000. Total expenses: \u20b14,400. Deficit: \u20b1400.")
        self.assertIsNone(self.engine._numeric_quality_retry_reason(self.message, response, self.history))

    def test_rejects_deficit_language_for_positive_balance(self):
        history = [{"user": "My weekly income is \u20b11,000 and rent is \u20b11,000 monthly.", "ai": "synthetic"}]
        response = "Known expenses now exceed the monthly baseline by \u20b1100; the deficit is -\u20b1100."
        self.assertEqual(
            self.engine._numeric_quality_retry_reason("Change rent to \u20b1900 monthly.", response, history),
            "numeric_positive_balance_reversal",
        )

    def test_rejects_calculation_without_a_total(self):
        response = "Your original food budget is \u20b11,500. The deficit is \u20b1500."
        self.assertEqual(
            self.engine._numeric_quality_retry_reason(
                "Actually, return to my original food budget and calculate everything again.",
                response,
                self.history,
            ),
            "numeric_expense_total",
        )

    def test_rejects_total_monthly_expenses_mismatch(self):
        response = "This brings your total monthly expenses to \u20b14,000, leaving a \u20b1500 deficit."
        self.assertEqual(
            self.engine._numeric_quality_retry_reason(
                "My weekly income is \u20b11,000. Rent is \u20b11,000, utilities are \u20b11,000, transportation is \u20b150 for 20 workdays, and food is \u20b11,500 monthly.",
                response,
            ),
            "numeric_expense_total",
        )

    def test_rejects_summary_that_omits_intermediate_hypothetical_values(self):
        history = [
            {"user": "My weekly income is \u20b11,000. Rent is \u20b11,000, utilities are \u20b11,000, transportation is \u20b150 for 20 workdays, and food is \u20b11,500 monthly.", "ai": "synthetic"},
            {"user": "What if I reduce my food expenses by \u20b1200 monthly?", "ai": "synthetic"},
            {"user": "Now increase that food budget by \u20b1100.", "ai": "synthetic"},
            {"user": "Change my food budget to \u20b1900 monthly.", "ai": "synthetic"},
            {"user": "Actually, return to my original food budget and calculate everything again.", "ai": "synthetic"},
        ]
        response = "Actual food is \u20b11,500 and the total is \u20b14,500 with a \u20b1500 deficit."
        self.assertEqual(
            self.engine._quality_retry_reason(
                "Can you summarize my actual budget and explain which changes were only hypothetical?",
                response,
                False,
                history,
            ),
            "financial_summary_incomplete",
        )

    def test_accepts_complete_hypothetical_summary(self):
        history = [
            {"user": "My weekly income is \u20b11,000. Rent is \u20b11,000, utilities are \u20b11,000, transportation is \u20b150 for 20 workdays, and food is \u20b11,500 monthly.", "ai": "synthetic"},
            {"user": "What if I reduce my food expenses by \u20b1200 monthly?", "ai": "synthetic"},
            {"user": "Now increase that food budget by \u20b1100.", "ai": "synthetic"},
            {"user": "Change my food budget to \u20b1900 monthly.", "ai": "synthetic"},
            {"user": "Actually, return to my original food budget and calculate everything again.", "ai": "synthetic"},
        ]
        response = "Actual food is \u20b11,500 and total is \u20b14,500. Hypothetical food scenarios were \u20b11,500 to \u20b11,300, then \u20b11,300 to \u20b11,400, then \u20b11,400 to \u20b1900."
        self.assertIsNone(
            self.engine._quality_retry_reason(
                "Can you summarize my actual budget and explain which changes were only hypothetical?",
                response,
                False,
                history,
            )
        )

    def test_rejects_unrelated_emotional_question_during_financial_continuation(self):
        history = [{"user": "I feel overwhelmed and sad about school.", "ai": "What are you feeling right now?"}]
        response = "I want to make sure I understand. Can you tell me what you are feeling right now?"
        self.assertEqual(
            self.engine._quality_retry_reason(
                "Actually, return to my original food budget and calculate everything again.",
                response,
                False,
                history,
            ),
            "financial_context_override",
        )


if __name__ == "__main__":
    unittest.main()
