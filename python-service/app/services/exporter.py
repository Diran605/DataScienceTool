import os
import json
import pandas as pd
import uuid
from app.config import settings
from app.storage import resolve_storage_path
from app.dataframes import read_dataframe
from app.schemas.responses import ExportResponse
from typing import Optional, Dict, Any

class ExporterService:
    def export(self, file_path: str, target_format: str, schema_definition: Optional[Dict[str, Any]] = None) -> ExportResponse:
        full_path = resolve_storage_path(file_path)
        
        df = read_dataframe(full_path)
            
        file_dir = os.path.dirname(file_path)
        base_filename = f"exported_{uuid.uuid4().hex[:8]}"
        new_filename = f"{base_filename}.{target_format}"
        exported_rel_path = os.path.join(file_dir, new_filename).replace('\\', '/')
        exported_full_path = resolve_storage_path(exported_rel_path)
        
        # Optionally filter to only the contract columns if schema_definition is provided
        if schema_definition and 'columns' in schema_definition:
            expected_cols = [col['name'] for col in schema_definition['columns']]
            existing_cols = [c for c in expected_cols if c in df.columns]
            df = df[existing_cols]
        
        if target_format == 'csv':
            df.to_csv(exported_full_path, index=False)
        elif target_format == 'json':
            df.to_json(exported_full_path, orient='records', lines=True)
        elif target_format == 'parquet':
            df.to_parquet(exported_full_path, index=False)
        else:
            raise ValueError(f"Unsupported export format: {target_format}")
            
        size_bytes = os.path.getsize(exported_full_path)
        
        # Generate Data Dictionary
        dict_filename = f"{base_filename}_dictionary.json"
        dict_rel_path = os.path.join(file_dir, dict_filename).replace('\\', '/')
        dict_full_path = resolve_storage_path(dict_rel_path)
        
        data_dict = {
            "dataset_info": {
                "rows": len(df),
                "columns": len(df.columns)
            },
            "columns": []
        }
        
        for col in df.columns:
            data_dict["columns"].append({
                "name": col,
                "type": str(df[col].dtype),
                "null_count": int(df[col].isna().sum()),
                "unique_count": int(df[col].nunique())
            })
            
        with open(dict_full_path, 'w') as f:
            json.dump(data_dict, f, indent=2)
        
        return ExportResponse(
            status="success",
            export_url=exported_rel_path,
            file_size_bytes=size_bytes,
            data_dictionary_url=dict_rel_path
        )
