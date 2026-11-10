from fastapi import APIRouter, HTTPException
from app.errors import ClientError
from app.schemas.requests import ProfileRequest
from app.schemas.responses import ProfileResponse
from app.services.profiler import ProfilerService

router = APIRouter(prefix="/profile", tags=["profile"])

@router.post("", response_model=ProfileResponse)
async def profile_data(request: ProfileRequest):
    try:
        service = ProfilerService()
        return service.profile(request.file_path, request.options)
    except ClientError as e:
        raise HTTPException(status_code=400, detail=str(e))
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
