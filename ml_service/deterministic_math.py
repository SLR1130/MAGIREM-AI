"""Small, bounded numerical checks for conversational budgeting.

This module intentionally is not a general expression evaluator.  It extracts
only common, explicitly stated income and expense facts and uses ``Decimal``
for arithmetic before the values are supplied to the language model as
authoritative context.  Unknown expenses remain unknown rather than being
silently treated as zero.
"""

from __future__ import annotations

from decimal import Decimal, InvalidOperation, ROUND_HALF_UP
import re
from typing import Dict, Iterable, List, Optional, Tuple


_NUMBER = r"(?:\d{1,3}(?:,\d{3})+|\d+(?:\.\d+)?)"
_CURRENCY = r"(?:₱|PHP|PESO(?:S)?|\$|USD|US\s*DOLLAR(?:S)?|€|EUR|£|GBP)"
_MONEY_RE = re.compile(rf"(?P<currency>{_CURRENCY})?\s*(?P<amount>{_NUMBER})", re.IGNORECASE)
_LABELS = {
    "rent": r"rent|boarding|dorm|housing",
    "utilities": r"utilities?|electric(?:ity)?|water|internet|wi[- ]?fi",
    "food": r"food|meal(?:s)?|grocer(?:y|ies)",
    "transportation": r"transport(?:ation)?|commute|fare|jeepney|bus|train|tricycle",
    "tuition": r"tuition|school\s+fees?",
    "phone": r"phone|mobile|load",
}
_CURRENCY_SYMBOLS = {
    "₱": "PHP", "php": "PHP", "peso": "PHP", "pesos": "PHP",
    "$": "USD", "usd": "USD", "us dollar": "USD", "us dollars": "USD",
    "€": "EUR", "eur": "EUR", "£": "GBP", "gbp": "GBP",
}


def _money(value: str) -> Optional[Decimal]:
    try:
        return Decimal(value.replace(",", "")).quantize(Decimal("0.01"), rounding=ROUND_HALF_UP)
    except (InvalidOperation, AttributeError):
        return None


def _currency(token: Optional[str], default: str = "PHP") -> str:
    if not token:
        return default
    return _CURRENCY_SYMBOLS.get(token.strip().lower(), token.strip().upper())


def _fmt(value: Decimal, currency: str) -> str:
    value = value.quantize(Decimal("0.01"), rounding=ROUND_HALF_UP)
    rendered = f"{value:,.2f}".rstrip("0").rstrip(".")
    return f"₱{rendered}" if currency == "PHP" else f"{currency} {rendered}"


def _user_turns(message: str, history: Optional[Iterable[Dict]]) -> List[str]:
    """Return bounded user-authored turns only.

    Model replies are deliberately excluded from financial extraction.  A
    reply can contain examples or caveats that must never become verified
    income/expense facts on a later turn.
    """
    turns: List[str] = []
    for exchange in history or []:
        if isinstance(exchange, dict):
            value = exchange.get("user") or exchange.get("user_message") or exchange.get("message")
            if value:
                turns.append(str(value))
        elif exchange:
            turns.append(str(exchange))
    turns.append(str(message or ""))
    return turns[-12:]


def _all_text(message: str, history: Optional[Iterable[Dict]]) -> str:
    # Keep extraction bounded; recent user turns are the useful facts and this
    # prevents a large transcript from bloating the Ollama prompt.
    return "\n".join(_user_turns(message, history))[-12000:]


def _money_mentions(text: str) -> List[Tuple[Decimal, str, int, int]]:
    values: List[Tuple[Decimal, str, int, int]] = []
    for match in _MONEY_RE.finditer(text):
        value = _money(match.group("amount"))
        if value is not None:
            values.append((value, _currency(match.group("currency")), match.start(), match.end()))
    return values


def _period_income(text: str, currency: str) -> List[Tuple[Decimal, str, str]]:
    results: List[Tuple[Decimal, str, str]] = []
    period_words = {
        "weekly": r"weekly|per\s+week|each\s+week|a\s+week",
        "monthly": r"monthly|per\s+month|each\s+month|a\s+month",
        "daily": r"daily|per\s+day|each\s+day|a\s+day",
    }
    for period, word_pattern in period_words.items():
        patterns = (
            rf"(?:{word_pattern})[^\n,.;]{{0,35}}?(?:{_CURRENCY}\s*)?({_NUMBER})",
            rf"(?:{_CURRENCY}\s*)?({_NUMBER})[^\n,.;]{{0,20}}?(?:{word_pattern})",
        )
        for pattern in patterns:
            for match in re.finditer(pattern, text, re.IGNORECASE):
                value = _money(match.group(1))
                if value is None:
                    continue
                if period == "monthly" and re.search(r"monthly\s+(?:budget(?:ing)?|average)", match.group(0), re.IGNORECASE):
                    continue
                # A period next to an expense (``food 1500 monthly``) is
                # not income. Require an income/allowance cue nearby.
                context_start = max(
                    text.rfind("\n", 0, match.start()),
                    text.rfind(",", 0, match.start()),
                    text.rfind(".", 0, match.start()),
                    text.rfind(";", 0, match.start()),
                ) + 1
                context_end_candidates = [
                    pos for pos in (text.find("\n", match.end()), text.find(",", match.end()), text.find(".", match.end()), text.find(";", match.end()))
                    if pos >= 0
                ]
                context_end = min(context_end_candidates) if context_end_candidates else len(text)
                if not re.search(
                    r"\b(?:income|allowance|salary|stipend|earn(?:s|ed|ing)?|get|gets|receive(?:s|d)?|make|makes)\b",
                    text[context_start:context_end], re.IGNORECASE,
                ):
                    continue
                amounts = _money_mentions(match.group(0))
                found_currency = amounts[-1][1] if amounts else currency
                results.append((value, period, found_currency or currency))
    return results

def _labelled_expenses(text: str, default_currency: str) -> Dict[str, Dict[str, object]]:
    expenses: Dict[str, Dict[str, object]] = {}
    category_pattern = "|".join(_LABELS.values())
    for label, label_pattern in _LABELS.items():
        label_matches = list(re.finditer(rf"\b(?:{label_pattern})\b", text, re.IGNORECASE))
        for label_match in label_matches:
            around_after = text[label_match.end(): min(len(text), label_match.end() + 60)]
            after_sentence = re.split(r"[\n.;]", around_after, maxsplit=1)[0]
            after_amounts = _money_mentions(after_sentence)
            candidate = after_amounts[0] if after_amounts else None
            if candidate is None:
                before_start = max(0, label_match.start() - 45)
                around_before = text[before_start:label_match.start()]
                before_amounts = _money_mentions(around_before)
                for amount in reversed(before_amounts):
                    between = text[before_start + amount[3]:label_match.start()]
                    before_candidate = around_before[:amount[2]]
                    if (not re.search(rf"\b(?:{category_pattern})\b", between, re.IGNORECASE)
                            and not re.search(rf"\b(?:{category_pattern})\b", before_candidate, re.IGNORECASE)):
                        candidate = amount
                        break
            if candidate is None:
                if label == "food" and re.search(r"(?:unknown|unspecified|not\s+sure|don't\s+know)", around_after, re.IGNORECASE):
                    expenses.pop(label, None)
                continue
            value, found_currency, _, _ = candidate
            multiplier = None
            if label == "transportation":
                window = text[max(0, label_match.start() - 10): min(len(text), label_match.end() + 100)]
                mult_match = re.search(r"(?:x|\*)\s*(\d{1,3})\s*(?:days?|workdays?)?", window.replace(chr(0xD7), "x"), re.IGNORECASE)
                if not mult_match:
                    mult_match = re.search(r"(\d{1,3})\s*(?:days?|workdays?)", window, re.IGNORECASE)
                if mult_match:
                    multiplier = int(mult_match.group(1))
            expenses[label] = {"amount": value, "currency": found_currency or default_currency, "multiplier": multiplier}
    return expenses


def _is_relative_expense_turn(text: str) -> bool:
    return bool(re.search(
        r"\b(?:reduce\w*|decrease\w*|lower\w*|cut\w*|increase\w*|raise\w*|add\w*|"
        r"spend\s+(?:less|more)|save)\b", str(text or ""), re.IGNORECASE
    ))


def _is_hypothetical_turn(text: str) -> bool:
    text = str(text or "")
    if re.search(r"\b(?:what\s+if|if\s+i\b|suppose|scenario|would\s+it)\b", text, re.IGNORECASE):
        return True
    # A summary request may mention the word "hypothetical" without proposing
    # a scenario. Treat it as hypothetical only when it also contains a
    # financial amount or an adjustment operation.
    return bool(
        re.search(r"\bhypothetical\b", text, re.IGNORECASE)
        and (_money_mentions(text) or _is_relative_expense_turn(text))
    )


def _label_in_text(text: str) -> Optional[str]:
    for label, pattern in _LABELS.items():
        if re.search(rf"\b(?:{pattern})\b", str(text or ""), re.IGNORECASE):
            return label
    return None


def _explicit_turn_expenses(text: str, default_currency: str) -> Dict[str, Dict[str, object]]:
    """Extract absolute values while ignoring relative adjustment amounts."""
    expenses = _labelled_expenses(text, default_currency)
    if not _is_relative_expense_turn(text):
        return expenses
    # A relative turn such as ``reduce food by PHP 200`` must not be read as
    # setting food to PHP 200.  Explicit ``change/set ... to`` remains an
    # absolute replacement and is retained.
    for label, pattern in _LABELS.items():
        if not re.search(rf"\b(?:{pattern})\b", text, re.IGNORECASE):
            continue
        absolute = re.search(
            rf"\b(?:set|change|replace|make)\b[^.?!,;]{{0,35}}\b(?:{pattern})\b[^.?!,;]{{0,25}}\bto\b",
            text, re.IGNORECASE,
        )
        if not absolute:
            expenses.pop(label, None)
    return expenses


def _relative_expense_change(text: str, last_label: Optional[str]) -> Optional[Tuple[str, Decimal, int, bool]]:
    """Return (label, unit adjustment, direction, hypothetical) if present."""
    text = str(text or "")
    if not _is_relative_expense_turn(text):
        return None
    label = _label_in_text(text) or (last_label if re.search(r"\b(?:it|that|this)\b", text, re.IGNORECASE) else None)
    if not label:
        return None
    if not re.search(r"\b(?:reduce\w*|decrease\w*|lower\w*|cut\w*|spend\s+less|save)\b", text, re.IGNORECASE) and not re.search(r"\b(?:increase\w*|raise\w*|add\w*|spend\s+more)\b", text, re.IGNORECASE):
        return None
    mentions = _money_mentions(text)
    if not mentions:
        return None
    amount = mentions[0][0]
    direction = -1 if re.search(r"\b(?:reduce\w*|decrease\w*|lower\w*|cut\w*|spend\s+less|save)\b", text, re.IGNORECASE) else 1
    return label, amount, direction, _is_hypothetical_turn(text)


def _restore_requested(text: str) -> bool:
    return bool(re.search(
        r"\b(?:go\s+back|return)\b[^.?!]{0,40}\b(?:actual|original)\b|"
        r"\b(?:use|restore)\b[^.?!]{0,40}\b(?:actual|original)\b",
        str(text or ""), re.IGNORECASE
    ))


def _summary_requested(text: str) -> bool:
    """Recognise an explicit request to separate actual and hypothetical values."""
    return bool(re.search(
        r"\b(?:summari[sz]e|summary|which\s+changes?|actual\s+budget|"
        r"hypothetical\s+(?:changes?|adjustments?|scenarios?))\b",
        str(text or ""),
        re.IGNORECASE,
    ))


def _copy_expenses(expenses: Dict[str, Dict[str, object]]) -> Dict[str, Dict[str, object]]:
    return {key: dict(value) for key, value in expenses.items()}


def calculate_budget_context(message: str, history: Optional[Iterable[Dict]] = None) -> Optional[Dict]:
    """Return bounded, user-authored budget facts with scenario provenance.

    ``original_expenses`` is immutable provenance. ``actual`` contains
    confirmed values, while ``working`` contains the current scenario. A
    hypothetical adjustment is not committed to ``actual``, but chained
    follow-ups continue from the active hypothetical ``working`` values until
    the student restores or confirms an actual budget.
    """
    turns = _user_turns(message, history)
    text = "\n".join(turns)
    lower = text.lower()
    if not re.search(r"\b(?:budget|allowance|income|salary|expenses?|rent|utilities?|transport|food|savings?|balance|money)\b", lower):
        return None
    # A standalone adequacy/range question is guidance, not a verified
    # income or expense fact. Keep it on the nutrition-safety path.
    if food_budget_caution_response(str(message or "")):
        return None

    actual: Dict[str, Dict[str, object]] = {}
    working: Dict[str, Dict[str, object]] = {}
    original: Dict[str, Dict[str, object]] = {}
    income_value: Optional[Decimal] = None
    income_period: Optional[str] = None
    income_currency = "PHP"
    last_label: Optional[str] = None
    scenario_active = False
    current_hypothetical = False
    hypothetical_changes: List[str] = []
    hypothetical_scenarios: List[Dict[str, object]] = []

    for turn in turns:
        turn_mentions = _money_mentions(turn)
        turn_currency = turn_mentions[0][1] if turn_mentions else income_currency
        turn_hypothetical = _is_hypothetical_turn(turn)
        turn_incomes = _period_income(turn, turn_currency)
        if turn_incomes and not turn_hypothetical:
            income_value, income_period, income_currency = turn_incomes[-1]

        explicit = _explicit_turn_expenses(turn, turn_currency)
        # An explicit first-person value is a confirmed fact unless it is part
        # of a conditional/scenario phrase. This lets students end a scenario
        # by stating a new actual value without losing the original snapshot.
        confirms_actual = bool(re.search(
            r"\b(?:my|the)\s+(?:monthly\s+)?(?:food|meal|rent|utilities?|transport(?:ation)?|phone|tuition)\b"
            r"[^.?!]{0,25}\b(?:is|are|costs?|equals?|=)\b",
            turn, re.IGNORECASE,
        )) and not turn_hypothetical
        target = working if (scenario_active or turn_hypothetical) and not confirms_actual else actual
        for label, data in explicit.items():
            previous = target.get(label, {}).get("amount") if isinstance(target.get(label), dict) else None
            target[label] = dict(data)
            if target is actual:
                working[label] = dict(data)
            original.setdefault(label, dict(data))
            if target is working and not confirms_actual and (scenario_active or turn_hypothetical):
                amount = data.get("amount")
                if isinstance(amount, Decimal):
                    hypothetical_changes.append(f"{label}: {previous if isinstance(previous, Decimal) else 'unknown'} -> {amount}")
                    hypothetical_scenarios.append({
                        "label": label,
                        "from": previous if isinstance(previous, Decimal) else None,
                        "to": amount,
                        "currency": str(data.get("currency") or turn_currency),
                    })
            last_label = label

        # A later ``food is unknown`` removes only the current view; the
        # original snapshot remains available for explicit restoration.
        if re.search(r"\bfood\b[^.?!]{0,35}\b(?:unknown|unspecified|not\s+sure|don't\s+know)\b", turn, re.IGNORECASE):
            target.pop("food", None)
            if target is actual:
                working.pop("food", None)
            last_label = "food"

        change = _relative_expense_change(turn, last_label)
        if change:
            label, adjustment, direction, change_hypothetical = change
            apply_hypothetical = scenario_active or turn_hypothetical or change_hypothetical
            target = working if apply_hypothetical and not confirms_actual else actual
            if label in target:
                candidate = dict(target[label])
                unit = candidate.get("amount")
                if isinstance(unit, Decimal):
                    candidate["amount"] = unit + (adjustment * direction)
                    target[label] = candidate
                    if target is actual:
                        working[label] = dict(candidate)
                    elif not confirms_actual and apply_hypothetical:
                        hypothetical_changes.append(f"{label}: {unit} -> {candidate['amount']}")
                        hypothetical_scenarios.append({
                            "label": label,
                            "from": unit,
                            "to": candidate["amount"],
                            "currency": str(candidate.get("currency") or turn_currency),
                        })
                    last_label = label
            if apply_hypothetical and not confirms_actual:
                scenario_active = True

        if turn_hypothetical and not confirms_actual:
            scenario_active = True
        elif confirms_actual:
            # A direct stated amount is a confirmed update, not merely a
            # continuation of the previous hypothetical scenario.
            scenario_active = False
            working = _copy_expenses(actual)

        # Restore requests always win and return the working and actual views
        # to the immutable original values.
        if _restore_requested(turn):
            target_label = _label_in_text(turn)
            if target_label and target_label in original:
                actual[target_label] = dict(original[target_label])
                working[target_label] = dict(original[target_label])
                last_label = target_label
            elif not target_label:
                actual = _copy_expenses(original)
                working = _copy_expenses(original)
            scenario_active = False

        current_hypothetical = scenario_active

    current = working if scenario_active else actual
    if not income_value and not current:
        return None

    currency = income_currency or next((str(item.get("currency") or "PHP") for item in current.values()), "PHP")
    income_monthly: Optional[Decimal] = None
    annualized: Optional[Decimal] = None
    assumptions: List[str] = []
    if income_value is not None and income_period:
        if income_period == "weekly":
            income_monthly = income_value * Decimal("4")
            assumptions.append("monthly baseline uses 4 weeks")
            annualized = income_value * Decimal("52") / Decimal("12")
        elif income_period == "daily":
            income_monthly = income_value * Decimal("20")
            assumptions.append("monthly baseline uses 20 days")
            annualized = income_monthly
        else:
            income_monthly = income_value
            annualized = income_value

    expense_lines: List[Tuple[str, Decimal, str]] = []
    for label, data in current.items():
        amount = data.get("amount")
        if not isinstance(amount, Decimal):
            continue
        multiplier = data.get("multiplier")
        if multiplier and label == "transportation":
            amount = amount * Decimal(int(multiplier))
            assumptions.append(f"transportation uses {multiplier} stated days")
        expense_lines.append((label, amount, str(data.get("currency") or currency)))
    known_total = sum((amount for _, amount, _ in expense_lines), Decimal("0"))
    balance = income_monthly - known_total if income_monthly is not None else None
    unknown = "food" not in current and bool(re.search(r"\b(?:budget|expenses?|allowance|income|money|food)\b", lower))
    return {
        "currency": currency,
        "income_period": income_period,
        "income_value": income_value,
        "monthly_income": income_monthly,
        "annualized_monthly_income": annualized,
        "expenses": expense_lines,
        "known_expenses_total": known_total,
        "balance_before_unknown": balance,
        "unknown_food": unknown,
        "assumptions": assumptions,
        "hypothetical": current_hypothetical,
        "original_expenses": [(label, data.get("amount"), str(data.get("currency") or currency), data.get("multiplier")) for label, data in original.items()],
        "actual_expenses": [(label, data.get("amount"), str(data.get("currency") or currency), data.get("multiplier")) for label, data in actual.items()],
        "hypothetical_changes": list(hypothetical_changes),
        "hypothetical_scenarios": list(hypothetical_scenarios),
    }

def budget_context_for_prompt(message: str, history: Optional[Iterable[Dict]] = None) -> str:
    facts = calculate_budget_context(message, history)
    if not facts:
        return ""
    currency = facts["currency"]
    lines = [
        "DETERMINISTIC NUMERICAL CHECK (authoritative arithmetic; do not contradict these values):",
        f"Currency: {currency}.",
    ]
    if facts["income_value"] is not None:
        lines.append(f"Stated {facts['income_period']} income: {_fmt(facts['income_value'], currency)}.")
    if facts["monthly_income"] is not None:
        lines.append(f"Monthly baseline: {_fmt(facts['monthly_income'], currency)} (" + "; ".join(facts["assumptions"][:1]) + ").")
        if facts["annualized_monthly_income"] is not None and facts["income_period"] == "weekly":
            lines.append(f"Annualized monthly average: {_fmt(facts['annualized_monthly_income'], currency)} (52 weeks ÷ 12 months; an estimate, not the 4-week baseline).")
    if facts["expenses"]:
        lines.append("Known monthly expenses: " + ", ".join(f"{label} {_fmt(amount, cur)}" for label, amount, cur in facts["expenses"]) + ".")
        lines.append(f"Known expense total: {_fmt(facts['known_expenses_total'], currency)}.")
    if facts.get("hypothetical_changes"):
        scenario_lines = []
        for scenario in facts.get("hypothetical_scenarios", []):
            label = str(scenario.get("label") or "expense")
            before = scenario.get("from")
            after = scenario.get("to")
            cur = str(scenario.get("currency") or currency)
            before_text = _fmt(before, cur) if isinstance(before, Decimal) else "unknown"
            after_text = _fmt(after, cur) if isinstance(after, Decimal) else "unknown"
            scenario_lines.append(f"{label} {before_text} -> {after_text}")
        if scenario_lines:
            lines.append("Hypothetical changes (not confirmed actual values): " + "; ".join(scenario_lines) + ".")
        else:
            lines.append("Hypothetical changes (not confirmed actual values): " + "; ".join(str(item) for item in facts["hypothetical_changes"]) + ".")
    if facts["unknown_food"]:
        lines.append("Food is not stated; do not call the balance savings or disposable income until food and other missing essentials are estimated.")
    if facts["balance_before_unknown"] is not None:
        balance = facts["balance_before_unknown"]
        if balance < 0:
            lines.append(f"Known-expense balance: {_fmt(balance.copy_abs(), currency)} deficit before missing expenses.")
        else:
            lines.append(f"Balance after known expenses, before missing expenses: {_fmt(balance, currency)}; this is not savings.")
    if facts["assumptions"]:
        lines.append("Assumptions: " + "; ".join(dict.fromkeys(facts["assumptions"])) + ".")
    lines.append("State what is known versus assumed, and show the arithmetic briefly when giving a budget recommendation. Do not repeat monthly expenses in every week; only provide a weekly breakdown when explicitly requested and label any allocation assumption.")
    return "\n".join(lines)



def _simple_numeric_context(message: str) -> str:
    text = str(message or "")
    marker = re.search(r"\b(?:average|mean|grade|grades|scores?|marks?)\b", text, re.IGNORECASE)
    if marker:
        tail = re.split(r"[.!?]", text[marker.end(): marker.end() + 120], maxsplit=1)[0]
        values = [Decimal(item) for item in re.findall(r"\d+(?:\.\d+)?", tail)]
        if len(values) >= 2:
            values = values[:10]
            average = (sum(values, Decimal("0")) / Decimal(len(values))).quantize(Decimal("0.01"), rounding=ROUND_HALF_UP)
            return ("DETERMINISTIC NUMERICAL CHECK (authoritative arithmetic): "
                    f"the stated values are {', '.join(str(v) for v in values)}; "
                    f"their arithmetic mean is {average}. Show the calculation and label any grading scale assumption.")
    percentage = re.search(r"(\d+(?:\.\d+)?)\s*%\s*(?:of|from)\s*(?:\u20b1|PHP|USD|EUR|GBP|\$)?\s*([0-9][0-9,]*(?:\.[0-9]+)?)", text, re.IGNORECASE)
    if percentage:
        rate = Decimal(percentage.group(1))
        base = _money(percentage.group(2))
        if base is not None:
            result = (rate / Decimal("100")) * base
            return ("DETERMINISTIC NUMERICAL CHECK (authoritative arithmetic): "
                    f"{rate}% of {base} = {result.quantize(Decimal('0.01'), rounding=ROUND_HALF_UP)}. Label this as an allocation, not actual spending.")
    expression = re.search(r"(?:what is|calculate|solve|equals?)\s+(\d+(?:\.\d+)?)\s*([+\-*/])\s*(\d+(?:\.\d+)?)", text, re.IGNORECASE)
    if expression:
        left, operator, right = Decimal(expression.group(1)), expression.group(2), Decimal(expression.group(3))
        if operator == "+": result = left + right
        elif operator == "-": result = left - right
        elif operator == "*": result = left * right
        elif operator == "/" and right != 0: result = left / right
        else: return ""
        return ("DETERMINISTIC NUMERICAL CHECK (authoritative arithmetic): "
                f"{left} {operator} {right} = {result.quantize(Decimal('0.01'), rounding=ROUND_HALF_UP)}. Do not change this result.")
    return ""


def _food_adequacy_request(text: str) -> bool:
    return bool(re.search(
        r"\b(?:realistic|sufficient|adequate|enough|affordable|can\s+i\s+(?:live|eat)|"
        r"will\s+it\s+cover|is\s+it\s+enough|whether|how\s+much\s+should)\b",
        str(text or ""), re.IGNORECASE
    ))


def _food_budget_context(message: str) -> str:
    """Return caution only for a hypothetical food-budget question.

    A complete arithmetic request can mention several monthly amounts and food;
    it must receive arithmetic first rather than the separate low-food-range
    safety fallback.
    """
    text = str(message or '')
    lower = text.lower()
    if not re.search(r"\b(?:monthly|per\s+month|a\s+month)\b", lower):
        return ""
    if not re.search(r"\b(?:food|meal|grocer(?:y|ies))\b", lower):
        return ""
    mentions = _money_mentions(text)
    if len(mentions) < 2:
        return ""
    has_stated_food = bool(re.search(
        r"\b(?:my\s+)?(?:food|meal|grocer(?:y|ies))(?:\s+expense|\s+spending|\s+budget)?\s*"
        r"(?:is|are|costs?|=|:)|\bfood\s+is\b", lower
    ))
    calculation_request = bool(re.search(
        r"\b(?:calculate|calculation|compute|total|expenses?|remaining|balance|deficit|shortfall|"
        r"using\s+(?:four|4)\s+weeks?|summari[sz]e)\b", lower
    ))
    # Explicit facts in a calculation are not a hypothetical range.  A direct
    # adequacy question with no arithmetic request remains eligible for the
    # cautious path.
    if has_stated_food and calculation_request:
        return ""
    if not _food_adequacy_request(lower) and not re.search(r"\b(?:\d[^\n]{0,12}(?:to|-)[^\n]{0,12}\d)\b", lower):
        return ""
    values = [item[0] for item in mentions[:4]]
    currency = mentions[0][1]
    rendered = " to ".join(_fmt(value, currency) for value in values[:2])
    return (
        "FOOD-BUDGET SAFETY CHECK (authoritative caution): the student is asking about a hypothetical monthly food-budget range "
        f"({rendered}), not stating their income or current food spending. Do not invent either. "
        "Do not call this range sufficient without the number of meals, time period, location, and existing food access. "
        "Prioritize adequate meals and essential needs; never recommend skipping meals. Explain that a very low range may be restrictive and label any example as hypothetical."
    )


def food_budget_caution_response(message: str) -> str:
    """Return a cautious response for a hypothetical low monthly food range."""
    guard = _food_budget_context(message)
    if not guard:
        return ""
    text = str(message or '')
    mentions = _money_mentions(text)
    if len(mentions) < 2:
        return ""
    currency = mentions[0][1]
    low, high = mentions[0][0], mentions[1][0]
    return (
        f"{_fmt(low, currency)} to {_fmt(high, currency)} per month may be very restrictive for a student's meals, "
        "and I cannot call it sufficient without knowing how many meals need to be covered, where food is bought, "
        "and what food is already available. Treat that range as hypothetical rather than a complete plan. "
        "A safer budget should first protect adequate meals and other essentials; do not skip meals to fit a target. "
        "Count the meals and days you need to cover, then compare the required amount with your actual available funds "
        "and look for verified support options if there is a shortfall."
    )

def numeric_context_for_prompt(message: str, history: Optional[Iterable[Dict]] = None) -> str:
    """Combine bounded budget and simple academic/arithmetic checks."""
    budget = budget_context_for_prompt(message, history)
    simple = _simple_numeric_context(message)
    food = _food_budget_context(message)
    return "\n\n".join(item for item in (budget, simple, food) if item)


def numeric_fallback_response(message: str, history: Optional[Iterable[Dict]] = None) -> str:
    """Provide a transparent arithmetic-only fallback after output validation.

    It is deliberately limited to values extracted by ``calculate_budget_context``
    and never invents food, savings, exchange rates, or personal expenses.
    """
    facts = calculate_budget_context(message, history)
    cautious_food = food_budget_caution_response(message)
    # A pure hypothetical range has no verified budget facts and can use the
    # dedicated nutrition-safety wording.  Complete calculations must proceed
    # through the arithmetic path below.
    if cautious_food and not facts:
        return cautious_food
    if facts:
        currency = str(facts["currency"])
        lines: List[str] = []
        # Summary fallbacks explicitly separate confirmed actual values from
        # every temporary scenario, so hypothetical amounts cannot be reported
        # as current spending when a model retry is incomplete.
        if _summary_requested(message) and facts.get("hypothetical_scenarios"):
            actual_items = facts.get("actual_expenses") or facts.get("original_expenses") or []
            actual_parts = []
            actual_total = Decimal("0")
            for label, amount, cur, multiplier in actual_items:
                if not isinstance(amount, Decimal):
                    continue
                effective = amount * Decimal(int(multiplier)) if multiplier and label == "transportation" else amount
                actual_total += effective
                actual_parts.append(f"{label} {_fmt(effective, str(cur or currency))}")
            lines.append("Actual budget (confirmed): " + ", ".join(actual_parts) + f"; total {_fmt(actual_total, currency)}.")
            if facts.get("monthly_income") is not None:
                actual_balance = facts["monthly_income"] - actual_total
                if actual_balance < 0:
                    lines.append(f"Against the four-week monthly baseline of {_fmt(facts['monthly_income'], currency)}, the confirmed budget has a deficit of -{_fmt(actual_balance.copy_abs(), currency)}.")
                else:
                    lines.append(f"Against the four-week monthly baseline of {_fmt(facts['monthly_income'], currency)}, the confirmed budget leaves {_fmt(actual_balance, currency)} before missing costs.")
            scenario_parts = []
            for scenario in facts.get("hypothetical_scenarios", []):
                label = str(scenario.get("label") or "expense")
                before = scenario.get("from")
                after = scenario.get("to")
                cur = str(scenario.get("currency") or currency)
                before_text = _fmt(before, cur) if isinstance(before, Decimal) else "unknown"
                after_text = _fmt(after, cur) if isinstance(after, Decimal) else "unknown"
                scenario_parts.append(f"{label} {before_text} -> {after_text}")
            if scenario_parts:
                lines.append("Hypothetical changes (not confirmed actual expenses): " + "; ".join(scenario_parts) + ".")
            return " ".join(lines)
        if facts["income_value"] is not None:
            lines.append(f"Stated {facts['income_period']} income: {_fmt(facts['income_value'], currency)}.")
        if facts["monthly_income"] is not None:
            lines.append(f"Using a four-week monthly baseline: {_fmt(facts['monthly_income'], currency)}.")
            if facts["income_period"] == "weekly":
                lines.append(f"The annualized monthly average is about {_fmt(facts['annualized_monthly_income'], currency)} (52 weeks divided by 12 months).")
        if facts["expenses"]:
            lines.append("Known expenses: " + ", ".join(f"{label} {_fmt(amount, cur)}" for label, amount, cur in facts["expenses"]) + f"; total {_fmt(facts['known_expenses_total'], currency)}.")
        if facts["unknown_food"]:
            lines.append("Food and other unlisted essentials are not included yet, so the amount remaining is not savings or disposable income.")
        if facts["balance_before_unknown"] is not None:
            balance = facts["balance_before_unknown"]
            if balance < 0:
                lines.append(f"Known expenses already exceed the monthly baseline by {_fmt(balance.copy_abs(), currency)}; the deficit is -{_fmt(balance.copy_abs(), currency)} before missing costs.")
            else:
                lines.append(f"Amount remaining after known expenses: {_fmt(balance, currency)}, before missing costs.")
        # When arithmetic and adequacy are requested together, retain the
        # verified totals and add only a cautious, non-price-specific note.
        if _food_adequacy_request(message) and re.search(r"\b(?:food|meal|grocer(?:y|ies))\b", str(message), re.IGNORECASE):
            lines.append("Food adequacy cannot be verified from the amount alone; consider meals, days covered, local prices, and existing food access, and do not skip meals to fit a target.")
        if lines:
            return " ".join(lines)
    if cautious_food:
        return cautious_food
    simple = _simple_numeric_context(message)
    if simple:
        return simple.split(": ", 1)[-1]
    return ""

