import os
import pandas as pd
import uuid
from app.config import settings
from app.schemas.responses import CleanResponse
from typing import List, Dict, Any

class CleanerService:
    def clean(self, file_path: str, steps: List[Dict[str, Any]]) -> CleanResponse:
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
            raise ValueError(f"Unsupported file format for cleaning.")
            
        initial_rows = len(df)
        
        for step in steps:
            action = step.get('action')
            if action == 'drop_nulls':
                cols = step.get('columns')
                if cols:
                    df = df.dropna(subset=cols)
                else:
                    df = df.dropna()
            elif action == 'fill_nulls':
                strategy = step.get('strategy', 'zero')
                cols = step.get('columns', df.columns)
                for col in cols:
                    if col not in df.columns:
                        continue
                    if strategy == 'zero':
                        df[col] = df[col].fillna(0)
                    elif strategy == 'mean' and pd.api.types.is_numeric_dtype(df[col]):
                        df[col] = df[col].fillna(df[col].mean())
                    elif strategy == 'median' and pd.api.types.is_numeric_dtype(df[col]):
                        df[col] = df[col].fillna(df[col].median())
                    elif strategy == 'custom':
                        val = step.get('value', '')
                        df[col] = df[col].fillna(val)
            elif action == 'deduplicate':
                df = df.drop_duplicates()
            elif action == 'rename_columns':
                mapping = step.get('mapping', {})
                df = df.rename(columns=mapping)
            
        final_rows = len(df)
        rows_modified = initial_rows - final_rows
        
        # Save cleaned file
        file_dir = os.path.dirname(file_path)
        new_filename = f"cleaned_{uuid.uuid4().hex[:8]}.csv"
        # Always output as CSV after cleaning for downstream compatibility
        cleaned_rel_path = os.path.join(file_dir, new_filename).replace('\\', '/')
        cleaned_full_path = os.path.join(settings.storage_path, cleaned_rel_path)
        
        df.to_csv(cleaned_full_path, index=False)
        
        return CleanResponse(
            status="success",
            cleaned_file_path=cleaned_rel_path,
            rows_modified=abs(rows_modified),
            diff_summary={
                "initial_rows": initial_rows,
                "final_rows": final_rows,
                "steps_applied": len(steps)
            }
        )
