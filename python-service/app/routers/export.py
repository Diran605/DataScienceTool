from fastapi import APIRouter, HTTPException
from app.errors import ClientError
from app.schemas.requests import ExportRequest
from app.schemas.responses import ExportResponse
from app.services.exporter import ExporterService

router = APIRouter(prefix="/export", tags=["export"])

@router.post("", response_model=ExportResponse)
async def export_data(request: ExportRequest):
    try:
        service = ExporterService()
        return service.export(request.file_path, request.format, request.options)
    except ClientError as e:
        raise HTTPException(status_code=400, detail=str(e))
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
