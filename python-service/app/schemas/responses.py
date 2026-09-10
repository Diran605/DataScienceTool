from pydantic import BaseModel
from typing import List, Dict, Any, Optional

class ColumnStat(BaseModel):
    type: str
    null_count: int
    unique_count: int
    mean: Optional[float] = None
    min: Optional[float] = None
    max: Optional[float] = None

class ProfileResponse(BaseModel):
    row_count: int
    column_count: int
    columns: Dict[str, ColumnStat]
    duplicate_rows: int
    memory_usage_kb: float

class ExtractResponse(BaseModel):
    status: str
    extracted_file_path: str
    row_count: int

class CleanResponse(BaseModel):
    status: str
    cleaned_file_path: str
    rows_modified: int
    diff_summary: Dict[str, Any]

class ValidateResponse(BaseModel):
    is_valid: bool
    errors: List[Dict[str, Any]]

class ExportResponse(BaseModel):
    status: str
    export_url: str
    file_size_bytes: int
