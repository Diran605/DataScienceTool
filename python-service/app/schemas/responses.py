from pydantic import BaseModel
from typing import List, Dict, Any, Optional

class ColumnStat(BaseModel):
    type: str
    null_count: int
    unique_count: int
    null_percentage: Optional[float] = None
    unique_percentage: Optional[float] = None
    mean: Optional[float] = None
    median: Optional[float] = None
    mode: Optional[Any] = None
    std: Optional[float] = None
    variance: Optional[float] = None
    min: Optional[float] = None
    max: Optional[float] = None
    range: Optional[float] = None
    q1: Optional[float] = None
    q3: Optional[float] = None
    iqr: Optional[float] = None
    skewness: Optional[float] = None
    kurtosis: Optional[float] = None
    outlier_count: Optional[int] = None
    p5: Optional[float] = None
    p25: Optional[float] = None
    p50: Optional[float] = None
    p75: Optional[float] = None
    p95: Optional[float] = None
    histogram_bins: Optional[List[Dict[str, Any]]] = None
    top_values: Optional[List[Dict[str, Any]]] = None

class ProfileResponse(BaseModel):
    row_count: int
    column_count: int
    columns: Dict[str, ColumnStat]
    duplicate_rows: int
    memory_usage_kb: float
    correlation_matrix: Optional[List[Dict[str, Any]]] = None
    sample_rows: Optional[List[Dict[str, Any]]] = None

class ExtractResponse(BaseModel):
    status: str
    extracted_file_path: str
    row_count: int
    confidence_score: Optional[float] = None

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
    data_dictionary_url: Optional[str] = None
