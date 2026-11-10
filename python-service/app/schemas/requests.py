from pydantic import BaseModel
from typing import List, Optional, Dict, Any

class ProfileRequest(BaseModel):
    file_path: str
    options: Optional[Dict[str, Any]] = None

# Field names here must match the payloads built in Laravel's
# app/Services/PythonServiceClient.php (covered by tests/test_contract.py).

class ExtractRequest(BaseModel):
    file_path: str

class CleanRequest(BaseModel):
    file_path: str
    steps: List[Dict[str, Any]]
    options: Optional[Dict[str, Any]] = None

class ValidateRequest(BaseModel):
    file_path: str
    # The contract's schema_definition, e.g. {"columns": [{"name": "id", "type": "integer", "required": true}]}
    schema_definition: Dict[str, Any]

class ExportRequest(BaseModel):
    file_path: str
    format: str
    options: Optional[Dict[str, Any]] = None
