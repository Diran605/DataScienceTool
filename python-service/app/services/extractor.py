import os
import uuid
import pandas as pd
import pdfplumber
from app.config import settings
from app.schemas.responses import ExtractResponse

class ExtractorService:
    def extract(self, file_path: str) -> ExtractResponse:
        full_path = os.path.join(settings.storage_path, file_path)
        
        if not os.path.exists(full_path):
            raise FileNotFoundError(f"File not found: {full_path}")
            
        if not full_path.lower().endswith('.pdf'):
            raise ValueError("Only PDF extraction is supported currently.")
             
        # Use pdfplumber to extract tables from all pages
        all_data = []
        headers = None
        
        with pdfplumber.open(full_path) as pdf:
            for page in pdf.pages:
                tables = page.extract_tables()
                for table in tables:
                    if not table: continue
                    # Rough heuristic: if headers not set, take first row
                    if not headers:
                        headers = table[0]
                        all_data.extend(table[1:])
                    else:
                        all_data.extend(table)
                    
        if not all_data and not headers:
            raise ValueError("No tabular data found in the provided PDF.")
            
        # Clean up column names (remove newlines, none)
        clean_headers = [str(h).replace('\n', ' ').strip() if h else f"col_{i}" for i, h in enumerate(headers)] if headers else None
            
        df = pd.DataFrame(all_data, columns=clean_headers)
        
        # Save extracted data as CSV for further processing
        file_dir = os.path.dirname(file_path)
        new_filename = f"extracted_{uuid.uuid4().hex[:8]}.csv"
        extracted_rel_path = os.path.join(file_dir, new_filename).replace('\\', '/')
        extracted_full_path = os.path.join(settings.storage_path, extracted_rel_path)
        
        df.to_csv(extracted_full_path, index=False)
        
        return ExtractResponse(
            status="success",
            extracted_file_path=extracted_rel_path,
            row_count=len(df)
        )
