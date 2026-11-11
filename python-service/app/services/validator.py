import os
import pandas as pd
from app.config import settings
from app.storage import resolve_storage_path
from app.dataframes import read_dataframe
from app.schemas.responses import ValidateResponse
from typing import Dict, Any

class ValidatorService:
    def validate(self, file_path: str, schema_definition: Dict[str, Any]) -> ValidateResponse:
        full_path = resolve_storage_path(file_path)
        
        df = read_dataframe(full_path)
            
        errors = []
        expected_columns = schema_definition.get('columns', [])
        
        df_cols = list(df.columns)
        
        # 1. Check missing columns
        for expected_col in expected_columns:
            name = expected_col.get('name')
            # Optional columns ("required": false) may be absent.
            if name not in df_cols and expected_col.get('required', True):
                errors.append({
                    "rule": "missing_column",
                    "column": name,
                    "message": f"Required column '{name}' is missing."
                })
                
        # 2. Check types & nulls
        for expected_col in expected_columns:
            name = expected_col.get('name')
            if name not in df_cols:
                continue
                
            expected_type = expected_col.get('type')
            is_nullable = expected_col.get('nullable', True)
            
            series = df[name]
            
            # Check nulls
            if not is_nullable:
                null_count = int(series.isna().sum())
                if null_count > 0:
                    errors.append({
                        "rule": "not_null",
                        "column": name,
                        "message": f"Column '{name}' contains {null_count} null values."
                    })
                    
            # Check types (basic pandas type checking)
            if expected_type in ['integer', 'int']:
                # Allow floats if they don't contain NaNs and only have integer values, or just check numeric
                if not pd.api.types.is_numeric_dtype(series):
                    errors.append({
                        "rule": "type_mismatch",
                        "column": name,
                        "message": f"Column '{name}' expected {expected_type} but got {series.dtype}."
                    })
            elif expected_type in ['float', 'decimal', 'numeric']:
                if not pd.api.types.is_numeric_dtype(series):
                    errors.append({
                        "rule": "type_mismatch",
                        "column": name,
                        "message": f"Column '{name}' expected {expected_type} but got {series.dtype}."
                    })
            elif expected_type in ['boolean', 'bool']:
                if not pd.api.types.is_bool_dtype(series) and not set(series.dropna().unique()).issubset({0, 1, '0', '1', 'true', 'false', 'True', 'False'}):
                    errors.append({
                        "rule": "type_mismatch",
                        "column": name,
                        "message": f"Column '{name}' expected boolean values."
                    })
                    
        is_valid = len(errors) == 0
        return ValidateResponse(is_valid=is_valid, errors=errors)
