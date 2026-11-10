from fastapi import APIRouter, HTTPException
from app.errors import ClientError
from app.schemas.requests import ExtractRequest
from app.schemas.responses import ExtractResponse
from app.services.extractor import ExtractorService

router = APIRouter(prefix="/extract", tags=["extract"])

@router.post("", response_model=ExtractResponse)
async def extract_data(request: ExtractRequest):
    try:
        service = ExtractorService()
        return service.extract(request.file_path)
    except ClientError as e:
        raise HTTPException(status_code=400, detail=str(e))
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
