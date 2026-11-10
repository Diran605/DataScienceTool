import os
import pandas as pd
import numpy as np
from app.config import settings
from app.storage import resolve_storage_path
from app.schemas.responses import ProfileResponse, ColumnStat
from typing import Dict, Any, Optional

class ProfilerService:
    def profile(self, file_path: str, options: Optional[Dict[str, Any]] = None) -> ProfileResponse:
        full_path = resolve_storage_path(file_path)
        
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
            
        # Sampling Mode for large files
        if options and options.get('sample_size'):
            sample_size = int(options.get('sample_size'))
            if len(df) > sample_size:
                df = df.sample(n=sample_size, random_state=42)

        return self.profile_dataframe(df)

    def profile_dataframe(self, df: pd.DataFrame) -> ProfileResponse:
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
            
            null_percentage = (null_count / row_count) * 100 if row_count > 0 else 0
            unique_percentage = (unique_count / row_count) * 100 if row_count > 0 else 0
            
            stat = ColumnStat(
                type=dtype,
                null_count=null_count,
                unique_count=unique_count,
                null_percentage=null_percentage,
                unique_percentage=unique_percentage
            )
            
            # Numeric Stats
            if pd.api.types.is_numeric_dtype(series):
                clean_series = series.dropna()
                if not clean_series.empty:
                    stat.mean = float(clean_series.mean())
                    stat.median = float(clean_series.median())
                    stat.std = float(clean_series.std()) if len(clean_series) > 1 else 0
                    stat.variance = float(clean_series.var()) if len(clean_series) > 1 else 0
                    stat.min = float(clean_series.min())
                    stat.max = float(clean_series.max())
                    stat.range = stat.max - stat.min
                    stat.q1 = float(clean_series.quantile(0.25))
                    stat.q3 = float(clean_series.quantile(0.75))
                    stat.iqr = stat.q3 - stat.q1
                    stat.skewness = float(clean_series.skew())
                    stat.kurtosis = float(clean_series.kurtosis())
                    
                    # Outliers (IQR method)
                    lower_bound = stat.q1 - 1.5 * stat.iqr
                    upper_bound = stat.q3 + 1.5 * stat.iqr
                    stat.outlier_count = int(((clean_series < lower_bound) | (clean_series > upper_bound)).sum())
                    
                    # Percentiles
                    stat.p5 = float(clean_series.quantile(0.05))
                    stat.p25 = stat.q1
                    stat.p50 = stat.median
                    stat.p75 = stat.q3
                    stat.p95 = float(clean_series.quantile(0.95))
                    
                    # Histogram
                    try:
                        counts, bin_edges = np.histogram(clean_series, bins=10)
                        stat.histogram_bins = [
                            {"min": float(bin_edges[i]), "max": float(bin_edges[i+1]), "count": int(counts[i])}
                            for i in range(len(counts))
                        ]
                    except Exception:
                        pass
            
            # Categorical Stats
            if pd.api.types.is_object_dtype(series) or pd.api.types.is_categorical_dtype(series):
                clean_series = series.dropna()
                if not clean_series.empty:
                    try:
                        mode_val = clean_series.mode()
                        if not mode_val.empty:
                            stat.mode = str(mode_val.iloc[0])
                        
                        top_vals = clean_series.value_counts().head(10)
                        stat.top_values = [
                            {"value": str(idx), "count": int(count), "percentage": (int(count)/len(clean_series))*100}
                            for idx, count in top_vals.items()
                        ]
                    except Exception:
                        pass
                        
            columns[col] = stat

        # Correlation Matrix
        correlation_matrix = []
        numeric_df = df.select_dtypes(include=[np.number])
        if not numeric_df.empty and len(numeric_df.columns) > 1:
            corr_df = numeric_df.corr()
            for i in range(len(corr_df.columns)):
                for j in range(i+1, len(corr_df.columns)):
                    col_a = corr_df.columns[i]
                    col_b = corr_df.columns[j]
                    coef = corr_df.iloc[i, j]
                    if not pd.isna(coef):
                        correlation_matrix.append({
                            "col_a": col_a,
                            "col_b": col_b,
                            "coefficient": float(coef)
                        })

        # Sample rows
        sample_rows = df.head(5).replace({np.nan: None}).to_dict(orient='records')
            
        return ProfileResponse(
            row_count=row_count,
            column_count=column_count,
            columns=columns,
            duplicate_rows=duplicate_rows,
            memory_usage_kb=memory_usage_kb,
            correlation_matrix=correlation_matrix,
            sample_rows=sample_rows
        )
