import os

import pandas as pd

from app.errors import ClientError

# Engines: CSV is built in; .xlsx needs openpyxl, .xls needs xlrd, .parquet needs pyarrow.
TABULAR_EXTENSIONS = (".csv", ".xlsx", ".xls", ".parquet")


class UnsupportedFormatError(ClientError):
    """The file isn't in a tabular format the service can load."""


def read_dataframe(full_path: str) -> pd.DataFrame:
    """Load a tabular file into a DataFrame, choosing the reader by extension."""
    if not os.path.exists(full_path):
        raise FileNotFoundError(f"File not found: {os.path.basename(full_path)}")

    extension = os.path.splitext(full_path)[1].lower()

    if extension == ".csv":
        return pd.read_csv(full_path)
    if extension == ".xlsx":
        return pd.read_excel(full_path, engine="openpyxl")
    if extension == ".xls":
        return pd.read_excel(full_path, engine="xlrd")
    if extension == ".parquet":
        return pd.read_parquet(full_path)

    raise UnsupportedFormatError(
        f"Unsupported file format '{extension or 'none'}'. Supported: {', '.join(TABULAR_EXTENSIONS)}."
    )
