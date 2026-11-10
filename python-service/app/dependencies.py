import hmac

from fastapi import Header, HTTPException
from app.config import settings


async def verify_internal_key(x_internal_key: str = Header(...)):
    # Constant-time comparison so response timing doesn't leak the key.
    if not hmac.compare_digest(x_internal_key.encode(), settings.internal_key.encode()):
        raise HTTPException(status_code=401, detail="Invalid X-Internal-Key header")
    return x_internal_key
