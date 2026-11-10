import os

from app.config import settings
from app.errors import UnsafePathError


def resolve_storage_path(relative_path: str) -> str:
    """Return the absolute path for a file inside shared storage.

    Rejects anything that would resolve outside the storage directory, such as
    "../../etc/passwd", absolute paths, or symlinks pointing elsewhere. Every file
    path received from Laravel must go through this before being opened or written.
    """
    if not relative_path or "\x00" in relative_path:
        raise UnsafePathError("File path is empty or invalid.")

    base = os.path.realpath(settings.storage_path)
    full = os.path.realpath(os.path.join(base, relative_path))

    try:
        inside = os.path.commonpath([base, full]) == base
    except ValueError:  # e.g. different drives on Windows
        inside = False

    if not inside or full == base:
        raise UnsafePathError("File path must point to a file inside shared storage.")

    return full
