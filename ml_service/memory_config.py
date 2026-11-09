"""Configuration helpers for the Flask long-term memory store."""

import os
from pathlib import Path

MEMORY_DB_ENV = "MINDCARE_MEMORY_DB_PATH"


def resolve_memory_db_path() -> Path:
    """Resolve the memory path before constructing a MemoryManager."""
    configured = os.getenv(MEMORY_DB_ENV)
    if configured is None or not configured.strip():
        return (Path.cwd() / "memory_store.db").resolve()
    path = Path(configured).expanduser()
    if not path.is_absolute():
        raise ValueError(f"{MEMORY_DB_ENV} must be an absolute path")
    path = path.resolve()
    if not path.parent.is_dir():
        raise FileNotFoundError(
            f"Parent directory for {MEMORY_DB_ENV} does not exist: {path.parent}"
        )
    return path
