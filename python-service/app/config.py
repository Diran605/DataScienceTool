import os
from pydantic import BaseModel

from dotenv import load_dotenv

load_dotenv()

# Placeholder values from example files / old defaults. Running with one of these
# would mean anyone who has read the repo knows the key.
_KNOWN_PLACEHOLDER_KEYS = {
    "default-dev-key",
    "local-dev-secret-key",
    "your-secret-key-here",
}

MIN_INTERNAL_KEY_LENGTH = 16


class Settings(BaseModel):
    internal_key: str
    storage_path: str


def get_settings() -> Settings:
    internal_key = os.environ.get("INTERNAL_KEY", "").strip()

    if not internal_key:
        raise RuntimeError("INTERNAL_KEY is not set. Refusing to start without a shared secret.")

    if internal_key in _KNOWN_PLACEHOLDER_KEYS or len(internal_key) < MIN_INTERNAL_KEY_LENGTH:
        raise RuntimeError(
            f"INTERNAL_KEY must be a random value of at least {MIN_INTERNAL_KEY_LENGTH} characters, "
            "not a placeholder. Generate one with: python -c \"import secrets; print(secrets.token_hex(32))\""
        )

    return Settings(
        internal_key=internal_key,
        storage_path=os.environ.get("STORAGE_PATH", "/tmp/storage"),
    )


settings = get_settings()
