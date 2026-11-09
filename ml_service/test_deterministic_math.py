import unittest
from decimal import Decimal

from ml_service.deterministic_math import calculate_budget_context, food_budget_caution_response, numeric_context_for_prompt, numeric_fallback_response


class DeterministicMathTests(unittest.TestCase):
    def test_weekly_four_week_and_annualized_values(self):
        facts = calculate_budget_context("weekly allowance \u20b11,000 with four-week budgeting")
        self.assertEqual(facts["monthly_income"], Decimal("4000.00"))
        self.assertEqual(facts["annualized_monthly_income"].quantize(Decimal("0.01")), Decimal("4333.33"))

    def test_known_expenses_and_unknown_food(self):
        facts = calculate_budget_context("rent \u20b11,000, utilities \u20b11,000, transportation \u20b150 x 20 days, food is unknown")
        self.assertEqual(facts["known_expenses_total"], Decimal("3000.00"))
        self.assertTrue(facts["unknown_food"])

    def test_expenses_can_exceed_income(self):
        facts = calculate_budget_context("monthly income \u20b12,000, rent \u20b11,500, utilities \u20b1800")
        self.assertEqual(facts["balance_before_unknown"], Decimal("-300.00"))

    def test_follow_up_revises_food(self):
        history = [{"user": "weekly allowance \u20b11,000, rent \u20b11,000, utilities \u20b11,000, transportation \u20b150 x 20 days; food unknown"}]
        facts = calculate_budget_context("What if food costs \u20b11,500?", history)
        self.assertEqual(facts["known_expenses_total"], Decimal("4500.00"))
        self.assertEqual(facts["balance_before_unknown"], Decimal("-500.00"))

    def test_hypothetical_food_range_is_not_income_or_expense(self):
        message = "Is PHP 800 to PHP 1,200 a realistic monthly food budget for a student?"
        self.assertIsNone(calculate_budget_context(message))
        caution = food_budget_caution_response(message)
        self.assertIn("very restrictive", caution)
        self.assertIn("do not skip meals", caution)
        self.assertEqual(numeric_fallback_response(message), caution)

    def test_foreign_currency_and_simple_math_are_explicit(self):
        facts = calculate_budget_context("I earn $100 weekly and spend $20 rent")
        self.assertEqual(facts["currency"], "USD")
        self.assertIn("162.00", numeric_context_for_prompt("What is 125 + 37?"))
        self.assertIn("85.00", numeric_context_for_prompt("My grades are 80, 90, and 85"))
        self.assertIn("200.00", numeric_context_for_prompt("What is 20% of PHP 1,000?"))

    def test_complete_calculation_does_not_trigger_food_range_fallback(self):
        message = ("My income is \u20b11,000 weekly. Rent is \u20b11,000 monthly, utilities are \u20b11,000 monthly, "
                   "transportation is \u20b150 for 20 workdays, and food is \u20b11,500 monthly. "
                   "Using four weeks, calculate my expenses and remaining balance.")
        facts = calculate_budget_context(message)
        self.assertEqual(facts["monthly_income"], Decimal("4000.00"))
        self.assertEqual(facts["known_expenses_total"], Decimal("4500.00"))
        self.assertEqual(facts["balance_before_unknown"], Decimal("-500.00"))
        fallback = numeric_fallback_response(message)
        self.assertIn("total \u20b14,500", fallback)
        self.assertIn("-\u20b1500", fallback)
        self.assertNotIn("very restrictive", fallback)

    def test_relative_and_absolute_food_changes_preserve_original(self):
        history = []
        expected = ["1500.00", "1300.00", "1400.00", "900.00", "1500.00", "1300.00", "1500.00"]
        messages = [
            "My food expense is \u20b11,500 monthly.",
            "Reduce it by \u20b1200.",
            "Increase it by \u20b1100.",
            "Change food to \u20b1900.",
            "Actually, use my original food amount.",
            "What if I reduced food spending by \u20b1200?",
            "Go back to my actual budget.",
        ]
        for message, amount in zip(messages, expected):
            facts = calculate_budget_context(message, history)
            self.assertEqual(str(facts["expenses"][0][1]), amount, message)
            history.append({"user": message, "ai": "synthetic"})

    def test_transport_relative_change_uses_stated_daily_multiplier(self):
        history = [{"user": "My income is \u20b11,000 weekly, transportation is \u20b150 for 20 workdays, food is \u20b11,500 monthly.", "ai": "synthetic"}]
        facts = calculate_budget_context("What if I reduce transportation by \u20b110 per workday?", history)
        transport = dict((label, amount) for label, amount, _ in facts["expenses"])["transportation"]
        self.assertEqual(transport, Decimal("800.00"))
        self.assertEqual(facts["known_expenses_total"], Decimal("2300.00"))

    def test_arithmetic_and_food_adequacy_are_both_preserved(self):
        message = ("My income is \u20b14,000 monthly and food is \u20b11,500 monthly. "
                   "Calculate my budget, then explain whether the food amount is adequate.")
        fallback = numeric_fallback_response(message)
        self.assertIn("total \u20b11,500", fallback)
        self.assertIn("Food adequacy cannot be verified", fallback)

    def test_chained_hypothetical_adjustments_continue_from_working_value(self):
        messages = [
            "My weekly income is \u20b11,000. My monthly rent is \u20b11,000, utilities are \u20b11,000, transportation is \u20b150 for 20 workdays, and food costs \u20b11,500 per month.",
            "What if I reduce my food expenses by \u20b1200 monthly?",
            "Now increase that food budget by \u20b1100.",
            "Change my food budget to \u20b1900 monthly.",
            "Actually, return to my original food budget and calculate everything again.",
            "Can you summarize my actual budget and explain which changes were only hypothetical?",
        ]
        expected = [("1500.00", "4500.00", "-500.00"), ("1300.00", "4300.00", "-300.00"),
                    ("1400.00", "4400.00", "-400.00"), ("900.00", "3900.00", "100.00"),
                    ("1500.00", "4500.00", "-500.00"), ("1500.00", "4500.00", "-500.00")]
        history = []
        for message, (food, total, balance) in zip(messages, expected):
            facts = calculate_budget_context(message, history)
            amounts = dict((label, amount) for label, amount, _ in facts["expenses"])
            self.assertEqual(str(amounts["food"]), food, message)
            self.assertEqual(str(facts["known_expenses_total"]), total, message)
            self.assertEqual(str(facts["balance_before_unknown"]), balance, message)
            history.append({"user": message, "ai": "Can you tell me what you are feeling?"})
        self.assertEqual(len(facts["hypothetical_changes"]), 3)
        self.assertIn("food: 1300.00 -> 1400.00", facts["hypothetical_changes"])

    def test_summary_retains_all_hypothetical_endpoints_and_fallback_separates_actual(self):
        history = []
        messages = [
            "My weekly income is \u20b11,000. My monthly rent is \u20b11,000, utilities are \u20b11,000, transportation is \u20b150 for 20 workdays, and food costs \u20b11,500 per month.",
            "What if I reduce my food expenses by \u20b1200 monthly?",
            "Now increase that food budget by \u20b1100.",
            "Change my food budget to \u20b1900 monthly.",
            "Actually, return to my original food budget and calculate everything again.",
        ]
        for message in messages:
            facts = calculate_budget_context(message, history)
            history.append({"user": message, "ai": "synthetic"})
        self.assertEqual(
            [(item.get("from"), item.get("to")) for item in facts["hypothetical_scenarios"]],
            [(Decimal("1500.00"), Decimal("1300.00")), (Decimal("1300.00"), Decimal("1400.00")), (Decimal("1400.00"), Decimal("900.00"))],
        )
        summary = numeric_fallback_response(
            "Can you summarize my actual budget and explain which changes were only hypothetical?",
            history,
        )
        for amount in ("\u20b11,300", "\u20b11,400", "\u20b1900"):
            self.assertIn(amount, summary)
        self.assertIn("Actual budget (confirmed)", summary)
        self.assertIn("not confirmed actual expenses", summary)

    def test_assistant_numbers_never_become_authoritative_facts(self):
        history = [{"user": "My food expense is \u20b11,500 monthly.", "ai": "Your food expense is \u20b11,600."}]
        facts = calculate_budget_context("What is my actual food expense?", history)
        self.assertEqual(dict((label, amount) for label, amount, _ in facts["expenses"])["food"], Decimal("1500.00"))


if __name__ == "__main__":
    unittest.main()

