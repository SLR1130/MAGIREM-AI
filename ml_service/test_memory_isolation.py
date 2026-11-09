"""Focused tests for explicit Flask memory-database isolation."""

import os
from pathlib import Path

from memory_config import resolve_memory_db_path


def test_explicit_absolute_path_is_selected():
    test_root = Path(__file__).resolve().parents[1] / "storage" / "app" / "phase3b-runtime"
    test_root.mkdir(parents=True, exist_ok=True)
    target = test_root / "memory-isolation-unit.db"
    old = os.environ.get("MINDCARE_MEMORY_DB_PATH")
    try:
        os.environ["MINDCARE_MEMORY_DB_PATH"] = str(target)
        assert resolve_memory_db_path() == target.resolve()
    finally:
        if old is None:
            os.environ.pop("MINDCARE_MEMORY_DB_PATH", None)
        else:
            os.environ["MINDCARE_MEMORY_DB_PATH"] = old


def test_relative_override_fails_closed():
    old = os.environ.get("MINDCARE_MEMORY_DB_PATH")
    try:
        os.environ["MINDCARE_MEMORY_DB_PATH"] = "relative-memory.db"
        try:
            resolve_memory_db_path()
        except ValueError:
            pass
        else:
            raise AssertionError("relative memory paths must be rejected")
    finally:
        if old is None:
            os.environ.pop("MINDCARE_MEMORY_DB_PATH", None)
        else:
            os.environ["MINDCARE_MEMORY_DB_PATH"] = old


def test_unconfigured_path_preserves_legacy_location():
    old = os.environ.pop("MINDCARE_MEMORY_DB_PATH", None)
    try:
        assert resolve_memory_db_path() == (Path.cwd() / "memory_store.db").resolve()
    finally:
        if old is not None:
            os.environ["MINDCARE_MEMORY_DB_PATH"] = old
