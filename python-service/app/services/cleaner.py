import os
import pandas as pd
import uuid
from app.config import settings
from app.storage import resolve_storage_path
from app.dataframes import read_dataframe
from app.errors import UnsafeExpressionError
from app.services.expressions import apply_filter_expression
from app.schemas.responses import CleanResponse
from typing import List, Dict, Any, Optional

class CleanerService:
    def clean(self, file_path: str, steps: List[Dict[str, Any]], options: Optional[Dict[str, Any]] = None) -> CleanResponse:
        full_path = resolve_storage_path(file_path)
        
        df = read_dataframe(full_path)
            
        # Preview Mode
        is_preview = options and options.get('preview_only', False)
        if is_preview and len(df) > 1000:
            df = df.head(1000)
            
        initial_rows = len(df)
        
        for step in steps:
            action = step.get('action')
            if action == 'drop_nulls':
                cols = step.get('columns')
                if cols:
                    df = df.dropna(subset=cols)
                else:
                    df = df.dropna()
            elif action == 'drop_columns':
                cols = step.get('columns')
                if cols:
                    df = df.drop(columns=[c for c in cols if c in df.columns])
            elif action == 'handle_outliers':
                strategy = step.get('strategy', 'cap') # 'cap' or 'filter'
                method = step.get('value', '0.99') # can be string '0.99' or absolute value
                cols = step.get('columns')
                if cols:
                    for col in cols:
                        if col not in df.columns or not pd.api.types.is_numeric_dtype(df[col]):
                            continue
                        
                        try:
                            val = float(method)
                        except:
                            continue
                            
                        # If value is between 0 and 1, treat as percentile. Else treat as absolute threshold.
                        if 0 < val < 1:
                            upper_bound = df[col].quantile(val)
                            lower_bound = df[col].quantile(1 - val)
                        else:
                            upper_bound = val
                            lower_bound = -val # naive assumption for absolute
                            
                        if strategy == 'cap':
                            df[col] = df[col].clip(lower=lower_bound, upper=upper_bound)
                        elif strategy == 'filter':
                            df = df[(df[col] >= lower_bound) & (df[col] <= upper_bound)]
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
            elif action == 'normalize_text':
                cols = step.get('columns')
                if not cols:
                    cols = df.select_dtypes(include=['object', 'string']).columns
                for col in cols:
                    if col in df.columns:
                        df[col] = df[col].astype(str).str.lower().str.strip()
            elif action == 'deduplicate':
                df = df.drop_duplicates()
            elif action == 'normalize_dates':
                cols = step.get('columns', [])
                for col in cols:
                    if col in df.columns:
                        df[col] = pd.to_datetime(df[col], errors='coerce').dt.strftime('%Y-%m-%d')
            elif action == 'regex_extract':
                cols = step.get('columns', [])
                pattern = step.get('value', '')
                if pattern:
                    for col in cols:
                        if col in df.columns:
                            extracted = df[col].astype(str).str.extract(pattern)
                            for extr_col in extracted.columns:
                                new_col_name = f"{col}_extracted_{extr_col}" if str(extr_col).isdigit() else extr_col
                                df[new_col_name] = extracted[extr_col]
            elif action == 'rename_columns':
                mapping = step.get('mapping', {})
                df = df.rename(columns=mapping)
            elif action == 'custom_expression':
                expression = step.get('value')
                if expression:
                    # Row filter. The expression is checked against a strict
                    # allow-list first; unsafe input raises UnsafeExpressionError (HTTP 400).
                    try:
                        df = apply_filter_expression(df, expression)
                    except UnsafeExpressionError:
                        raise
                    except Exception as e:
                        raise UnsafeExpressionError(f"Failed to evaluate filter expression: {e}") from None
            
        final_rows = len(df)
        rows_modified = initial_rows - final_rows
        
        if is_preview:
            from app.services.profiler import ProfilerService
            profiler = ProfilerService()
            profile_response = profiler.profile_dataframe(df)
            
            return CleanResponse(
                status="success",
                cleaned_file_path="",
                rows_modified=abs(rows_modified),
                diff_summary={
                    "initial_rows": initial_rows,
                    "final_rows": final_rows,
                    "steps_applied": len(steps),
                    "is_preview": True,
                    "preview_data": df.head(5).replace({pd.NA: None, float('nan'): None}).to_dict(orient='records'),
                    "preview_profile": profile_response.dict()
                }
            )
        
        # Save cleaned file
        file_dir = os.path.dirname(file_path)
        new_filename = f"cleaned_{uuid.uuid4().hex[:8]}.csv"
        # Always output as CSV after cleaning for downstream compatibility
        cleaned_rel_path = os.path.join(file_dir, new_filename).replace('\\', '/')
        cleaned_full_path = resolve_storage_path(cleaned_rel_path)
        
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
