"""Contract tests: each request below mirrors the payload Laravel's
app/Services/PythonServiceClient.php sends. If a field is renamed on either
side, these tests (or tests/Feature/PythonServiceClientTest.php) should fail.
"""

import os

import pandas as pd
import pytest
from fastapi.testclient import TestClient

from app.main import app
from conftest import TEST_INTERNAL_KEY, TEST_STORAGE

client = TestClient(app)
AUTH = {"X-Internal-Key": TEST_INTERNAL_KEY}

CONTRACT = {
    "columns": [
        {"name": "id", "type": "integer", "required": True},
        {"name": "email", "type": "string", "required": True, "nullable": False},
        {"name": "created_at", "type": "datetime", "required": False},
    ]
}


@pytest.fixture
def csv_file():
    def _write(name: str, df: pd.DataFrame) -> str:
        os.makedirs(os.path.join(TEST_STORAGE, "uploads"), exist_ok=True)
        df.to_csv(os.path.join(TEST_STORAGE, "uploads", name), index=False)
        return f"uploads/{name}"

    return _write


def test_profile_accepts_laravel_payload(csv_file):
    path = csv_file("profile.csv", pd.DataFrame({"id": [1, 2], "email": ["a@x.cm", None]}))

    response = client.post("/profile", headers=AUTH, json={"file_path": path})

    assert response.status_code == 200, response.text
    assert response.json()["row_count"] == 2


def test_clean_accepts_laravel_payload_without_options(csv_file):
    path = csv_file("clean.csv", pd.DataFrame({"id": [1, 1, 2]}))

    # Laravel omits `options` entirely when there are none.
    response = client.post("/clean", headers=AUTH, json={"file_path": path, "steps": [{"action": "deduplicate"}]})

    assert response.status_code == 200, response.text
    cleaned = response.json()["cleaned_file_path"]
    assert os.path.exists(os.path.join(TEST_STORAGE, cleaned))


def test_clean_preview_option_is_honoured_and_writes_no_file(csv_file):
    path = csv_file("preview.csv", pd.DataFrame({"id": [1, 1, 2]}))
    files_before = set(os.listdir(os.path.join(TEST_STORAGE, "uploads")))

    response = client.post("/clean", headers=AUTH, json={
        "file_path": path,
        "steps": [{"action": "deduplicate"}],
        "options": {"preview_only": True},
    })

    assert response.status_code == 200, response.text
    body = response.json()
    assert body["diff_summary"]["is_preview"] is True
    assert body["cleaned_file_path"] == ""
    assert set(os.listdir(os.path.join(TEST_STORAGE, "uploads"))) == files_before


def test_validate_accepts_laravel_payload_and_passes_valid_data(csv_file):
    path = csv_file("valid.csv", pd.DataFrame({"id": [1, 2], "email": ["a@x.cm", "b@x.cm"]}))

    response = client.post("/validate", headers=AUTH, json={"file_path": path, "schema_definition": CONTRACT})

    assert response.status_code == 200, response.text
    assert response.json() == {"is_valid": True, "errors": []}


def test_validate_reports_violations(csv_file):
    path = csv_file("invalid.csv", pd.DataFrame({"email": ["a@x.cm", None]}))

    response = client.post("/validate", headers=AUTH, json={"file_path": path, "schema_definition": CONTRACT})

    assert response.status_code == 200, response.text
    body = response.json()
    rules = {(e["rule"], e["column"]) for e in body["errors"]}
    assert body["is_valid"] is False
    assert ("missing_column", "id") in rules
    assert ("not_null", "email") in rules
    # created_at is optional, so its absence is not a violation.
    assert not any(e["column"] == "created_at" for e in body["errors"])


def test_extract_accepts_laravel_payload():
    # Only file_path is sent; a non-PDF is a client error, not a crash or schema rejection.
    response = client.post("/extract", headers=AUTH, json={"file_path": "uploads/clean.csv"})

    assert response.status_code != 422, response.text


def test_export_accepts_laravel_payload(csv_file):
    path = csv_file("export.csv", pd.DataFrame({"id": [1, 2]}))

    response = client.post("/export", headers=AUTH, json={"file_path": path, "format": "csv", "options": CONTRACT})

    assert response.status_code == 200, response.text
    assert response.json()["export_url"]
