from fastapi import Header, HTTPException
from app.config import settings

async def verify_internal_key(x_internal_key: str = Header(...)):
    if x_internal_key != settings.internal_key:
        raise HTTPException(status_code=401, detail="Invalid X-Internal-Key header")
    return x_internal_key
