from fastapi import APIRouter, HTTPException
from app.schemas.requests import ValidateRequest
from app.schemas.responses import ValidateResponse
from app.services.validator import ValidatorService

router = APIRouter(prefix="/validate", tags=["validate"])

@router.post("", response_model=ValidateResponse)
async def validate_data(request: ValidateRequest):
    try:
        service = ValidatorService()
        return service.validate(request.file_path, request.rules)
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
