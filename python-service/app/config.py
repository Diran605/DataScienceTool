import os
from pydantic import BaseModel

class Settings(BaseModel):
    internal_key: str
    storage_path: str

from dotenv import load_dotenv

load_dotenv()

def get_settings():
    return Settings(
        internal_key=os.environ.get("INTERNAL_KEY", "default-dev-key"),
        storage_path=os.environ.get("STORAGE_PATH", "/tmp/storage")
    )

settings = get_settings()
