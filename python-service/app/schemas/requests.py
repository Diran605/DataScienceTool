from pydantic import BaseModel
from typing import List, Optional, Dict, Any

class ProfileRequest(BaseModel):
    file_path: str
    options: Optional[Dict[str, Any]] = None

class ExtractRequest(BaseModel):
    file_path: str
    format: str

class CleanRequest(BaseModel):
    file_path: str
    steps: List[Dict[str, Any]]
    options: Optional[Dict[str, Any]] = None

class ValidateRequest(BaseModel):
    file_path: str
    rules: List[Dict[str, Any]]

class ExportRequest(BaseModel):
    file_path: str
    format: str
    options: Optional[Dict[str, Any]] = None
