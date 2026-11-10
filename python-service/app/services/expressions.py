"""Safe row-filter expressions for the "custom_expression" cleaning step.

pandas' DataFrame.query()/eval() can run arbitrary code when given untrusted
input, so expressions are parsed and checked against a strict allow-list before
pandas ever sees them. Allowed: column names, literals, arithmetic, comparisons,
boolean logic and `in` lists, e.g.

    age >= 18 and country in ["CM", "NG"]
    `unit price` * qty > 100
"""

import ast
import re
from typing import Iterable

import pandas as pd

from app.errors import UnsafeExpressionError

MAX_EXPRESSION_LENGTH = 500

_ALLOWED_NODES = (
    ast.Expression,
    ast.BoolOp, ast.And, ast.Or,
    ast.BinOp, ast.Add, ast.Sub, ast.Mult, ast.Div, ast.Mod, ast.FloorDiv, ast.Pow,
    ast.BitAnd, ast.BitOr,
    ast.UnaryOp, ast.Not, ast.USub, ast.UAdd, ast.Invert,
    ast.Compare, ast.Eq, ast.NotEq, ast.Lt, ast.LtE, ast.Gt, ast.GtE, ast.In, ast.NotIn,
    ast.Name, ast.Load,
    ast.Constant,
    ast.List, ast.Tuple,
)

_BACKTICK_COLUMN = re.compile(r"`([^`]+)`")


def validate_filter_expression(expression: str, columns: Iterable[str]) -> None:
    """Raise UnsafeExpressionError unless the expression only uses allowed syntax
    and refers only to existing columns."""
    if not isinstance(expression, str) or not expression.strip():
        raise UnsafeExpressionError("Filter expression is empty.")

    if len(expression) > MAX_EXPRESSION_LENGTH:
        raise UnsafeExpressionError(f"Filter expression is longer than {MAX_EXPRESSION_LENGTH} characters.")

    column_names = {str(c) for c in columns}

    # pandas lets you write `column with spaces`; swap those for placeholder
    # identifiers so the expression can be parsed as Python.
    placeholders: dict[str, str] = {}

    def _substitute(match: re.Match) -> str:
        name = f"_bt_col_{len(placeholders)}"
        placeholders[name] = match.group(1)
        return name

    parseable = _BACKTICK_COLUMN.sub(_substitute, expression)

    try:
        tree = ast.parse(parseable, mode="eval")
    except SyntaxError as e:
        raise UnsafeExpressionError(f"Filter expression is not valid: {e.msg}.") from None

    for node in ast.walk(tree):
        if not isinstance(node, _ALLOWED_NODES):
            raise UnsafeExpressionError(
                f"'{type(node).__name__}' is not allowed in filter expressions. "
                "Use column names, values, comparisons, arithmetic and and/or/not."
            )

        if isinstance(node, ast.Constant) and not isinstance(node.value, (str, int, float, bool, type(None))):
            raise UnsafeExpressionError("Only text, number, boolean and null values are allowed.")

        if isinstance(node, ast.Name):
            column = placeholders.get(node.id, node.id)
            if column not in column_names:
                raise UnsafeExpressionError(f"Unknown column '{column}' in filter expression.")


def apply_filter_expression(df: pd.DataFrame, expression: str) -> pd.DataFrame:
    """Keep only the rows matching a validated filter expression."""
    validate_filter_expression(expression, df.columns)

    # Empty dicts stop pandas resolving any name that is not a column.
    return df.query(expression, local_dict={}, global_dict={})
