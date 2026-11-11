"""Each supported file format goes through the endpoints end to end."""

import os

import pandas as pd
import pytest
from fastapi.testclient import TestClient

from app.main import app
from conftest import TEST_INTERNAL_KEY, TEST_STORAGE

client = TestClient(app)
AUTH = {"X-Internal-Key": TEST_INTERNAL_KEY}

PEOPLE = pd.DataFrame({"id": [1, 2, 2], "email": ["a@x.cm", "b@x.cm", "b@x.cm"]})


def write(name: str, writer: str) -> str:
    """Write PEOPLE to uploads/<name> with the given DataFrame writer; return the relative path."""
    os.makedirs(os.path.join(TEST_STORAGE, "uploads"), exist_ok=True)
    getattr(PEOPLE, writer)(os.path.join(TEST_STORAGE, "uploads", name), index=False)
    return f"uploads/{name}"


@pytest.mark.parametrize("name, writer", [
    ("people.csv", "to_csv"),
    ("PEOPLE_UPPER.CSV", "to_csv"),  # extensions are matched case-insensitively
    ("people.xlsx", "to_excel"),
    ("people.parquet", "to_parquet"),
])
def test_profile_supports_each_tabular_format(name, writer):
    response = client.post("/profile", headers=AUTH, json={"file_path": write(name, writer)})

    assert response.status_code == 200, response.text
    body = response.json()
    assert body["row_count"] == 3
    assert body["duplicate_rows"] == 1


def test_clean_reads_excel_and_writes_csv():
    response = client.post("/clean", headers=AUTH, json={
        "file_path": write("clean_me.xlsx", "to_excel"),
        "steps": [{"action": "deduplicate"}],
    })

    assert response.status_code == 200, response.text
    cleaned = response.json()["cleaned_file_path"]
    assert cleaned.endswith(".csv")
    assert len(pd.read_csv(os.path.join(TEST_STORAGE, cleaned))) == 2


def test_validate_reads_parquet():
    response = client.post("/validate", headers=AUTH, json={
        "file_path": write("validate_me.parquet", "to_parquet"),
        "schema_definition": {"columns": [{"name": "id", "type": "integer"}]},
    })

    assert response.status_code == 200, response.text
    assert response.json()["is_valid"] is True


@pytest.mark.parametrize("target_format, reader", [
    ("csv", pd.read_csv),
    ("json", pd.read_json),
    ("parquet", pd.read_parquet),
])
def test_export_writes_each_offered_format(target_format, reader):
    response = client.post("/export", headers=AUTH, json={
        "file_path": write(f"export_me_{target_format}.csv", "to_csv"),
        "format": target_format,
    })

    assert response.status_code == 200, response.text
    exported = os.path.join(TEST_STORAGE, response.json()["export_url"])
    assert len(reader(exported)) == 3


def test_unsupported_formats_are_a_client_error():
    os.makedirs(os.path.join(TEST_STORAGE, "uploads"), exist_ok=True)
    with open(os.path.join(TEST_STORAGE, "uploads", "notes.txt"), "w") as f:
        f.write("not a table")

    response = client.post("/profile", headers=AUTH, json={"file_path": "uploads/notes.txt"})

    assert response.status_code == 400
    assert "Unsupported file format" in response.json()["detail"]
