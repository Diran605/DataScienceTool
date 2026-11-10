import os

import pandas as pd
import pytest
from fastapi.testclient import TestClient

from app.errors import UnsafeExpressionError, UnsafePathError
from app.main import app
from app.services.expressions import apply_filter_expression
from app.storage import resolve_storage_path
from conftest import TEST_INTERNAL_KEY, TEST_STORAGE

client = TestClient(app)
AUTH = {"X-Internal-Key": TEST_INTERNAL_KEY}


# --- Internal key -----------------------------------------------------------

def test_requests_without_key_are_rejected():
    assert client.get("/health").status_code == 422  # header missing


def test_requests_with_wrong_key_are_rejected():
    assert client.get("/health", headers={"X-Internal-Key": "wrong"}).status_code == 401


def test_requests_with_correct_key_are_accepted():
    assert client.get("/health", headers=AUTH).status_code == 200


@pytest.mark.parametrize("key", ["", "local-dev-secret-key", "default-dev-key", "short"])
def test_service_refuses_to_start_with_missing_or_weak_key(monkeypatch, key):
    from app import config

    monkeypatch.setenv("INTERNAL_KEY", key)
    with pytest.raises(RuntimeError):
        config.get_settings()


# --- Storage path guard -----------------------------------------------------

def test_paths_inside_storage_are_resolved():
    assert resolve_storage_path("uploads/a.csv") == os.path.join(os.path.realpath(TEST_STORAGE), "uploads", "a.csv")


@pytest.mark.parametrize("path", [
    "../secret.txt",
    "uploads/../../secret.txt",
    os.path.abspath(os.sep + "etc"),
    "",
    ".",
    "uploads/\x00evil",
])
def test_paths_outside_storage_are_rejected(path):
    with pytest.raises(UnsafePathError):
        resolve_storage_path(path)


def test_endpoint_returns_400_for_path_traversal():
    response = client.post("/profile", json={"file_path": "../../etc/passwd"}, headers=AUTH)
    assert response.status_code == 400


# --- Filter expressions -----------------------------------------------------

@pytest.fixture
def people():
    return pd.DataFrame({
        "age": [12, 25, 40],
        "country": ["CM", "NG", "FR"],
        "unit price": [1.0, 5.0, 10.0],
    })


@pytest.mark.parametrize("expression, expected_rows", [
    ("age >= 18", 2),
    ("age >= 18 and country == 'NG'", 1),
    ("country in ['CM', 'FR']", 2),
    ("not (age > 30)", 2),
    ("`unit price` * 2 > 3", 2),
])
def test_allowed_expressions_filter_rows(people, expression, expected_rows):
    assert len(apply_filter_expression(people, expression)) == expected_rows


@pytest.mark.parametrize("expression", [
    "__import__('os').system('echo hacked')",
    "age.__class__",
    "@secret > 1",
    "age > 1; import os",
    "country.str.len() > 1",
    "missing_column > 1",
    "[c for c in country]",
    "lambda: 1",
    "age[0] > 1",
    "a" * 501,
])
def test_unsafe_or_invalid_expressions_are_rejected(people, expression):
    with pytest.raises(UnsafeExpressionError):
        apply_filter_expression(people, expression)


def test_clean_endpoint_returns_400_for_unsafe_expression():
    os.makedirs(os.path.join(TEST_STORAGE, "uploads"), exist_ok=True)
    pd.DataFrame({"age": [1, 2]}).to_csv(os.path.join(TEST_STORAGE, "uploads", "people.csv"), index=False)

    response = client.post("/clean", headers=AUTH, json={
        "file_path": "uploads/people.csv",
        "steps": [{"action": "custom_expression", "value": "__import__('os').getcwd()"}],
        "options": {"preview_only": True},
    })

    assert response.status_code == 400
    assert "not allowed" in response.json()["detail"]
