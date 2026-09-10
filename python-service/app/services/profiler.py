import os
import pandas as pd
from app.config import settings
from app.schemas.responses import ProfileResponse, ColumnStat
from typing import Dict, Any, Optional

class ProfilerService:
    def profile(self, file_path: str, options: Optional[Dict[str, Any]] = None) -> ProfileResponse:
        full_path = os.path.join(settings.storage_path, file_path)
        
        if not os.path.exists(full_path):
            raise FileNotFoundError(f"File not found: {full_path}")
            
        if full_path.endswith('.csv'):
            df = pd.read_csv(full_path)
        elif full_path.endswith(('.xlsx', '.xls')):
            df = pd.read_excel(full_path)
        elif full_path.endswith('.parquet'):
            df = pd.read_parquet(full_path)
        else:
            raise ValueError(f"Unsupported file format for {file_path}")
            
        row_count = len(df)
        column_count = len(df.columns)
        duplicate_rows = int(df.duplicated().sum())
        memory_usage_kb = float(df.memory_usage(deep=True).sum() / 1024)
        
        columns = {}
        for col in df.columns:
            series = df[col]
            dtype = str(series.dtype)
            null_count = int(series.isna().sum())
            unique_count = int(series.nunique())
            
            mean_val = None
            min_val = None
            max_val = None
            
            if pd.api.types.is_numeric_dtype(series):
                mean_val = float(series.mean()) if not pd.isna(series.mean()) else None
                min_val = float(series.min()) if not pd.isna(series.min()) else None
                max_val = float(series.max()) if not pd.isna(series.max()) else None
                
            columns[col] = ColumnStat(
                type=dtype,
                null_count=null_count,
                unique_count=unique_count,
                mean=mean_val,
                min=min_val,
                max=max_val
            )
            
        return ProfileResponse(
            row_count=row_count,
            column_count=column_count,
            columns=columns,
            duplicate_rows=duplicate_rows,
            memory_usage_kb=memory_usage_kb
        )
