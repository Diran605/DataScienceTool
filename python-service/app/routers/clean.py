from fastapi import APIRouter, HTTPException
from app.errors import ClientError
from app.schemas.requests import CleanRequest
from app.schemas.responses import CleanResponse
from app.services.cleaner import CleanerService

router = APIRouter(prefix="/clean", tags=["clean"])

@router.post("", response_model=CleanResponse)
async def clean_data(request: CleanRequest):
    try:
        service = CleanerService()
        return service.clean(request.file_path, request.steps, request.options)
    except ClientError as e:
        raise HTTPException(status_code=400, detail=str(e))
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
